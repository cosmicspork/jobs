<?php

namespace App\Filament\Pages;

use App\Ai\Agents\ApplicationQuestionsAgent;
use App\Ai\ProviderFreeze;
use App\ApplicationQuestionSetStatus;
use App\Models\ApplicationQuestion;
use App\Models\ApplicationQuestionSet;
use App\Models\Listing;
use App\Models\TargetProfile;
use App\Models\User;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Exceptions\AiException;

/**
 * @property-read Schema $form
 */
class ApplicationQuestions extends Page
{
    protected string $view = 'filament.pages.application-questions';

    protected static ?string $title = 'Application Questions';

    protected static ?string $navigationLabel = 'Application Questions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    private const EMPTY_QUESTIONS = [['question' => '', 'answer' => '']];

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public ?string $questionSetId = null;

    public bool $showResults = false;

    /** @var array<int, array<string, mixed>> */
    public array $results = [];

    public function mount(): void
    {
        $listingId = request()->query('listing');

        if ($listingId) {
            $questionSet = ApplicationQuestionSet::with('questions')
                ->where('listing_id', $listingId)
                ->where('user_id', auth()->id())
                ->latest()
                ->first();

            if ($questionSet) {
                $this->questionSetId = $questionSet->id;
                $this->loadQuestionSet($questionSet);

                return;
            }
        }

        /** @var User $user */
        $user = auth()->user();
        $defaultTarget = $listingId
            ? $user->bestTargetFor(Listing::find($listingId))
            : $user->activeTargets()->first();

        $this->form->fill([
            'listing_id' => $listingId,
            'target_profile_id' => $defaultTarget?->id,
            'questions' => self::EMPTY_QUESTIONS,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        /** @var User $user */
        $user = auth()->user();
        $targetOptions = $user->activeTargets()
            ->mapWithKeys(fn (TargetProfile $t) => [$t->id => $t->name])
            ->all();

        return $schema
            ->components([
                Form::make([
                    Select::make('target_profile_id')
                        ->label('Target')
                        ->options($targetOptions)
                        ->required()
                        ->disabled($this->showResults),
                    Select::make('listing_id')
                        ->label('Listing')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search) => Listing::query()
                            ->where('title', 'like', "%{$search}%")
                            ->orWhere('company', 'like', "%{$search}%")
                            ->limit(20)
                            ->get()
                            ->mapWithKeys(fn (Listing $listing) => [
                                $listing->id => "{$listing->title} — {$listing->company}",
                            ]))
                        ->getOptionLabelUsing(function (?string $value) {
                            $listing = $value ? Listing::find($value) : null;

                            return $listing ? "{$listing->title} — {$listing->company}" : null;
                        })
                        ->placeholder('Select a listing (optional)')
                        ->disabled($this->showResults),
                    Repeater::make('questions')
                        ->schema([
                            TextInput::make('question')
                                ->label('Question')
                                ->required()
                                ->columnSpanFull(),
                            Textarea::make('answer')
                                ->label('Your Answer')
                                ->required()
                                ->rows(4)
                                ->columnSpanFull(),
                        ])
                        ->addActionLabel('Add Question')
                        ->minItems(1)
                        ->reorderable(false)
                        ->hidden($this->showResults),
                ]),
            ])
            ->statePath('data');
    }

    public function submitForReview(): void
    {
        $state = $this->form->getState();
        $listingId = $state['listing_id'] ?? null;
        $targetId = $state['target_profile_id'] ?? null;
        $questions = $state['questions'];

        /** @var User $user */
        $user = auth()->user();

        $target = $targetId
            ? $user->targetProfiles()->where('id', $targetId)->where('is_active', true)->first()
            : null;

        if (! $target instanceof TargetProfile) {
            Notification::make()->title('Target required')->body('Pick an active target to review against.')->danger()->send();

            return;
        }

        $questionSet = $this->questionSetId
            ? ApplicationQuestionSet::findOrFail($this->questionSetId)
            : ApplicationQuestionSet::create([
                'listing_id' => $listingId,
                'user_id' => auth()->id(),
                'target_profile_id' => $target->id,
                'status' => ApplicationQuestionSetStatus::Reviewing,
            ]);

        $questionSet->update([
            'target_profile_id' => $target->id,
            'status' => ApplicationQuestionSetStatus::Reviewing,
        ]);

        $questionSet->questions()->delete();

        $questionRecords = [];
        foreach ($questions as $q) {
            $questionRecords[] = $questionSet->questions()->create([
                'question' => $q['question'],
                'answer' => $q['answer'],
            ]);
        }

        $this->questionSetId = $questionSet->id;

        $prompt = $this->buildAgentPrompt($questions, $listingId);

        $agent = new ApplicationQuestionsAgent($user, $target);
        $provider = config('ai.agents.app_questions.provider');

        if ($frozenUntil = ProviderFreeze::providerFrozenUntil($provider)) {
            $questionSet->update(['status' => ApplicationQuestionSetStatus::Draft]);
            $this->notifyProviderFrozen($frozenUntil);

            return;
        }

        try {
            $response = $agent->prompt($prompt, provider: $agent->providers() ?: null);

            foreach ($response['answers'] as $answer) {
                $index = $answer['question_index'];
                if (isset($questionRecords[$index])) {
                    $questionRecords[$index]->update([
                        'feedback' => $answer['feedback'],
                        'grammar_corrections' => $answer['grammar_corrections'],
                        'suggested_answer' => $answer['suggested_answer'],
                    ]);
                }
            }

            $questionSet->update(['status' => ApplicationQuestionSetStatus::Reviewed]);
            $questionSet->load('questions');

            $this->loadQuestionSet($questionSet);

            Notification::make()
                ->title('Review complete')
                ->success()
                ->send();
        } catch (AiException $e) {
            $questionSet->update(['status' => ApplicationQuestionSetStatus::Draft]);

            if ($until = ProviderFreeze::freezeIfUsageLimited($provider, $e)) {
                $this->notifyProviderFrozen($until);

                return;
            }

            Log::error('Application questions review failed', ['exception' => $e]);

            Notification::make()
                ->title('Review failed')
                ->body('An error occurred during review. Please try again.')
                ->danger()
                ->send();
        } catch (\Exception $e) {
            Log::error('Application questions review failed', ['exception' => $e]);

            $questionSet->update(['status' => ApplicationQuestionSetStatus::Draft]);

            Notification::make()
                ->title('Review failed')
                ->body('An error occurred during review. Please try again.')
                ->danger()
                ->send();
        }
    }

    private function notifyProviderFrozen(CarbonImmutable $until): void
    {
        Notification::make()
            ->title('AI provider temporarily unavailable')
            ->body("Service is unavailable until {$until->toDayDateTimeString()} UTC. Please try again later.")
            ->danger()
            ->send();
    }

    public function saveFinalAnswers(): void
    {
        $questionSet = ApplicationQuestionSet::findOrFail($this->questionSetId);

        foreach ($this->results as $result) {
            ApplicationQuestion::where('id', $result['id'])
                ->update(['final_answer' => $result['suggested_answer']]);
        }

        $questionSet->update(['status' => ApplicationQuestionSetStatus::Finalized]);

        Notification::make()
            ->title('Answers saved')
            ->success()
            ->send();
    }

    public function resubmit(): void
    {
        $questionSet = ApplicationQuestionSet::findOrFail($this->questionSetId);

        $questions = [];
        foreach ($this->results as $result) {
            $questions[] = [
                'question' => $result['question'],
                'answer' => $result['suggested_answer'],
            ];
        }

        $this->showResults = false;
        $this->results = [];

        $this->form->fill([
            'listing_id' => $questionSet->listing_id,
            'target_profile_id' => $questionSet->target_profile_id,
            'questions' => $questions,
        ]);

        $this->submitForReview();
    }

    public function resetForm(): void
    {
        $this->questionSetId = null;
        $this->showResults = false;
        $this->results = [];

        /** @var User $user */
        $user = auth()->user();

        $this->form->fill([
            'listing_id' => null,
            'target_profile_id' => $user->activeTargets()->first()?->id,
            'questions' => self::EMPTY_QUESTIONS,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reset')
                ->label('New')
                ->icon(Heroicon::OutlinedPlusCircle)
                ->action('resetForm')
                ->visible($this->showResults),
        ];
    }

    private function loadQuestionSet(ApplicationQuestionSet $questionSet): void
    {
        $hasReviews = $questionSet->questions->contains(fn (ApplicationQuestion $q) => $q->hasBeenReviewed());

        if ($hasReviews) {
            $this->showResults = true;
            $this->results = $questionSet->questions->map(fn (ApplicationQuestion $q) => [
                'id' => $q->id,
                'question' => $q->question,
                'answer' => $q->answer,
                'feedback' => $q->feedback,
                'grammar_corrections' => $q->grammar_corrections,
                'suggested_answer' => $q->final_answer ?? $q->suggested_answer,
            ])->all();

            $this->form->fill([
                'listing_id' => $questionSet->listing_id,
                'target_profile_id' => $questionSet->target_profile_id,
                'questions' => [],
            ]);
        } else {
            $this->form->fill([
                'listing_id' => $questionSet->listing_id,
                'target_profile_id' => $questionSet->target_profile_id,
                'questions' => $questionSet->questions->map(fn (ApplicationQuestion $q) => [
                    'question' => $q->question,
                    'answer' => $q->answer,
                ])->all(),
            ]);
        }
    }

    /**
     * @param  array<int, array<string, string>>  $questions
     */
    private function buildAgentPrompt(array $questions, ?string $listingId): string
    {
        $lines = [];

        $listing = $listingId ? Listing::find($listingId) : null;

        if ($listing) {
            $listingJson = json_encode($listing->toAgentPayload(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
            $lines[] = "Review my answers to application questions for this job posting:\n```json\n{$listingJson}\n```";
        } else {
            $lines[] = 'Review my answers to the following application questions.';
        }

        $lines[] = '';

        foreach ($questions as $index => $q) {
            $lines[] = "Question {$index}: {$q['question']}";
            $lines[] = "My Answer: {$q['answer']}";
            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
