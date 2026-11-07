<?php

namespace App\Modules\Notification\Services;

use App\Models\User;
use App\Modules\Notification\Repositories\NotificationRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;

/** The signed-in person's own bell (rnc-1 R17): what it lists and what marks as read. */
class NotificationService
{
    public function __construct(private NotificationRepository $notificationRepository) {}

    /** @return array{unread_count: int, items: Collection<int, DatabaseNotification>} */
    public function inbox(User $user): array
    {
        return [
            'unread_count' => $this->notificationRepository->countUnreadNotificationsOfUser($user),
            'items' => $this->notificationRepository->findLatestNotificationsOfUser($user),
        ];
    }

    public function markRead(User $user, string $id): ?DatabaseNotification
    {
        $notification = $this->notificationRepository->findNotificationOfUser($user, $id);

        return ! $notification ? null : $this->notificationRepository->markNotificationRead($notification);
    }

    public function markAllRead(User $user): int
    {
        $this->notificationRepository->markAllNotificationsOfUserRead($user);

        return $this->notificationRepository->countUnreadNotificationsOfUser($user);
    }
}
