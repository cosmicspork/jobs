<?php

namespace App\Filament\Resources\Applications\Schemas;

use App\Filament\Pages\ApplicationQuestions;
use App\Models\Application;
use App\Models\ApplicationQuestionSet;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Workspace form for the Application resource — Resume, Cover letter,
 * Application questions, and Notes are all sections of this single page.
 * Resume and cover-letter content are bound to the `resume_content` and
 * `cover_letter_content` JSON columns; Application questions stay in
 * their own model (ApplicationQuestionSet) and are reached via the
 * dedicated page from the Questions tab.
 */
class ApplicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Workspace')
                    ->tabs([
                        Tab::make('Resume')
                            ->icon(Heroicon::OutlinedDocumentText)
                            ->schema(self::resumeSchema()),
                        Tab::make('Cover Letter')
                            ->icon(Heroicon::OutlinedEnvelope)
                            ->schema(self::coverLetterSchema()),
                        Tab::make('Questions')
                            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                            ->schema(self::questionsSchema()),
                        Tab::make('Notes')
                            ->icon(Heroicon::OutlinedPencilSquare)
                            ->schema(self::notesSchema()),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<int, Component>
     */
    private static function resumeSchema(): array
    {
        return [
            Section::make('Summary')
                ->afterHeader([
                    Action::make('printResume')
                        ->label('Print')
                        ->icon(Heroicon::OutlinedPrinter)
                        ->color('gray')
                        ->url(fn (Application $record): string => route('applications.print.resume', $record))
                        ->openUrlInNewTab()
                        ->visible(fn (Application $record): bool => filled($record->resume_content)),
                ])
                ->schema([
                    Textarea::make('resume_content.summary')
                        ->label('Professional summary')
                        ->rows(4)
                        ->placeholder('Generate or paste the candidate summary…'),
                ]),
            Section::make('Skills')
                ->schema([
                    TagsInput::make('resume_content.skills')
                        ->label('Skills')
                        ->placeholder('Add a skill and press enter'),
                ]),
            Section::make('Experience')
                ->schema([
                    Repeater::make('resume_content.experience')
                        ->label('Roles')
                        ->schema([
                            TextInput::make('role')->required(),
                            TextInput::make('company')->required(),
                            TextInput::make('period')->placeholder('2023 - Present'),
                            Repeater::make('highlights')
                                ->label('Bullets')
                                ->simple(TextInput::make('highlight')->required())
                                ->addActionLabel('Add bullet')
                                ->reorderable()
                                ->collapsible()
                                ->columnSpanFull(),
                        ])
                        ->columns(3)
                        ->addActionLabel('Add role')
                        ->collapsible()
                        ->reorderable()
                        ->itemLabel(fn (array $state): ?string => trim(($state['role'] ?? '').' — '.($state['company'] ?? '')) ?: null),
                ]),
            Section::make('Education')
                ->schema([
                    Repeater::make('resume_content.education')
                        ->label('Entries')
                        ->schema([
                            TextInput::make('qualification')->required(),
                            TextInput::make('institution')->required(),
                            TextInput::make('field_of_study'),
                            TextInput::make('period')->required()->placeholder('2014 - 2018'),
                            Repeater::make('highlights')
                                ->label('Bullets')
                                ->simple(TextInput::make('highlight')->required())
                                ->addActionLabel('Add bullet')
                                ->reorderable()
                                ->collapsible()
                                ->columnSpanFull(),
                        ])
                        ->columns(4)
                        ->addActionLabel('Add education entry')
                        ->collapsible()
                        ->reorderable()
                        ->itemLabel(fn (array $state): ?string => trim(($state['qualification'] ?? '').' — '.($state['institution'] ?? '')) ?: null),
                ]),
        ];
    }

    /**
     * @return array<int, Component>
     */
    private static function coverLetterSchema(): array
    {
        return [
            Section::make('Cover letter')
                ->afterHeader([
                    Action::make('printCoverLetter')
                        ->label('Print')
                        ->icon(Heroicon::OutlinedPrinter)
                        ->color('gray')
                        ->url(fn (Application $record): string => route('applications.print.cover-letter', $record))
                        ->openUrlInNewTab()
                        ->visible(fn (Application $record): bool => filled($record->cover_letter_content)),
                ])
                ->schema([
                    TextInput::make('cover_letter_content.subject_line')
                        ->label('Subject line')
                        ->placeholder('Senior Engineer — Acme Corp'),
                    Textarea::make('cover_letter_content.body')
                        ->label('Body')
                        ->rows(18)
                        ->placeholder('Generate or write the cover letter body. Paragraphs are separated by a blank line.'),
                ]),
        ];
    }

    /**
     * @return array<int, Component>
     */
    private static function questionsSchema(): array
    {
        return [
            Section::make('Application questions')
                ->description('Review and refine answers to structured application questions for this listing. Status is preserved across visits.')
                ->afterHeader([
                    Action::make('openQuestions')
                        ->label(fn (Application $record): string => self::latestQuestionSet($record) ? 'Open question set' : 'Start question set')
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->color('primary')
                        ->url(fn (Application $record): string => ApplicationQuestions::getUrl(['listing' => $record->listing_id])),
                ])
                ->schema([
                    TextEntry::make('latestQuestionSetStatus')
                        ->label('Latest status')
                        ->state(fn (Application $record) => self::latestQuestionSet($record)?->status->value ?? 'No questions yet')
                        ->badge(),
                ]),
        ];
    }

    /**
     * @return array<int, Component>
     */
    private static function notesSchema(): array
    {
        return [
            Section::make('Notes')
                ->description('Private scratchpad for this application — not sent to the AI.')
                ->schema([
                    Textarea::make('notes')
                        ->hiddenLabel()
                        ->rows(12)
                        ->placeholder('Anything you want to remember about this application…'),
                ]),
        ];
    }

    private static function latestQuestionSet(Application $application): ?ApplicationQuestionSet
    {
        return ApplicationQuestionSet::query()
            ->where('listing_id', $application->listing_id)
            ->where('user_id', $application->user_id)
            ->latest()
            ->first();
    }
}
