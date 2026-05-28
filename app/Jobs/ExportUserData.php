<?php

namespace App\Jobs;

use App\Mail\UserDataExportReady;
use App\Models\User;
use App\Services\UserDataExporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use ZipArchive;

class ExportUserData implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $backoff = 30;

    public function __construct(public User $user) {}

    public function handle(UserDataExporter $exporter): void
    {
        $manifest = $exporter->export($this->user);
        $filename = now()->format('Y-m-d_His').'.zip';
        $storagePath = "exports/{$this->user->id}/{$filename}";

        $tempZipPath = tempnam(sys_get_temp_dir(), 'user-export-');

        try {
            $zip = new ZipArchive;
            if ($zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException("Unable to open temp zip at {$tempZipPath}");
            }

            $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $zip->addFromString('README.txt', $this->readme($manifest));

            $zip->close();

            $stream = fopen($tempZipPath, 'r');
            try {
                Storage::put($storagePath, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        } finally {
            @unlink($tempZipPath);
        }

        $signedUrl = URL::temporarySignedRoute(
            'user-data.download',
            now()->addHours(24),
            ['user' => $this->user->id, 'file' => $filename],
        );

        Mail::to($this->user->email)->send(new UserDataExportReady($this->user, $signedUrl));

        Log::info("Generated user data export for user {$this->user->id} at {$storagePath}");
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    protected function readme(array $manifest): string
    {
        $generated = $manifest['generated_at'] ?? now()->toIso8601String();
        $version = $manifest['schema_version'] ?? UserDataExporter::SCHEMA_VERSION;

        return <<<TXT
            Your {$manifest['user']['name']} data export
            Generated: {$generated}
            Schema version: {$version}

            manifest.json
              Everything we have on you: profile, target profiles, applications,
              question sets, listing interactions, AI usage, board subscriptions.
              Each application's tailored resume and cover letter are stored
              inline as structured JSON under `applications[].resume_content`
              and `applications[].cover_letter_content`.
              Sensitive fields (passwords, admin flags, internal scoring state)
              are excluded by design.
            TXT;
    }
}
