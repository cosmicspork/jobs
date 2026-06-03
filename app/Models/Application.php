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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;

/**
 * @property User $user
 * @property TargetProfile $targetProfile
 * @property ApplicationStatus|null $status
 * @property Carbon|null $applied_at
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
        'resume_content',
        'cover_letter_content',
        'extra_instructions',
        'notes',
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

    public static function generateResume(Listing $listing, User $user, TargetProfile $target, ?string $extraInstructions = null): static
    {
        return static::dispatchGeneration($listing, $user, $target, $extraInstructions, fn ($app) => [
            new GenerateResume($app),
        ]);
    }

    public static function generateCoverLetter(Listing $listing, User $user, TargetProfile $target, ?string $extraInstructions = null): static
    {
        return static::dispatchGeneration($listing, $user, $target, $extraInstructions, fn ($app) => [
            new GenerateCoverLetter($app),
        ]);
    }

    public static function generateBoth(Listing $listing, User $user, TargetProfile $target, ?string $extraInstructions = null): static
    {
        return static::dispatchGeneration($listing, $user, $target, $extraInstructions, fn ($app) => [
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
            'resume_content' => 'array',
            'cover_letter_content' => 'array',
        ];
    }

    /**
     * @param  callable(self): array<ShouldQueue>  $jobs
     */
    protected static function dispatchGeneration(Listing $listing, User $user, TargetProfile $target, ?string $extraInstructions, callable $jobs): static
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

        // The extra-instructions field is non-destructive: caller passed
        // null → keep whatever was there. Caller passed a string → overwrite.
        // Empty string from a UI textarea is treated as "clear".
        $updates = ['status' => ApplicationStatus::Generating];

        if ($extraInstructions !== null) {
            $updates['extra_instructions'] = $extraInstructions !== '' ? $extraInstructions : null;
        }

        $application->update($updates);

        $application->dispatchGenerationBatch($jobs($application));

        return $application;
    }

    /**
     * Dispatch generation jobs as a batch whose terminal callbacks settle this
     * application's status: Ready on success, Failed on error. Every entry
     * point — initial create and single-section regenerate from the workspace —
     * MUST route through here, otherwise the application is left stranded in
     * the Generating state once the jobs finish.
     *
     * @param  array<ShouldQueue>  $jobs
     */
    public function dispatchGenerationBatch(array $jobs): void
    {
        Bus::batch($jobs)
            ->then(new MarkApplicationReady($this))
            ->catch(new MarkApplicationFailed($this))
            ->dispatch();
    }
}
