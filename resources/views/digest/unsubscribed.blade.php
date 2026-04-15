<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Digest paused</title>
    <style>
        body { font-family: -apple-system, sans-serif; background: #f5f5f5; color: #1a1a1a; padding: 60px 20px; text-align: center; }
        .card { max-width: 480px; margin: 0 auto; background: #fff; padding: 32px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        h1 { font-size: 22px; margin: 0 0 12px; }
        p { margin: 0 0 16px; color: #4b5563; }
        a { color: #2563eb; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Daily digest paused</h1>
        <p>You won't receive the daily digest anymore, {{ $user->name }}.</p>
        <p>Changed your mind? Re-enable it from your <a href="{{ url('/') }}">profile</a>.</p>
    </div>
</body>
</html>
