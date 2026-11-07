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

// rnc-3 F5 (R11, AM19): a reviewer hears at day 83 that a note's review runs out at day 90 — once
// per review, every firm in turn. 07:00 in São Paulo, before the team starts.
Schedule::command('assistant:notify-expiring')->dailyAt('07:00')->timezone('America/Sao_Paulo');

// rnc-3 F8 (R27, AM6): Reinaldo's briefing by e-mail — every active ceo of each firm, business days
// only (the command skips weekends and national holidays). 07:00 in São Paulo, before the day starts.
Schedule::command('briefing:send')->dailyAt('07:00')->timezone('America/Sao_Paulo');
