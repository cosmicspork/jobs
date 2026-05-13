<?php

namespace App\Jobs;

use App\ApplicationStatus;
use App\Models\Application;
use Illuminate\Bus\Batch;

class MarkApplicationReady
{
    public function __construct(public Application $application) {}

    public function __invoke(Batch $batch): void
    {
        $this->application->refresh()->update(['status' => ApplicationStatus::Ready]);
    }
}
