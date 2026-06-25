<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Monthly AI spend cap (USD) per user
    |--------------------------------------------------------------------------
    |
    | Soft cap enforced at scoring-dispatch time. When a user's AI spend for
    | the current calendar month meets or exceeds this value, their listings
    | stop being queued for AI scoring until the next month. An alert email
    | goes to the admin address the first time each user hits the cap.
    |
    */

    'monthly_cap_usd' => (float) env('AI_MONTHLY_CAP_USD', 5.0),

    'admin_alert_email' => env('ADMIN_ALERT_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Daily digest relevant-listing cap
    |--------------------------------------------------------------------------
    |
    | The digest surfaces only "relevant" listings, ranked by fit_score then
    | recency, and is capped at this many per email to keep the inbox to a
    | handful of high-quality matches.
    |
    */

    'digest_relevant_cap' => (int) env('DIGEST_RELEVANT_CAP', 10),

    /*
    |--------------------------------------------------------------------------
    | Reclassify core-keyword fallback
    |--------------------------------------------------------------------------
    |
    | Optional global fallback "core" keywords used by `jobs:reclassify` Pass B
    | when a target profile has no must_have_keywords of its own. Comma-
    | separated. Leave empty to make Pass B a no-op for such targets.
    |
    */

    'core_keywords' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SCORING_CORE_KEYWORDS', '')),
    ))),
];
