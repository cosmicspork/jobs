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
];
