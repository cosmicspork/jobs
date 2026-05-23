<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Resume — {{ $profile['name'] }} — {{ $listing->company ?? 'Application' }}</title>
    <style>
        @page { margin: 0.5in; }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #222;
            padding: 0.5in;
            background: #f4f4f4;
        }
        .sheet {
            background: #fff;
            padding: 0.5in;
            max-width: 8.5in;
            margin: 0 auto;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.12);
        }
        h1 { font-size: 20pt; margin-bottom: 2pt; }
        h2 {
            font-size: 13pt;
            border-bottom: 1px solid #ccc;
            padding-bottom: 3pt;
            margin-top: 14pt;
            margin-bottom: 6pt;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #555;
        }
        .header { text-align: center; margin-bottom: 14pt; }
        .header p { color: #666; font-size: 10pt; }
        .summary, .skills { margin-bottom: 10pt; }
        .skills ul { list-style: none; display: flex; flex-wrap: wrap; gap: 6pt; }
        .skills li { background: #f0f0f0; padding: 2pt 8pt; border-radius: 3pt; font-size: 10pt; }
        .experience-entry,
        .education-entry { margin-bottom: 10pt; page-break-inside: avoid; }
        .experience-entry .role-line,
        .education-entry .role-line { font-weight: bold; font-size: 11pt; margin-bottom: 1pt; }
        .experience-entry .meta-line,
        .education-entry .meta-line { color: #666; font-size: 10pt; margin-bottom: 4pt; }
        .experience-entry ul,
        .education-entry ul { padding-left: 16pt; }
        .experience-entry li,
        .education-entry li { margin-bottom: 3pt; }
        .empty {
            background: #fff8e1;
            border: 1px dashed #d4a017;
            padding: 8pt 12pt;
            color: #6b5300;
            font-style: italic;
            border-radius: 4pt;
        }

        @media print {
            body { background: #fff; padding: 0; }
            .sheet { box-shadow: none; padding: 0; max-width: none; }
            .skills li { background: transparent; padding: 0; border: 1px solid #999; }
            .empty { background: transparent; border-color: #999; }
        }
    </style>
</head>
<body>
    <div class="sheet">
        @if (empty($content))
            <div class="empty">No resume content yet. Generate one from the application workspace before printing.</div>
        @else
            <div class="header">
                <h1>{{ $profile['name'] }}</h1>
                @php
                    $contactParts = array_filter([$profile['email'] ?? null]);
                @endphp
                @if (count($contactParts))
                    <p class="contact">{{ implode(' | ', $contactParts) }}</p>
                @endif
            </div>

            <h2>Professional Summary</h2>
            <div class="summary"><p>{{ $content['summary'] ?? '' }}</p></div>

            <h2>Skills</h2>
            <div class="skills">
                <ul>
                    @foreach ($content['skills'] ?? [] as $skill)
                        <li>{{ $skill }}</li>
                    @endforeach
                </ul>
            </div>

            <h2>Experience</h2>
            @foreach ($content['experience'] ?? [] as $entry)
                <div class="experience-entry">
                    <div class="role-line">{{ $entry['role'] ?? '' }} — {{ $entry['company'] ?? '' }}</div>
                    <div class="meta-line">{{ $entry['period'] ?? '' }}</div>
                    <ul>
                        @foreach ($entry['highlights'] ?? [] as $highlight)
                            <li>{{ $highlight }}</li>
                        @endforeach
                    </ul>
                </div>
            @endforeach

            <h2>Education</h2>
            @foreach ($content['education'] ?? [] as $entry)
                <div class="education-entry">
                    <div class="role-line">
                        {{ $entry['qualification'] ?? '' }}@if (! empty($entry['field_of_study'])) in {{ $entry['field_of_study'] }}@endif — {{ $entry['institution'] ?? '' }}
                    </div>
                    <div class="meta-line">{{ $entry['period'] ?? '' }}</div>
                    @if (! empty($entry['highlights']))
                        <ul>
                            @foreach ($entry['highlights'] as $highlight)
                                <li>{{ $highlight }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        @endif
    </div>

    @if (! empty($content))
        <script>
            window.addEventListener('load', function () {
                window.print();
            });
        </script>
    @endif
</body>
</html>
