<?php

namespace App\Models;

use App\ApplicationQuestionSetStatus;
use Database\Factories\ApplicationQuestionSetFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApplicationQuestionSet extends Model
{
    /** @use HasFactory<ApplicationQuestionSetFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'listing_id',
        'user_id',
        'target_profile_id',
        'status',
    ];

    /**
     * @return BelongsTo<Listing, $this>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<TargetProfile, $this>
     */
    public function targetProfile(): BelongsTo
    {
        return $this->belongsTo(TargetProfile::class);
    }

    /**
     * @return HasMany<ApplicationQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(ApplicationQuestion::class, 'question_set_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApplicationQuestionSetStatus::class,
        ];
    }
}
