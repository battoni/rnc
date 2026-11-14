<?php

namespace App\Modules\Notification\Repositories;

use App\Models\User;
use App\Modules\Auth\Enums\UserStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;

class NotificationRepository
{
    /** The roles whose members hear the bell (plan: "every equipe/management user of the firm"). */
    private const TEAM_ROLES = ['equipe', 'gestao'];

    private const LIST_SIZE = 20;

    /** @return Collection<int, User> */
    public function findTeamMembersOfFirm(int $firmId): Collection
    {
        return User::query()
            ->where('firm_id', $firmId)
            ->where('status', UserStatus::ACTIVE->value)
            ->whereHas('role', fn ($query) => $query->whereIn('slug', self::TEAM_ROLES))
            ->get();
    }

    /** @return Collection<int, DatabaseNotification> the person's latest, newest first */
    public function findLatestNotificationsOfUser(User $user): Collection
    {
        return $user->notifications()->limit(self::LIST_SIZE)->get();
    }

    public function countUnreadNotificationsOfUser(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    /** Only the person's own: somebody else's id is not found (404, never 403). */
    public function findNotificationOfUser(User $user, string $id): ?DatabaseNotification
    {
        return $user->notifications()->whereKey($id)->first();
    }

    public function markNotificationRead(DatabaseNotification $notification): DatabaseNotification
    {
        $notification->markAsRead();

        return $notification;
    }

    public function markAllNotificationsOfUserRead(User $user): void
    {
        $user->unreadNotifications()->update(['read_at' => now()]);
    }
}
