<?php

namespace App;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ApplicationStatus: string implements HasColor, HasLabel
{
    case Generating = 'generating';
    case Ready = 'ready';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Generating => 'Generating',
            self::Ready => 'Ready',
            self::Failed => 'Failed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Generating => 'warning',
            self::Ready => 'success',
            self::Failed => 'danger',
        };
    }
}
