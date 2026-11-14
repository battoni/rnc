<?php

namespace App\Modules\Notification\Services;

use App\Models\User;
use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Notification\Repositories\NotificationRepository;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as Notifier;

/**
 * Rings the bell of the current firm's team (rnc-1 R17): every active `equipe` and `gestao` member —
 * the people who work the leads. The operator (Battoni Dev) is not paged by a firm's leads.
 * `notifyUser` rings one person the caller already picked (rnc-3 R13: the specialist a question was
 * handed to). `mailTeam` queues one e-mail to the same team (rnc-4 R29: a beta signup rings the
 * platform firm's team by bell AND e-mail).
 */
class TeamNotifier
{
    private const MAIL_LOCALE = 'pt_BR';

    public function __construct(
        private CurrentFirm $currentFirm,
        private NotificationRepository $notificationRepository,
    ) {}

    public function notifyTeam(Notification $notification): void
    {
        $firmId = $this->currentFirm->id();

        if ($firmId === null) {
            return;
        }

        Notifier::send($this->notificationRepository->findTeamMembersOfFirm($firmId), $notification);
    }

    public function notifyUser(User $user, Notification $notification): void
    {
        Notifier::send($user, $notification);
    }

    /**
     * The same mail, queued to every member of the current firm's team with an e-mail — each its own
     * copy, so no member sees the others' addresses.
     */
    public function mailTeam(Mailable $mail): void
    {
        $firmId = $this->currentFirm->id();

        if ($firmId === null) {
            return;
        }

        foreach ($this->notificationRepository->findTeamMembersOfFirm($firmId) as $member) {
            if ($member->email === null) {
                continue;
            }

            Mail::to($member->email)->queue((clone $mail)->locale(self::MAIL_LOCALE));
        }
    }
}
