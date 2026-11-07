<?php

namespace App\Modules\Auth\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Nobody hands out a key they do not hold themselves.
 *
 * `users.create` / `users.update` used to be the whole product: whoever could edit an account
 * could give it — or themselves, through their own row — any permission in the matrix, including
 * `permissions.update`. Editing people is not the same job as deciding what people may do, and a
 * screen that can only manage accounts must not be a way around the screen that manages power
 * (ARC-03).
 *
 * Only GRANTS are checked. Taking a key away is not an escalation, and an administrator revoking
 * something they were never given themselves is an ordinary day.
 */
class GrantableByActor implements ValidationRule
{
    public const MESSAGE_KEY = 'rolesAndPermissions.cannotGrantUnheldPermission';

    /**
     * @param  array<int, string>  $actorPermissionKeys  the keys the person making the request holds
     */
    public function __construct(private array $actorPermissionKeys) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        $granted = array_keys(array_filter($value, static fn (mixed $isGranted): bool => (bool) $isGranted));

        if (array_diff($granted, $this->actorPermissionKeys) === []) {
            return;
        }

        $fail(self::MESSAGE_KEY);
    }
}
