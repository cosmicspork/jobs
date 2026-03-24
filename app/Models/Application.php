<?php

namespace App\Models;

use App\ApplicationStatus;
use App\Jobs\GenerateCoverLetter;
use App\Jobs\GenerateResume;
use Database\Factories\ApplicationFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Bus;

class Application extends Model
{
    /** @use HasFactory<ApplicationFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'listing_id',
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

    public static function generateResume(Listing $listing): static
    {
        return static::dispatchGeneration($listing, fn ($app) => [
            new GenerateResume($app),
        ]);
    }

    public static function generateCoverLetter(Listing $listing): static
    {
        return static::dispatchGeneration($listing, fn ($app) => [
            new GenerateCoverLetter($app),
        ]);
    }

    public static function generateBoth(Listing $listing): static
    {
        return static::dispatchGeneration($listing, fn ($app) => [
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
     * @param  callable(static): ShouldQueue[]  $jobs
     */
    private static function dispatchGeneration(Listing $listing, callable $jobs): static
    {
        $application = static::firstOrCreate(
            ['listing_id' => $listing->id],
            ['status' => ApplicationStatus::Generating],
        );

        Bus::batch($jobs($application))
            ->then(fn () => $application->update(['status' => ApplicationStatus::Ready]))
            ->catch(fn () => $application->update(['status' => ApplicationStatus::Failed]))
            ->dispatch();

        return $application;
    }
}
