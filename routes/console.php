<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Example command shipped with Laravel starter.
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Operational jobs for approval workflow SLAs.
Schedule::command('review:send-reminders --hours=24')->hourly();
Schedule::command('review:escalate-overdue --hours=24')->hourly();
