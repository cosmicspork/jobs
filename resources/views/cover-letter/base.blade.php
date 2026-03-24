<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cover Letter — {{ $profile['name'] }} — {{ $listing->company }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Georgia', serif; font-size: 11pt; line-height: 1.6; color: #333; padding: 0.75in 1in; }
        .letterhead { margin-bottom: 20pt; }
        .letterhead .name { font-weight: bold; font-size: 13pt; }
        .letterhead .contact { color: #666; font-size: 10pt; }
        .date { margin-bottom: 16pt; color: #666; }
        .recipient { margin-bottom: 16pt; }
        .subject { font-weight: bold; margin-bottom: 16pt; }
        .salutation { margin-bottom: 12pt; }
        .body p { margin-bottom: 12pt; text-align: justify; }
        .signature { margin-top: 24pt; }
        .signature .contact { color: #666; font-size: 10pt; margin-top: 4pt; }
    </style>
</head>
<body>
    <div class="letterhead">
        <p class="name">{{ $profile['name'] }}</p>
        @php
            $contactParts = array_filter([
                $profile['email'] ?? null,
                $profile['location'] ?? null,
            ]);
        @endphp
        @if(count($contactParts))
            <p class="contact">{{ implode(' | ', $contactParts) }}</p>
        @endif
    </div>

    <p class="date">{{ now()->format('F j, Y') }}</p>

    <div class="recipient">
        <p>{{ $listing->company }}</p>
    </div>

    <p class="subject">Position: {{ $subjectLine }}</p>

    <p class="salutation">Dear Hiring Team at {{ $listing->company }},</p>

    <div class="body">
        @foreach(explode("\n\n", $body) as $paragraph)
            <p>{{ $paragraph }}</p>
        @endforeach
    </div>

    <div class="signature">
        <p>Sincerely,</p>
        <p><strong>{{ $profile['name'] }}</strong></p>
        @if(!empty($profile['email']))
            <p class="contact">{{ $profile['email'] }}</p>
        @endif
    </div>
</body>
</html>
