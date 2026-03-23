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
        .summary { margin-bottom: 10pt; }
        .skills { margin-bottom: 10pt; }
        .skills ul { list-style: none; display: flex; flex-wrap: wrap; gap: 6pt; }
        .skills li { background: #f0f0f0; padding: 2pt 8pt; border-radius: 3pt; font-size: 10pt; }
        .highlights ul { padding-left: 16pt; }
        .highlights li { margin-bottom: 4pt; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $profile['name'] }}</h1>
        <p>{{ $profile['title'] }}</p>
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

    <h2>Experience Highlights</h2>
    <div class="highlights">
        <ul>
            @foreach($highlights as $highlight)
                <li>{{ $highlight }}</li>
            @endforeach
        </ul>
    </div>
</body>
</html>
