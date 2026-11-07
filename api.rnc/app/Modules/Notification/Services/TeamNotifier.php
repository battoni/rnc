<?php

namespace App\Modules\Notification\Services;

use App\Models\User;
use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Notification\Repositories\NotificationRepository;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as Notifier;

/**
 * Rings the bell of the current firm's team (rnc-1 R17): every active `equipe` and `ceo` member —
 * the people who work the leads. The operator (Battoni Dev) is not paged by a firm's leads.
 * `notifyUser` rings one person the caller already picked (rnc-3 R13: the specialist a question was
 * handed to).
 */
class TeamNotifier
{
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
}
