<?php

namespace App\Modules\Auth\Services;

use App\Models\User;
use App\Modules\Auth\DTOs\MeUpdateDTO;
use App\Modules\Auth\Repositories\UserRepository;
use Spatie\LaravelData\Optional;

/**
 * Self-service: the fields an account owns about itself.
 *
 * Deliberately NOT `UserService::update` with the acting user's own id. That method also writes
 * role, status and the permission matrix, and it carries the guards that go with them; reaching it
 * from an unguarded route would make "change my name" and "change my role" the same endpoint, one
 * validation rule away from each other.
 */
class MeService
{
    public function __construct(
        private PermissionService $permissionService,
        private UserRepository $userRepository,
    ) {}

    public function update(User $user, MeUpdateDTO $dto): User
    {
        $data = [];

        foreach (['name' => $dto->name, 'last_name' => $dto->last_name] as $field => $value) {
            if ($value instanceof Optional) {
                continue;
            }

            $data[$field] = $value;
        }

        if (! empty($data)) {
            $this->userRepository->updateUser($user->id, $data);
        }

        // Re-read, and answer the way `auth/me` does: the SPA replaces its stored user with this.
        $fresh = $this->userRepository->findUserById($user->id);
        $fresh->setAttribute('permissions', $this->permissionService->forUser($fresh));

        return $fresh;
    }
}
