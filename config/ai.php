<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider Names
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the AI providers below should be the
    | default for AI operations when no explicit provider is provided
    | for the operation. This should be any provider defined below.
    |
    */

    'default' => env('AI_PROVIDER', 'anthropic'),
    'default_for_images' => 'gemini',
    'default_for_audio' => 'openai',
    'default_for_transcription' => 'openai',
    'default_for_embeddings' => 'openai',
    'default_for_reranking' => 'cohere',

    /*
    |--------------------------------------------------------------------------
    | Per-Agent Provider & Model
    |--------------------------------------------------------------------------
    |
    | Each agent role resolves its provider and model at runtime from the
    | values below. Per-role env vars override; provider falls back to
    | the global AI_PROVIDER; model falls back to the per-role default.
    |
    */

    'agents' => [
        'scorer' => [
            'provider' => env('AI_SCORER_PROVIDER', env('AI_PROVIDER', 'anthropic')),
            'model' => env('AI_SCORER_MODEL', 'claude-haiku-4-5-20251001'),
        ],
        'resume_tailor' => [
            'provider' => env('AI_RESUME_TAILOR_PROVIDER', env('AI_PROVIDER', 'anthropic')),
            'model' => env('AI_RESUME_TAILOR_MODEL', 'claude-sonnet-4-6'),
        ],
        'cover_letter' => [
            'provider' => env('AI_COVER_LETTER_PROVIDER', env('AI_PROVIDER', 'anthropic')),
            'model' => env('AI_COVER_LETTER_MODEL', 'claude-sonnet-4-6'),
        ],
        'app_questions' => [
            'provider' => env('AI_APP_QUESTIONS_PROVIDER', env('AI_PROVIDER', 'anthropic')),
            'model' => env('AI_APP_QUESTIONS_MODEL', 'claude-sonnet-4-6'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pricing (USD per million tokens)
    |--------------------------------------------------------------------------
    |
    | Keyed by [provider][model]. Each provider/model pair carries its own
    | prices since they differ across providers (Anthropic Sonnet pricing
    | is not the same as OpenRouter Sonnet pricing). Include both alias
    | and dated model names so the value returned by each API resolves.
    |
    */

    'pricing' => [
        'anthropic' => [
            'claude-sonnet-4-6' => ['input' => 3.00, 'output' => 15.00, 'cacheWrite' => 3.75, 'cacheRead' => 0.30],
            'claude-sonnet-4-6-20260217' => ['input' => 3.00, 'output' => 15.00, 'cacheWrite' => 3.75, 'cacheRead' => 0.30],
            'claude-haiku-4-5' => ['input' => 1.00, 'output' => 5.00, 'cacheWrite' => 1.25, 'cacheRead' => 0.10],
            'claude-haiku-4-5-20251001' => ['input' => 1.00, 'output' => 5.00, 'cacheWrite' => 1.25, 'cacheRead' => 0.10],
        ],
        'openrouter' => [
            'anthropic/claude-sonnet-4-6' => ['input' => 3.00, 'output' => 15.00, 'cacheWrite' => 3.75, 'cacheRead' => 0.30],
            'anthropic/claude-4.6-sonnet-20260217' => ['input' => 3.00, 'output' => 15.00, 'cacheWrite' => 3.75, 'cacheRead' => 0.30],
            'anthropic/claude-haiku-4-5' => ['input' => 1.00, 'output' => 5.00, 'cacheWrite' => 1.25, 'cacheRead' => 0.10],
            'anthropic/claude-4.5-haiku-20251001' => ['input' => 1.00, 'output' => 5.00, 'cacheWrite' => 1.25, 'cacheRead' => 0.10],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Below you may configure caching strategies for AI related operations
    | such as embedding generation. You are free to adjust these values
    | based on your application's available caching stores and needs.
    |
    */

    'caching' => [
        'embeddings' => [
            'cache' => false,
            'store' => env('CACHE_STORE', 'database'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    |
    | Below are each of your AI providers defined for this application. Each
    | represents an AI provider and API key combination which can be used
    | to perform tasks like text, image, and audio creation via agents.
    |
    */

    'providers' => [
        'anthropic' => [
            'driver' => 'anthropic',
            'key' => env('ANTHROPIC_API_KEY'),
        ],

        'azure' => [
            'driver' => 'azure',
            'key' => env('AZURE_OPENAI_API_KEY'),
            'url' => env('AZURE_OPENAI_URL'),
            'api_version' => env('AZURE_OPENAI_API_VERSION', '2024-10-21'),
            'deployment' => env('AZURE_OPENAI_DEPLOYMENT', 'gpt-4o'),
            'embedding_deployment' => env('AZURE_OPENAI_EMBEDDING_DEPLOYMENT', 'text-embedding-3-small'),
        ],

        'cohere' => [
            'driver' => 'cohere',
            'key' => env('COHERE_API_KEY'),
        ],

        'deepseek' => [
            'driver' => 'deepseek',
            'key' => env('DEEPSEEK_API_KEY'),
        ],

        'eleven' => [
            'driver' => 'eleven',
            'key' => env('ELEVENLABS_API_KEY'),
        ],

        'gemini' => [
            'driver' => 'gemini',
            'key' => env('GEMINI_API_KEY'),
        ],

        'groq' => [
            'driver' => 'groq',
            'key' => env('GROQ_API_KEY'),
        ],

        'jina' => [
            'driver' => 'jina',
            'key' => env('JINA_API_KEY'),
        ],

        'mistral' => [
            'driver' => 'mistral',
            'key' => env('MISTRAL_API_KEY'),
        ],

        'ollama' => [
            'driver' => 'ollama',
            'key' => env('OLLAMA_API_KEY', ''),
            'url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
        ],

        'openai' => [
            'driver' => 'openai',
            'key' => env('OPENAI_API_KEY'),
        ],

        'openrouter' => [
            'driver' => 'openrouter',
            'key' => env('OPENROUTER_API_KEY'),
        ],

        'voyageai' => [
            'driver' => 'voyageai',
            'key' => env('VOYAGEAI_API_KEY'),
        ],

        'xai' => [
            'driver' => 'xai',
            'key' => env('XAI_API_KEY'),
        ],
    ],

];
