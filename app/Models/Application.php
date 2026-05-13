<?php

namespace App\Models;

use App\ApplicationStatus;
use App\Jobs\GenerateCoverLetter;
use App\Jobs\GenerateResume;
use App\Jobs\MarkApplicationFailed;
use App\Jobs\MarkApplicationReady;
use Database\Factories\ApplicationFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Bus;

/**
 * @property User $user
 * @property TargetProfile $targetProfile
 */
class Application extends Model
{
    /** @use HasFactory<ApplicationFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'listing_id',
        'user_id',
        'target_profile_id',
        'status',
        'resume_path',
        'cover_letter_path',
        'applied_at',
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

    public static function generateResume(Listing $listing, User $user, TargetProfile $target): static
    {
        return static::dispatchGeneration($listing, $user, $target, fn ($app) => [
            new GenerateResume($app),
        ]);
    }

    public static function generateCoverLetter(Listing $listing, User $user, TargetProfile $target): static
    {
        return static::dispatchGeneration($listing, $user, $target, fn ($app) => [
            new GenerateCoverLetter($app),
        ]);
    }

    public static function generateBoth(Listing $listing, User $user, TargetProfile $target): static
    {
        return static::dispatchGeneration($listing, $user, $target, fn ($app) => [
            new GenerateResume($app),
            new GenerateCoverLetter($app),
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'applied_at' => 'datetime',
        ];
    }

    /**
     * @param  callable(self): array<ShouldQueue>  $jobs
     */
    protected static function dispatchGeneration(Listing $listing, User $user, TargetProfile $target, callable $jobs): static
    {
        /** @var static $application */
        $application = static::firstOrCreate(
            [
                'listing_id' => $listing->id,
                'user_id' => $user->id,
                'target_profile_id' => $target->id,
            ],
            ['status' => ApplicationStatus::Generating],
        );

        Bus::batch($jobs($application))
            ->then(new MarkApplicationReady($application))
            ->catch(new MarkApplicationFailed($application))
            ->dispatch();

        return $application;
    }
}
