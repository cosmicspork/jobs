<?php

namespace App\Filament\Pages;

use App\Ai\Agents\ApplicationQuestionsAgent;
use App\ApplicationQuestionSetStatus;
use App\Models\ApplicationQuestion;
use App\Models\ApplicationQuestionSet;
use App\Models\Listing;
use BackedEnum;
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

        $this->form->fill([
            'listing_id' => $listingId,
            'questions' => self::EMPTY_QUESTIONS,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
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
        $questions = $state['questions'];

        $questionSet = $this->questionSetId
            ? ApplicationQuestionSet::findOrFail($this->questionSetId)
            : ApplicationQuestionSet::create([
                'listing_id' => $listingId,
                'user_id' => auth()->id(),
                'status' => ApplicationQuestionSetStatus::Reviewing,
            ]);

        $questionSet->update(['status' => ApplicationQuestionSetStatus::Reviewing]);

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

        try {
            $response = (new ApplicationQuestionsAgent(auth()->user()))->prompt($prompt);

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
            'questions' => $questions,
        ]);

        $this->submitForReview();
    }

    public function resetForm(): void
    {
        $this->questionSetId = null;
        $this->showResults = false;
        $this->results = [];

        $this->form->fill([
            'listing_id' => null,
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
                'questions' => [],
            ]);
        } else {
            $this->form->fill([
                'listing_id' => $questionSet->listing_id,
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

        if ($listingId) {
            $lines[] = "Review my answers to application questions for listing_id: {$listingId}.";
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
