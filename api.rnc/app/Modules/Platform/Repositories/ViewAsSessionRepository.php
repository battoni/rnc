<?php

namespace App\Modules\Platform\Repositories;

use App\Modules\Platform\Enums\ViewAsEndReason;
use App\Modules\Platform\Models\ViewAsSession;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The view-as audit rows (rnc-4 R26, D20). A row is open while `ended_at` is null; an open row is
 * looked up by its id AND its operator, so a session key carried by anyone else finds nothing.
 */
class ViewAsSessionRepository
{
    public function createViewAsSession(int $userId, int $firmId, ?string $ipAddress): ViewAsSession
    {
        return ViewAsSession::query()->create([
            'user_id' => $userId,
            'firm_id' => $firmId,
            'ip_address' => $ipAddress,
            'started_at' => now(),
        ]);
    }

    public function findOpenViewAsSessionOfOperator(int $id, int $userId): ?ViewAsSession
    {
        return ViewAsSession::query()
            ->with(['firm', 'operator:id,name'])
            ->whereKey($id)
            ->where('user_id', $userId)
            ->whereNull('ended_at')
            ->first();
    }

    public function closeViewAsSession(ViewAsSession $session, ViewAsEndReason $reason): ViewAsSession
    {
        $session->update(['ended_at' => now(), 'end_reason' => $reason]);

        return $session;
    }

    /** @return int how many rows were closed */
    public function closeOpenViewAsSessionsOfOperator(int $userId, ViewAsEndReason $reason): int
    {
        return ViewAsSession::query()
            ->where('user_id', $userId)
            ->whereNull('ended_at')
            ->update(['ended_at' => now(), 'end_reason' => $reason->value]);
    }

    /** @return LengthAwarePaginator<int, ViewAsSession> latest first */
    public function paginateViewAsSessions(int $perPage): LengthAwarePaginator
    {
        return ViewAsSession::query()
            ->with(['operator:id,name', 'firm:id,slug,name'])
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
