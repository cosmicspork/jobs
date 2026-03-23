<?php

namespace App;

enum Relevance: string
{
    case Relevant = 'relevant';
    case Maybe = 'maybe';
    case Irrelevant = 'irrelevant';

    public function label(): string
    {
        return match ($this) {
            self::Relevant => 'Relevant',
            self::Maybe => 'Maybe',
            self::Irrelevant => 'Irrelevant',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Relevant => 'success',
            self::Maybe => 'warning',
            self::Irrelevant => 'danger',
        };
    }
}
