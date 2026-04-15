<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{ config('app.name') }}</title>
    <style>
        body { font-family: -apple-system, 'Helvetica Neue', Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #1a1a1a; background: #f5f5f5; margin: 0; padding: 0; }
        .wrapper { max-width: 640px; margin: 0 auto; background: #ffffff; }
        .header { background: #111827; color: #ffffff; padding: 24px 32px; }
        .header h1 { font-size: 22px; font-weight: 600; margin: 0; }
        .section { padding: 24px 32px; border-bottom: 1px solid #e5e7eb; }
        .section h2 { font-size: 16px; font-weight: 600; margin: 0 0 12px 0; }
        .section p { margin: 0 0 12px 0; }
        ol { margin: 0 0 12px 20px; padding: 0; }
        ol li { margin-bottom: 6px; }
        .btn { display: inline-block; background: #f59e0b; color: #111827; padding: 10px 18px; border-radius: 6px; text-decoration: none; font-weight: 600; margin-top: 8px; }
        .muted { color: #6b7280; font-size: 13px; }
        a { color: #2563eb; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header"><h1>Welcome, {{ $user->name }}</h1></div>

    <div class="section">
        <p>Thanks for joining {{ config('app.name') }}. Here's how to get started:</p>
        <ol>
            <li><strong>Set your password.</strong> You should receive a separate email with a link to set your password. If it doesn't arrive, use the <a href="{{ $forgotPasswordUrl }}">forgot password</a> link.</li>
            <li><strong>Fill out your profile.</strong> Add your title, summaries, skills, and preferences. Scoring quality depends on it.</li>
            <li><strong>Subscribe to job boards.</strong> Pick which boards to pull listings from on the Profile page.</li>
            <li><strong>Wait for your first digest.</strong> Once scoring runs, you'll get a daily email summarizing relevant matches.</li>
        </ol>
        <a class="btn" href="{{ $loginUrl }}">Sign in</a>
    </div>

    <div class="section">
        <p class="muted">Questions? Just reply to this email.</p>
    </div>
</div>
</body>
</html>
