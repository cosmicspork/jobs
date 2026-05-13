<?php

namespace App\Jobs;

use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Artisan;

class RunJobScoring
{
    public function __invoke(Batch $batch): void
    {
        Artisan::call('jobs:score');
    }
}
