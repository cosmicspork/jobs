<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>AI cap reached</title></head>
<body style="font-family: -apple-system, sans-serif; padding: 24px; color: #1a1a1a;">
    <h2>AI monthly cap reached</h2>
    <p><strong>{{ $user->name }}</strong> ({{ $user->email }}) hit the per-user monthly AI spend cap.</p>
    <ul>
        <li>Spend this month: <strong>${{ number_format($spend, 2) }}</strong></li>
        <li>Cap: <strong>${{ number_format($cap, 2) }}</strong></li>
    </ul>
    <p>Scoring for this user is paused until the next calendar month, or until you raise the cap.</p>
</body>
</html>
