<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $monthStart->format('F Y') }} usage report</title>
    <style>
        body { font-family: -apple-system, sans-serif; font-size: 14px; line-height: 1.6; color: #1a1a1a; background: #f5f5f5; margin: 0; padding: 0; }
        .wrapper { max-width: 640px; margin: 0 auto; background: #fff; }
        .header { background: #111827; color: #fff; padding: 24px 32px; }
        .header h1 { font-size: 22px; margin: 0; font-weight: 600; }
        .header p { color: #9ca3af; font-size: 13px; margin: 4px 0 0; }
        .section { padding: 24px 32px; border-bottom: 1px solid #e5e7eb; }
        .stats-grid { display: flex; flex-wrap: wrap; gap: 12px; }
        .stat-card { flex: 1; min-width: 140px; background: #f9fafb; border-radius: 8px; padding: 14px; text-align: center; }
        .stat-value { font-size: 24px; font-weight: 700; color: #111827; }
        .stat-label { font-size: 12px; color: #6b7280; text-transform: uppercase; margin-top: 4px; }
        .footer { padding: 16px 32px; color: #6b7280; font-size: 12px; text-align: center; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>Your {{ $monthStart->format('F Y') }} report</h1>
        <p>Hi {{ $user->name }} — here's how your job hunt looked last month.</p>
    </div>

    <div class="section">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value">{{ number_format($stats['listings_received']) }}</div>
                <div class="stat-label">Listings Received</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #166534;">{{ number_format($stats['relevant']) }}</div>
                <div class="stat-label">Relevant</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #854d0e;">{{ number_format($stats['maybe']) }}</div>
                <div class="stat-label">Maybe</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #991b1b;">{{ number_format($stats['irrelevant']) }}</div>
                <div class="stat-label">Irrelevant</div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value">{{ number_format($stats['applications']) }}</div>
                <div class="stat-label">Applications Sent</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">${{ number_format($stats['ai_cost'], 2) }}</div>
                <div class="stat-label">AI Spend</div>
            </div>
        </div>
    </div>

    <div class="footer">
        {{ config('app.name') }} &middot; {{ config('app.url') }}
    </div>
</div>
</body>
</html>
