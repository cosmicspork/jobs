<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Schedule::command('jobs:scrape')->hourly();
Schedule::command('digest:send')->dailyAt('08:00');
