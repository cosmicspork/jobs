<?php

namespace App\Jobs;

use App\ApplicationStatus;
use App\Models\Application;
use Illuminate\Bus\Batch;
use Throwable;

class MarkApplicationFailed
{
    public function __construct(public Application $application) {}

    public function __invoke(Batch $batch, Throwable $exception): void
    {
        $this->application->refresh()->update(['status' => ApplicationStatus::Failed]);
    }
}
