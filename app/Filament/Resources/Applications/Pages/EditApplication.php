<?php

namespace App\Filament\Resources\Applications\Pages;

use App\ApplicationStatus;
use App\Filament\Resources\Applications\ApplicationResource;
use App\Jobs\GenerateCoverLetter;
use App\Jobs\GenerateResume;
use App\Models\Application;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditApplication extends EditRecord
{
    protected static string $resource = ApplicationResource::class;

    public function getTitle(): string
    {
        /** @var Application $record */
        $record = $this->record;

        return "Application — {$record->listing?->title} @ {$record->listing?->company}";
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->regenerateAction('regenerateResume', 'Regenerate resume', GenerateResume::class, 'resume'),
            $this->regenerateAction('regenerateCoverLetter', 'Regenerate cover letter', GenerateCoverLetter::class, 'cover letter'),
            Action::make('printResume')
                ->label('Print resume')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn (Application $record): string => route('applications.print.resume', $record))
                ->openUrlInNewTab()
                ->visible(fn (Application $record): bool => filled($record->resume_content)),
            Action::make('printCoverLetter')
                ->label('Print cover letter')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn (Application $record): string => route('applications.print.cover-letter', $record))
                ->openUrlInNewTab()
                ->visible(fn (Application $record): bool => filled($record->cover_letter_content)),
            DeleteAction::make(),
        ];
    }

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

                $jobClass::dispatch($record);

                Notification::make()
                    ->title("Regenerating {$subject}")
                    ->body('The AI is reworking this section. Refresh in a moment to see the result.')
                    ->success()
                    ->send();
            });
    }
}
