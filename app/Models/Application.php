<?php

namespace App\Models;

use App\ApplicationStatus;
use App\Jobs\GenerateCoverLetter;
use App\Jobs\GenerateResume;
use Database\Factories\ApplicationFactory;
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

    public static function generate(Listing $listing): static
    {
        $application = static::create([
            'listing_id' => $listing->id,
            'status' => ApplicationStatus::Generating,
        ]);

        Bus::batch([
            new GenerateResume($application),
            new GenerateCoverLetter($application),
        ])->then(function () use ($application) {
            $application->update(['status' => ApplicationStatus::Ready]);
        })->catch(function () use ($application) {
            $application->update(['status' => ApplicationStatus::Failed]);
        })->dispatch();

        return $application;
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
}
