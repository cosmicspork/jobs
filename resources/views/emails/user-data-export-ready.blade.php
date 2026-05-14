<x-mail::message>
# Your data export is ready

Hi {{ $user->name }} — we've packaged everything we have on you into a ZIP file. It includes your profile, target profiles, application history, question sets, listing interaction state, AI usage, and the resume and cover-letter PDFs we've generated for you.

<x-mail::button :url="$signedUrl">
Download my data
</x-mail::button>

The link expires in 24 hours. If you didn't request this export, ignore this email and the file will be deleted automatically.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
