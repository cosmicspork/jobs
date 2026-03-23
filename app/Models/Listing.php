<?php

namespace App\Models;

use Database\Factories\ListingFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Listing extends Model
{
    /** @use HasFactory<ListingFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'title',
        'company',
        'url',
        'description',
        'salary_min',
        'salary_max',
        'remote',
        'board',
        'raw_data',
        'score',
        'score_data',
        'scored_at',
        'scraped_at',
        'read_at',
    ];

    /**
     * @return HasMany<Application, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'raw_data' => 'array',
            'score_data' => 'array',
            'remote' => 'boolean',
            'scored_at' => 'datetime',
            'scraped_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }
}
