<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('jobs:scrape')->dailyAt('07:00');
Schedule::command('jobs:score')->dailyAt('07:15');

Artisan::command('app:generate-token', function (): void {
    $token = base64_encode(random_bytes(32));

    $path = base_path('.env');
    $contents = file_get_contents($path);

    if (str_contains($contents, 'APP_TOKEN=')) {
        $contents = preg_replace('/^APP_TOKEN=.*/m', "APP_TOKEN={$token}", $contents);
    } else {
        $contents .= "\nAPP_TOKEN={$token}\n";
    }

    file_put_contents($path, $contents);

    $this->info("Token generated: {$token}");
    $this->info('APP_TOKEN has been written to .env');
})->purpose('Generate a new APP_TOKEN and write it to .env');

Artisan::command('db:export', function (): void {
    $exportPath = storage_path('app');

    $tables = ['listings', 'applications'];

    foreach ($tables as $table) {
        $rows = DB::table($table)->get();

        if ($rows->isEmpty()) {
            $this->warn("Skipping {$table} — no rows.");

            continue;
        }

        $columns = array_keys((array) $rows->first());
        $handle = fopen("{$exportPath}/{$table}.csv", 'w');
        fputcsv($handle, $columns);

        foreach ($rows as $row) {
            fputcsv($handle, (array) $row);
        }

        fclose($handle);
        $this->info("Exported {$rows->count()} rows to storage/app/{$table}.csv");
    }
})->purpose('Export listings and applications tables to CSV');
