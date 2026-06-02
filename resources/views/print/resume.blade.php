<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Resume — {{ $profile['name'] }} — {{ $listing->company ?? 'Application' }}</title>
    <style>
        /* margin: 0 hands the page edges to .sheet padding so the browser has no
           margin box to drop its auto headers/footers (URL, date, page number) into. */
        @page { size: letter; margin: 0; }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { background: #f4f4f4; }
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #222;
            padding: 0.5in;
        }
        .sheet {
            background: #fff;
            padding: 0.5in;
            width: 8.5in;
            max-width: 100%;
            min-height: 11in;
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
            /* Keep a section heading glued to the content that follows it. */
            break-after: avoid;
            page-break-after: avoid;
        }
        .header { text-align: center; margin-bottom: 14pt; }
        .header p { color: #666; font-size: 10pt; }
        .summary, .skills { margin-bottom: 10pt; }
        /* Don't let the browser split these blocks across a page boundary. */
        .summary,
        .skills,
        .experience-entry,
        .education-entry { break-inside: avoid; page-break-inside: avoid; }
        .skills ul { list-style: none; display: flex; flex-wrap: wrap; gap: 6pt; }
        .skills li {
            background: #f0f0f0;
            padding: 2pt 8pt;
            border-radius: 3pt;
            font-size: 10pt;
            break-inside: avoid;
        }
        .experience-entry,
        .education-entry { margin-bottom: 10pt; }
        .experience-entry .role-line,
        .education-entry .role-line { font-weight: bold; font-size: 11pt; margin-bottom: 1pt; }
        .experience-entry .meta-line,
        .education-entry .meta-line { color: #666; font-size: 10pt; margin-bottom: 4pt; }
        .experience-entry ul,
        .education-entry ul { padding-left: 16pt; }
        .experience-entry li,
        .education-entry li { margin-bottom: 3pt; }
        p { orphans: 3; widows: 3; }
        .empty {
            background: #fff8e1;
            border: 1px dashed #d4a017;
            padding: 8pt 12pt;
            color: #6b5300;
            font-style: italic;
            border-radius: 4pt;
        }

        @media print {
            html, body { background: #fff; }
            body { padding: 0; }
            .sheet {
                box-shadow: none;
                width: auto;
                max-width: none;
                min-height: 0;
                margin: 0;
                padding: 0.5in;
                /* Repeat the .sheet padding on every printed page so page 2+
                   keeps the same margin instead of butting against the paper
                   edge. @page margin stays 0 (no margin box → no browser
                   headers/footers), so this padding is the only per-page
                   margin. Browsers that ignore it fall back to a flush top. */
                -webkit-box-decoration-break: clone;
                box-decoration-break: clone;
            }
            /* Keep the skill chips legible on paper: padding stays, and force the
               background to render instead of collapsing the text onto the border. */
            .skills li {
                background: #f0f0f0;
                border: 1px solid #ccc;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
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
