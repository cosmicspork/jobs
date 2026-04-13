<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Schedule::command('jobs:scrape')->hourly();
Schedule::command('digest:send')->dailyAt('08:00');

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
