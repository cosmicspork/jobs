<?php

namespace App\Models;

use Database\Factories\ApplicationQuestionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationQuestion extends Model
{
    /** @use HasFactory<ApplicationQuestionFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'question_set_id',
        'question',
        'answer',
        'feedback',
        'grammar_corrections',
        'suggested_answer',
        'final_answer',
    ];

    /**
     * @return BelongsTo<ApplicationQuestionSet, $this>
     */
    public function questionSet(): BelongsTo
    {
        return $this->belongsTo(ApplicationQuestionSet::class, 'question_set_id');
    }

    public function hasBeenReviewed(): bool
    {
        return $this->feedback !== null;
    }
}
