<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Resume — {{ $profile['name'] }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Arial, sans-serif; font-size: 11pt; line-height: 1.5; color: #333; padding: 0.5in; }
        h1 { font-size: 20pt; margin-bottom: 2pt; }
        h2 { font-size: 13pt; border-bottom: 1px solid #ccc; padding-bottom: 3pt; margin-top: 14pt; margin-bottom: 6pt; text-transform: uppercase; letter-spacing: 1px; color: #555; }
        .header { text-align: center; margin-bottom: 14pt; }
        .header p { color: #666; font-size: 10pt; }
        .header .contact { margin-top: 4pt; }
        .summary { margin-bottom: 10pt; }
        .skills { margin-bottom: 10pt; }
        .skills ul { list-style: none; display: flex; flex-wrap: wrap; gap: 6pt; }
        .skills li { background: #f0f0f0; padding: 2pt 8pt; border-radius: 3pt; font-size: 10pt; }
        .experience-entry { margin-bottom: 10pt; }
        .experience-entry .role-line { font-weight: bold; font-size: 11pt; margin-bottom: 1pt; }
        .experience-entry .meta-line { color: #666; font-size: 10pt; margin-bottom: 4pt; }
        .experience-entry ul { padding-left: 16pt; }
        .experience-entry li { margin-bottom: 3pt; }
        .education { margin-bottom: 10pt; }
        .education ul { padding-left: 16pt; }
        .education li { margin-bottom: 3pt; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $profile['name'] }}</h1>
        @php
            $contactParts = array_filter([
                $profile['email'] ?? null,
            ]);
        @endphp
        @if(count($contactParts))
            <p class="contact">{{ implode(' | ', $contactParts) }}</p>
        @endif
    </div>

    <h2>Professional Summary</h2>
    <div class="summary">
        <p>{{ $summary }}</p>
    </div>

    <h2>Skills</h2>
    <div class="skills">
        <ul>
            @foreach($skills as $skill)
                <li>{{ $skill }}</li>
            @endforeach
        </ul>
    </div>

    <h2>Experience</h2>
    @foreach($experience as $entry)
        <div class="experience-entry">
            <div class="role-line">{{ $entry['role'] }} — {{ $entry['company'] }}</div>
            <div class="meta-line">{{ $entry['period'] }}</div>
            <ul>
                @foreach($entry['highlights'] as $highlight)
                    <li>{{ $highlight }}</li>
                @endforeach
            </ul>
        </div>
    @endforeach

    <h2>Education</h2>
    <div class="education">
        <ul>
            @foreach($profile['education'] as $degree)
                <li>{{ $degree }}</li>
            @endforeach
        </ul>
    </div>
</body>
</html>
