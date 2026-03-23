<?php

namespace App;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Relevance: string implements HasColor, HasLabel
{
    case Relevant = 'relevant';
    case Maybe = 'maybe';
    case Irrelevant = 'irrelevant';

    public function getLabel(): string
    {
        return match ($this) {
            self::Relevant => 'Relevant',
            self::Maybe => 'Maybe',
            self::Irrelevant => 'Irrelevant',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Relevant => 'success',
            self::Maybe => 'warning',
            self::Irrelevant => 'danger',
        };
    }
}
