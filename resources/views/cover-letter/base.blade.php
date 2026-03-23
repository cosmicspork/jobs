<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cover Letter — {{ $profile['name'] }} — {{ $listing->company }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Georgia', serif; font-size: 11pt; line-height: 1.6; color: #333; padding: 0.75in 1in; }
        .date { margin-bottom: 20pt; color: #666; }
        .subject { font-weight: bold; margin-bottom: 16pt; }
        .body p { margin-bottom: 12pt; text-align: justify; }
        .signature { margin-top: 24pt; }
    </style>
</head>
<body>
    <p class="date">{{ now()->format('F j, Y') }}</p>

    <p class="subject">Re: {{ $subjectLine }}</p>

    <div class="body">
        @foreach(explode("\n\n", $body) as $paragraph)
            <p>{{ $paragraph }}</p>
        @endforeach
    </div>

    <div class="signature">
        <p>Sincerely,</p>
        <p><strong>{{ $profile['name'] }}</strong></p>
    </div>
</body>
</html>
