<?php

namespace App\Modules\Auth\Rules;

use App\Modules\Auth\Repositories\RoleRepository;
use App\Modules\Auth\Services\RoleService;
use App\Modules\Partner\Services\PartnerAccessService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A firm member hands out team roles only (rnc-4 R31, plan F5a AM2): never `operador` — the
 * platform's own role, every key there is — nor `parceiro`, a partner's portal login, which is made
 * with its partner by the partner screen. Only the firm-less platform operator gives those.
 *
 * Beside `AssignableRole`, which already refuses a role holding a key the actor lacks: this one
 * names the boundary itself, so a manager is refused even if a future key reached both roles.
 */
class TeamRole implements ValidationRule
{
    public const MESSAGE_KEY = 'users.form.roleNotAssignable';

    /** Roles that are never a firm's team, whatever keys they hold. */
    public const RESERVED_ROLE_SLUGS = [RoleService::ADMINISTRATOR_SLUG, PartnerAccessService::PARTNER_ROLE_SLUG];

    public function __construct(
        private bool $actorIsPlatformOperator,
        private RoleRepository $roleRepository,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->actorIsPlatformOperator || $value === null || $value === '') {
            return;
        }

        $slug = $this->roleRepository->findRoleById((int) $value)?->slug;

        if (! in_array($slug, self::RESERVED_ROLE_SLUGS, true)) {
            return;
        }

        $fail(self::MESSAGE_KEY);
    }
}
