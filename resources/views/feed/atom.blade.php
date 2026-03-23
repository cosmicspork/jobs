<?xml version="1.0" encoding="utf-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
    <title>Job Hunter — Scored Listings</title>
    <link href="{{ url('/feed.xml') }}" rel="self" type="application/atom+xml"/>
    <link href="{{ url('/') }}" rel="alternate" type="text/html"/>
    <id>{{ url('/feed.xml') }}</id>
    <updated>{{ $listings->first()?->scored_at?->toAtomString() ?? now()->toAtomString() }}</updated>

    @foreach($listings as $listing)
    <entry>
        <title>{{ $listing->score }}/100 — {{ $listing->title }} @ {{ $listing->company }}{{ $listing->remote ? ' [Remote]' : '' }}{{ $listing->salary_min ? ' $' . number_format($listing->salary_min / 1000) . 'k-$' . number_format($listing->salary_max / 1000) . 'k' : '' }}</title>
        <link href="{{ $listing->url }}" rel="alternate"/>
        <link href="{{ url('/apply/' . $listing->id) }}" rel="related" title="Generate application"/>
        <id>{{ $listing->url }}</id>
        <updated>{{ $listing->scored_at->toAtomString() }}</updated>
        <summary type="html"><![CDATA[
            <p><strong>Score:</strong> {{ $listing->score }}/100</p>
            @if($listing->score_data)
            <p><strong>Matched Skills:</strong> {{ implode(', ', $listing->score_data['matched_skills'] ?? []) }}</p>
            <p><strong>Gaps:</strong> {{ implode(', ', $listing->score_data['gaps'] ?? []) }}</p>
            <p><strong>Reasoning:</strong> {{ $listing->score_data['reasoning'] ?? '' }}</p>
            @endif
            <hr/>
            <p>{{ Str::limit($listing->description, 500) }}</p>
        ]]></summary>
    </entry>
    @endforeach
</feed>
