<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// rnc-1 F4 (R17): the 24-hour meeting reminder. Hourly, so a meeting booked for "amanhã cedo" is
// reminded within the hour it enters the window; `reminded_at` keeps it to once.
Schedule::command('appointments:remind')->hourly();
