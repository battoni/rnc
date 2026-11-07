<?php

namespace App\Modules\Auth\Rules;

use App\Modules\Auth\Repositories\RoleRepository;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A role can only be handed to somebody by a person who already holds everything it grants.
 *
 * The companion of `GrantableByActor`: refusing the key one by one is pointless if the same
 * request can assign the `administrator` role instead and grant the whole matrix at once (ARC-03).
 * Assigning a role is granting its keys, so it asks the same question.
 *
 * A role that grants nothing — a newly created one, or a profile whose matrix is all false —
 * passes for everybody. There is nothing in it to hand out.
 */
class AssignableRole implements ValidationRule
{
    public const MESSAGE_KEY = 'rolesAndPermissions.cannotAssignUnheldRole';

    /**
     * @param  array<int, string>  $actorPermissionKeys  the keys the person making the request holds
     */
    public function __construct(
        private array $actorPermissionKeys,
        private RoleRepository $roleRepository,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $rolePermissions = $this->roleRepository->getRolePermissions((int) $value);
        $granted = array_keys(array_filter($rolePermissions, static fn (mixed $isGranted): bool => (bool) $isGranted));

        if (array_diff($granted, $this->actorPermissionKeys) === []) {
            return;
        }

        $fail(self::MESSAGE_KEY);
    }
}
