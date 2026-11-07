<?php

namespace App\Console\Commands;

use App\Modules\Booking\Services\ReminderService;
use Illuminate\Console\Command;

/**
 * `appointments:remind` (rnc-1 R17), scheduled hourly in `routes/console.php`: the reminder of every
 * meeting in the next 24 hours, once. The work, and the walk over the firms (AM6), is
 * `ReminderService`'s.
 */
class RemindAppointments extends Command
{
    protected $signature = 'appointments:remind';

    protected $description = 'Queue the reminder e-mail of every meeting starting in the next 24 hours (once each)';

    public function handle(ReminderService $reminderService): int
    {
        $sent = $reminderService->sendDueReminders();

        $this->info("{$sent} lembrete(s) enviado(s).");

        return self::SUCCESS;
    }
}
