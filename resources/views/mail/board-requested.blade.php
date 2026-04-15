<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Board request</title></head>
<body style="font-family: -apple-system, sans-serif; padding: 24px; color: #1a1a1a;">
    <h2>New board request</h2>
    <p><strong>{{ $user->name }}</strong> ({{ $user->email }}) requested a new job board.</p>
    <ul>
        <li><strong>Name:</strong> {{ $boardName }}</li>
        <li><strong>URL:</strong> <a href="{{ $boardUrl }}">{{ $boardUrl }}</a></li>
    </ul>
    @if ($notes)
        <p><strong>Notes:</strong></p>
        <blockquote style="border-left: 3px solid #e5e7eb; padding-left: 12px; margin: 8px 0; color: #4b5563;">
            {{ $notes }}
        </blockquote>
    @endif
</body>
</html>
