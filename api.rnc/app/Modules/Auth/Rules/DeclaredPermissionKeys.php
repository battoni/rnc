<?php

namespace App\Modules\Auth\Rules;

use App\Modules\Auth\Helpers\PermissionGroupsHelper;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A permission payload is keyed by permission, and Laravel validates an array's values, never its
 * keys. An undeclared key used to be stored as sent — granted in advance to whatever route adopts
 * that name later, by anyone who can edit a role's matrix or a person's overrides. One rule for
 * both, so the two doors cannot drift apart.
 */
class DeclaredPermissionKeys implements ValidationRule
{
    public const MESSAGE_KEY = 'rolesAndPermissions.unknownPermission';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $declaredKeys = collect(PermissionGroupsHelper::getPermissionGroupsStructure())
            ->flatMap(fn (array $group) => array_column($group['permissions'], 'key'))
            ->all();

        if (! is_array($value) || array_diff(array_keys($value), $declaredKeys) === []) {
            return;
        }

        $fail(self::MESSAGE_KEY);
    }
}
