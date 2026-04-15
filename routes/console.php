<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('jobs:scrape')->hourly();
Schedule::command('digest:send')->everyMinute()->withoutOverlapping();
