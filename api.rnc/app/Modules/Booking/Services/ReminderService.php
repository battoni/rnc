<?php

namespace App\Modules\Booking\Services;

use App\Modules\Booking\Mail\MeetingReminder;
use App\Modules\Booking\Repositories\AppointmentRepository;
use App\Modules\Firm\Services\CurrentFirm;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

/**
 * The 24-hour reminder (rnc-1 R17): every meeting starting in the next 24 hours that has not been
 * reminded gets one e-mail. Walks each firm as the current one (plan AM6), so the firm-scoped reads
 * see that firm's meetings and nothing else.
 */
class ReminderService
{
    private const WINDOW_HOURS = 24;

    private const MAIL_LOCALE = 'pt_BR';

    public function __construct(
        private CurrentFirm $currentFirm,
        private AppointmentRepository $appointmentRepository,
        private MeetingMailBuilder $meetingMailBuilder,
    ) {}

    /** @return int how many reminders were queued */
    public function sendDueReminders(): int
    {
        $sent = 0;
        $now = CarbonImmutable::now();

        $this->currentFirm->forEachFirm(function () use ($now, &$sent) {
            foreach ($this->appointmentRepository->findAppointmentsToRemind($now, $now->addHours(self::WINDOW_HOURS)) as $appointment) {
                $email = $appointment->lead->email;

                if ($email === null || ! $this->appointmentRepository->claimAppointmentReminder($appointment, $now)) {
                    continue;
                }

                Mail::to($email)->queue((new MeetingReminder($this->meetingMailBuilder->build($appointment, $appointment->lead)))->locale(self::MAIL_LOCALE));
                $sent++;
            }
        });

        return $sent;
    }
}
