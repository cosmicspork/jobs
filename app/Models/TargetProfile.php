<?php

namespace App\Models;

use Database\Factories\TargetProfileFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property int $user_id
 * @property string $name
 * @property string|null $positioning
 * @property array<int, string>|null $target_titles
 * @property array<string, mixed>|null $criteria
 * @property bool $is_active
 * @property int $sort_order
 */
class TargetProfile extends Model
{
    /** @use HasFactory<TargetProfileFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'name',
        'positioning',
        'target_titles',
        'criteria',
        'is_active',
        'sort_order',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ListingUser, $this>
     */
    public function listingUsers(): HasMany
    {
        return $this->hasMany(ListingUser::class);
    }

    /**
     * @return HasMany<Application, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function criterion(string $key, mixed $default = null): mixed
    {
        return $this->criteria[$key] ?? $default;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_titles' => 'array',
            'criteria' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
