<?php

namespace App;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ApplicationQuestionSetStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Reviewing = 'reviewing';
    case Reviewed = 'reviewed';
    case Finalized = 'finalized';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Reviewing => 'Reviewing',
            self::Reviewed => 'Reviewed',
            self::Finalized => 'Finalized',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Reviewing => 'warning',
            self::Reviewed => 'info',
            self::Finalized => 'success',
        };
    }
}
