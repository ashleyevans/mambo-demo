<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Normal polling stays within TrueLayer's background AIS allowance (4×/day).
Schedule::command('accounts:sync')->everySixHours()->withoutOverlapping();
// Demo mode polls every minute, forwarding the PSU IP as customer-present.
Schedule::command('accounts:sync --demo')->everyMinute()->withoutOverlapping();
