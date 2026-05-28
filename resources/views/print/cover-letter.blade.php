<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cover Letter — {{ $profile['name'] }} — {{ $listing->company ?? 'Application' }}</title>
    <style>
        /* margin: 0 hands the page edges to .sheet padding so the browser has no
           margin box to drop its auto headers/footers (URL, date, page number) into. */
        @page { size: letter; margin: 0; }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { background: #f4f4f4; }
        body {
            font-family: Georgia, serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #222;
            padding: 0.5in;
        }
        .sheet {
            background: #fff;
            padding: 0.75in 1in;
            width: 8.5in;
            max-width: 100%;
            min-height: 11in;
            margin: 0 auto;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.12);
        }
        .letterhead { margin-bottom: 20pt; }
        .letterhead .name { font-weight: bold; font-size: 13pt; }
        .letterhead .contact { color: #666; font-size: 10pt; }
        .date { margin-bottom: 16pt; color: #666; }
        .recipient { margin-bottom: 16pt; }
        .subject { font-weight: bold; margin-bottom: 16pt; }
        .salutation { margin-bottom: 12pt; }
        .body p { margin-bottom: 12pt; text-align: justify; orphans: 3; widows: 3; }
        /* Keep the closing block intact rather than orphaning the signature. */
        .signature { margin-top: 24pt; break-inside: avoid; page-break-inside: avoid; }
        .signature .contact { color: #666; font-size: 10pt; margin-top: 4pt; }
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
                padding: 0.75in 1in;
            }
            .empty { background: transparent; border-color: #999; }
        }
    </style>
</head>
<body>
    <div class="sheet">
        @if (empty($content))
            <div class="empty">No cover letter yet. Generate one from the application workspace before printing.</div>
        @else
            <div class="letterhead">
                <p class="name">{{ $profile['name'] }}</p>
                @if (! empty($profile['email']))
                    <p class="contact">{{ $profile['email'] }}</p>
                @endif
            </div>

            <p class="date">{{ now()->format('F j, Y') }}</p>

            <div class="recipient">
                <p>{{ $listing->company ?? '' }}</p>
            </div>

            <p class="subject">Position: {{ $content['subject_line'] ?? '' }}</p>

            <p class="salutation">Dear Hiring Team at {{ $listing->company ?? 'the team' }},</p>

            <div class="body">
                @foreach (explode("\n\n", $content['body'] ?? '') as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </div>

            <div class="signature">
                <p>Sincerely,</p>
                <p><strong>{{ $profile['name'] }}</strong></p>
                @if (! empty($profile['email']))
                    <p class="contact">{{ $profile['email'] }}</p>
                @endif
            </div>
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
