<?php

namespace App;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * What happened after applying. Distinct from ApplicationStatus, which tracks
 * AI artifact generation and says nothing about the application itself.
 *
 * Null is meaningful: applied, but nothing recorded yet — which is not the same
 * as Ghosted, where the user has decided nothing is coming back.
 */
enum ApplicationOutcome: string implements HasColor, HasLabel
{
    case Screening = 'screening';
    case Interviewing = 'interviewing';
    case Offer = 'offer';
    case Rejected = 'rejected';
    case Ghosted = 'ghosted';
    case Withdrawn = 'withdrawn';

    public function getLabel(): string
    {
        return match ($this) {
            self::Screening => 'Screening',
            self::Interviewing => 'Interviewing',
            self::Offer => 'Offer',
            self::Rejected => 'Rejected',
            self::Ghosted => 'Ghosted',
            self::Withdrawn => 'Withdrawn',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Screening => 'info',
            self::Interviewing => 'warning',
            self::Offer => 'success',
            self::Rejected => 'danger',
            self::Ghosted, self::Withdrawn => 'gray',
        };
    }
}
