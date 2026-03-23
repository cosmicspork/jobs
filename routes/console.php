<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('jobs:scrape')->dailyAt('07:00');
Schedule::command('jobs:score')->dailyAt('07:15');
