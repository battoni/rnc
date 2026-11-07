<?php

namespace App\Modules\Notification\Services;

use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Notification\Repositories\NotificationRepository;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as Notifier;

/**
 * Rings the bell of the current firm's team (rnc-1 R17): every active `equipe` and `ceo` member —
 * the people who work the leads. The operator (Battoni Dev) is not paged by a firm's leads.
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
}
