<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Job Digest</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, 'Helvetica Neue', Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #1a1a1a; background: #f5f5f5; }
        .wrapper { max-width: 640px; margin: 0 auto; background: #ffffff; }
        .header { background: #111827; color: #ffffff; padding: 24px 32px; }
        .header h1 { font-size: 22px; font-weight: 600; }
        .header p { color: #9ca3af; font-size: 13px; margin-top: 4px; }
        .section { padding: 24px 32px; border-bottom: 1px solid #e5e7eb; }
        .section-title { font-size: 16px; font-weight: 600; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
        .badge { display: inline-block; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 9999px; }
        .badge-green { background: #dcfce7; color: #166534; }
        .badge-yellow { background: #fef9c3; color: #854d0e; }
        .badge-red { background: #fee2e2; color: #991b1b; }
        .badge-blue { background: #dbeafe; color: #1e40af; }
        .badge-gray { background: #f3f4f6; color: #374151; }
        .listing { padding: 14px 0; border-bottom: 1px solid #f3f4f6; }
        .listing:last-child { border-bottom: none; }
        .listing-title { font-size: 15px; font-weight: 600; }
        .listing-title a { color: #111827; text-decoration: none; }
        .listing-title a:hover { text-decoration: underline; }
        .listing-meta { font-size: 13px; color: #6b7280; margin-top: 2px; }
        .listing-tags { margin-top: 6px; display: flex; flex-wrap: wrap; gap: 4px; }
        .listing-links { margin-top: 8px; font-size: 13px; }
        .listing-links a { color: #2563eb; text-decoration: none; }
        .listing-links a:hover { text-decoration: underline; }
        .compact-listing { padding: 6px 0; font-size: 14px; }
        .compact-listing a { color: #111827; text-decoration: none; font-weight: 500; }
        .compact-listing a:hover { text-decoration: underline; }
        .compact-listing .meta { color: #6b7280; font-size: 13px; }
        .app-item { padding: 8px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px; }
        .app-item:last-child { border-bottom: none; }
        .stats-grid { display: flex; flex-wrap: wrap; gap: 12px; }
        .stat-card { flex: 1; min-width: 120px; background: #f9fafb; border-radius: 8px; padding: 14px; text-align: center; }
        .stat-value { font-size: 24px; font-weight: 700; color: #111827; }
        .stat-label { font-size: 12px; color: #6b7280; margin-top: 2px; }
        .empty-state { color: #9ca3af; font-style: italic; font-size: 14px; }
        .footer { padding: 20px 32px; text-align: center; font-size: 12px; color: #9ca3af; background: #f9fafb; }
        .sub-section { margin-top: 14px; }
        .sub-section-title { font-size: 13px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>Daily Job Digest</h1>
            <p>{{ now()->format('l, M j, Y') }}</p>
        </div>

        {{-- Relevant Listings --}}
        <div class="section">
            <div class="section-title">
                <span class="badge badge-green">Relevant</span>
                New Listings ({{ $relevantListings->count() }})
            </div>
            @forelse($relevantListings as $listing)
                <div class="listing">
                    <div class="listing-title">
                        <a href="{{ $listing->url }}">{{ $listing->title }}</a>
                    </div>
                    <div class="listing-meta">
                        {{ $listing->companyName() }}
                        &middot; {{ $listing->board }}
                        @if($listing->remote)
                            &middot; Remote
                        @endif
                        @if($listing->salary_min || $listing->salary_max)
                            &middot; ${{ number_format($listing->salary_min / 1000) }}k–${{ number_format($listing->salary_max / 1000) }}k
                        @endif
                        @if($listing->score_data['role_type'] ?? null)
                            &middot; {{ strtoupper($listing->score_data['role_type']) }}
                        @endif
                    </div>
                    <div class="listing-tags">
                        @foreach($listing->score_data['matched_skills'] ?? [] as $skill)
                            <span class="badge badge-green">{{ $skill }}</span>
                        @endforeach
                        @foreach($listing->score_data['gaps'] ?? [] as $gap)
                            <span class="badge badge-red">{{ $gap }}</span>
                        @endforeach
                    </div>
                    <div class="listing-links">
                        <a href="{{ route('filament.admin.resources.listings.view', $listing) }}">View in Admin</a>
                    </div>
                </div>
            @empty
                <p class="empty-state">No new relevant listings today.</p>
            @endforelse
        </div>

        {{-- Maybe Listings --}}
        <div class="section">
            <div class="section-title">
                <span class="badge badge-yellow">Maybe</span>
                Listings ({{ $maybeListings->count() }})
            </div>
            @forelse($maybeListings as $listing)
                <div class="compact-listing">
                    <a href="{{ $listing->url }}">{{ $listing->title }}</a>
                    <span class="meta">
                        &mdash; {{ $listing->companyName() }}
                        &middot; {{ $listing->board }}
                        &middot; <a href="{{ route('filament.admin.resources.listings.view', $listing) }}" style="color: #2563eb; text-decoration: none;">Admin</a>
                    </span>
                </div>
            @empty
                <p class="empty-state">No new maybe listings today.</p>
            @endforelse
        </div>

        {{-- Application Updates --}}
        <div class="section">
            <div class="section-title">
                <span class="badge badge-blue">Applications</span>
                Updates
            </div>
            @if($readyApplications->isEmpty() && $failedApplications->isEmpty() && $shortlistedWithoutApplications->isEmpty())
                <p class="empty-state">No application updates.</p>
            @else
                @if($readyApplications->isNotEmpty())
                    <div class="sub-section">
                        <div class="sub-section-title">Ready</div>
                        @foreach($readyApplications as $application)
                            <div class="app-item">
                                <span class="badge badge-green">Ready</span>
                                {{ $application->listing->title }} &mdash; {{ $application->listing->companyName() }}
                            </div>
                        @endforeach
                    </div>
                @endif

                @if($failedApplications->isNotEmpty())
                    <div class="sub-section">
                        <div class="sub-section-title">Failed</div>
                        @foreach($failedApplications as $application)
                            <div class="app-item">
                                <span class="badge badge-red">Failed</span>
                                {{ $application->listing->title }} &mdash; {{ $application->listing->companyName() }}
                            </div>
                        @endforeach
                    </div>
                @endif

                @if($shortlistedWithoutApplications->isNotEmpty())
                    <div class="sub-section">
                        <div class="sub-section-title">Shortlisted — Needs Application</div>
                        @foreach($shortlistedWithoutApplications as $listing)
                            <div class="app-item">
                                <a href="{{ route('filament.admin.resources.listings.view', $listing) }}" style="color: #2563eb; text-decoration: none;">
                                    {{ $listing->title }}
                                </a>
                                &mdash; {{ $listing->companyName() }}
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>

        {{-- Daily Stats --}}
        <div class="section">
            <div class="section-title">
                <span class="badge badge-gray">Stats</span>
                Last 24 Hours
            </div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value">{{ $stats['total_scraped'] }}</div>
                    <div class="stat-label">Scraped</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: #166534;">{{ $stats['relevant_count'] }}</div>
                    <div class="stat-label">Relevant</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: #854d0e;">{{ $stats['maybe_count'] }}</div>
                    <div class="stat-label">Maybe</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: #991b1b;">{{ $stats['irrelevant_count'] }}</div>
                    <div class="stat-label">Irrelevant</div>
                </div>
            </div>
            <div style="margin-top: 16px;">
                <div class="sub-section-title">AI Costs</div>
                <div style="font-size: 14px; margin-top: 4px;">
                    Total: <strong>${{ number_format($stats['ai_total_cost'], 2) }}</strong>
                </div>
                @foreach($stats['ai_usage_breakdown'] as $usage)
                    <div style="font-size: 13px; color: #6b7280; margin-top: 2px;">
                        {{ $usage['model'] }}: ${{ number_format($usage['cost'], 4) }} ({{ $usage['requests'] }} {{ Str::plural('request', $usage['requests']) }})
                    </div>
                @endforeach
            </div>
        </div>

        <div class="footer">
            {{ config('app.name') }} &middot; {{ config('app.url') }}
        </div>
    </div>
</body>
</html>
