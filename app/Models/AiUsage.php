<?php

namespace App\Models;

use Database\Factories\AiUsageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiUsage extends Model
{
    /** @use HasFactory<AiUsageFactory> */
    use HasFactory;

    /**
     * OpenRouter pricing per million tokens.
     * Keyed by both alias and full versioned model names.
     *
     * @var array<string, array{input: float, output: float, cacheWrite: float, cacheRead: float}>
     */
    private const SONNET_PRICING = ['input' => 3.00, 'output' => 15.00, 'cacheWrite' => 3.75, 'cacheRead' => 0.30];

    private const HAIKU_PRICING = ['input' => 0.80, 'output' => 4.00, 'cacheWrite' => 1.00, 'cacheRead' => 0.08];

    public const PRICING = [
        'anthropic/claude-sonnet-4-6' => self::SONNET_PRICING,
        'anthropic/claude-4.6-sonnet-20260217' => self::SONNET_PRICING,
        'anthropic/claude-haiku-4-5' => self::HAIKU_PRICING,
        'anthropic/claude-4.5-haiku-20251001' => self::HAIKU_PRICING,
    ];

    protected $fillable = [
        'agent',
        'provider',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'cache_write_tokens',
        'cache_read_tokens',
        'reasoning_tokens',
        'cost',
    ];

    public function totalTokens(): int
    {
        return $this->prompt_tokens + $this->completion_tokens;
    }

    public static function shortModelName(string $model): string
    {
        return str_replace('anthropic/', '', $model);
    }

    public static function formatTokens(int $tokens): string
    {
        if ($tokens >= 1_000_000) {
            return number_format($tokens / 1_000_000, 2).'M';
        }

        if ($tokens >= 1_000) {
            return number_format($tokens / 1_000, 1).'K';
        }

        return (string) $tokens;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'cache_write_tokens' => 'integer',
            'cache_read_tokens' => 'integer',
            'reasoning_tokens' => 'integer',
            'cost' => 'decimal:6',
        ];
    }
}
