<?php

namespace App\Filament\Resources\Applications\Pages;

use App\ApplicationStatus;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Jobs\GenerateCoverLetter;
use App\Jobs\GenerateResume;
use App\Models\Application;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class EditApplication extends EditRecord
{
    protected static string $resource = ApplicationResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var Application $record */
        $record = $this->record;

        $listing = $record->listing;

        if ($listing === null) {
            return 'Application';
        }

        $url = route('filament.admin.resources.listings.view', $listing);
        $label = e("{$listing->title} @ {$listing->company}");

        return new HtmlString(
            'Application — <a href="'.e($url).'" class="text-primary-600 hover:underline dark:text-primary-400">'.$label.'</a>'
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                $this->regenerateAction('regenerateResume', 'Regenerate resume', GenerateResume::class, 'resume'),
                $this->regenerateAction('regenerateCoverLetter', 'Regenerate cover letter', GenerateCoverLetter::class, 'cover letter'),
            ])
                ->label('Regenerate')
                ->icon(Heroicon::OutlinedArrowPath)
                ->button()
                ->color('primary'),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  class-string<ShouldQueue>  $jobClass
     */
    private function regenerateAction(string $name, string $label, string $jobClass, string $subject): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('primary')
            ->schema([
                Textarea::make('extra_instructions')
                    ->label('Anything else the AI should know?')
                    ->placeholder('Optional — e.g. "lead with the queue-layer work, drop early-career roles"')
                    ->rows(4)
                    ->default(fn (Application $record): ?string => $record->extra_instructions),
            ])
            ->action(function (array $data) use ($jobClass, $subject): void {
                /** @var Application $record */
                $record = $this->record;

                $record->update([
                    'extra_instructions' => filled($data['extra_instructions'] ?? null) ? $data['extra_instructions'] : null,
                    'status' => ApplicationStatus::Generating,
                ]);

                $record->dispatchGenerationBatch([new $jobClass($record)]);

                Notification::make()
                    ->title("Regenerating {$subject}")
                    ->body('The AI is reworking this section. Refresh in a moment to see the result.')
                    ->success()
                    ->send();
            });
    }
}
