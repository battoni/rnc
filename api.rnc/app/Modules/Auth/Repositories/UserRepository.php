<?php

namespace App\Modules\Auth\Repositories;

use App\Models\User;
use App\Modules\Auth\Enums\UserStatus;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class UserRepository
{
    private const SESSIONS_TABLE = 'sessions';

    public function __construct(
        private User $model
    ) {}

    /**
     * @param  array<int>|null  $ids  Filter by user ids (when provided and non-empty).
     * @param  string|null  $orderBy  One of: name_asc, name_desc, status_asc, status_desc, role_asc, role_desc.
     * @param  string|null  $profileType  Filter by registration profile type.
     * @return LengthAwarePaginator<int, User>
     */
    public function findAllUsers(?array $ids = null, ?string $orderBy = null, ?string $profileType = null, int $perPage = 15): LengthAwarePaginator
    {
        // `UserResource` reads the role's name and slug on every row.
        $query = $this->model->newQuery()->with('role');

        if ($ids !== null && count($ids) > 0) {
            $query->whereIn('users.id', $ids);
        }

        if ($profileType !== null) {
            $query->where('users.profile_type', $profileType);
        }

        $orderBy = $orderBy ?? 'name_asc';
        switch ($orderBy) {
            case 'name_desc':
                $query->orderByDesc('name')->orderByDesc('last_name');
                break;
            case 'status_asc':
                $query->orderBy('status');
                break;
            case 'status_desc':
                $query->orderByDesc('status');
                break;
            case 'role_asc':
                $query
                    ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
                    ->select('users.*')
                    ->orderBy('roles.name')
                    ->orderBy('users.name')
                    ->orderBy('users.last_name');
                break;
            case 'role_desc':
                $query
                    ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
                    ->select('users.*')
                    ->orderByDesc('roles.name')
                    ->orderBy('users.name')
                    ->orderBy('users.last_name');
                break;
            case 'name_asc':
            default:
                $query->orderBy('name')->orderBy('last_name');
                break;
        }

        return $query->paginate($perPage);
    }

    public function findUserById(int $id): ?User
    {
        return $this->model->newQuery()->with('role')->find($id);
    }

    /**
     * @return array<int, User>
     */
    public function findAllActiveUsers(): array
    {
        return $this->model
            ->newQuery()
            ->where('status', UserStatus::ACTIVE->value)
            ->orderBy('name')
            ->orderBy('last_name')
            ->get()
            ->all();
    }

    public function findUserByUsername(string $username, ?int $excludeId = null): ?User
    {
        $query = $this->model->where('username', $username);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->first();
    }

    public function findUserByEmail(string $email): ?User
    {
        return $this->model->newQuery()->where('email', $email)->first();
    }

    /**
     * The account a phone number signs in to — but only when exactly ONE account carries it.
     * `users.phone` is not unique (an administrator may record a shared line), and a code must
     * never pick one of two people at random.
     */
    public function findSoleUserByPhone(string $phone): ?User
    {
        $users = $this->model->newQuery()->where('phone', $phone)->limit(2)->get();

        return $users->count() === 1 ? $users->first() : null;
    }

    public function createUser(array $data): User
    {
        return $this->model->create($data);
    }

    public function updateUser(int $id, array $data): bool
    {
        return $this->model->where('id', $id)->update($data) > 0;
    }

    /** The portal login of a partner (rnc-2 R1), active or revoked — at most one per partner. */
    public function findUserByPartnerId(int $partnerId): ?User
    {
        return $this->model->newQuery()->where('partner_id', $partnerId)->latest('id')->first();
    }

    /**
     * Fill and save through the model, so its casts run — `updateUser` is a query update and would
     * store a password in clear.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function saveUser(User $user, array $attributes): User
    {
        $user->fill($attributes)->save();

        return $user;
    }

    /**
     * Every stored session of the user (`SESSION_DRIVER=database`, the `sessions` table): a cookie
     * already handed out finds nothing on its next request.
     */
    public function deleteUserSessions(int $userId): void
    {
        DB::table(self::SESSIONS_TABLE)->where('user_id', $userId)->delete();
    }

    public function deleteUser(int $id): bool
    {
        return $this->model->where('id', $id)->delete();
    }

    /**
     * @return LazyCollection<int, User>
     */
    public function cursorAllUsers(): LazyCollection
    {
        return $this->model->newQuery()->orderBy('id')->cursor();
    }
}
