<?php

namespace App\Modules\Firm\Services;

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Services\RoleService;
use App\Modules\Firm\Helpers\FirmModules;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Repositories\FirmRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * The only writer of `firms.modules` (rnc-4 R18, D15, D16) — the platform console (F4a) and
 * `firm:create` (F3b1) call it. The list is the firm's whole opt-in set, not a change: core keys are
 * dropped (always on, never stored), the rest is checked and written in the catalogue's order.
 *
 * - an unknown key → 422 `modules.unknown`;
 * - a key without what it needs (`radar`, `contracts`, `fiscal` → `portfolio`) → 422
 *   `modules.missingDependency`;
 * - turning an operator-only key (`channel`) on or off when the actor is not the platform operator
 *   → 403. Leaving it as it was is anyone's.
 */
class FirmModulesService
{
    public const FIELD = 'modules';

    public const UNKNOWN = 'modules.unknown';

    public const MISSING_DEPENDENCY = 'modules.missingDependency';

    public function __construct(private FirmRepository $firmRepository) {}

    /** @param  list<string>  $modules */
    public function set(Firm $firm, array $modules, User $actor): Firm
    {
        $wanted = array_values(array_filter($modules, fn (string $module) => ! FirmModules::isCore($module)));

        if (array_diff($wanted, FirmModules::ALL) !== []) {
            throw ValidationException::withMessages([self::FIELD => [self::UNKNOWN]]);
        }

        $wanted = FirmModules::normalise($wanted);

        foreach ($wanted as $module) {
            if (array_diff(FirmModules::DEPENDENCIES[$module] ?? [], $wanted) !== []) {
                throw ValidationException::withMessages([self::FIELD => [self::MISSING_DEPENDENCY]]);
            }
        }

        $current = FirmModules::enabled($firm);

        foreach (FirmModules::OPERATOR_ONLY as $module) {
            if (in_array($module, $current, true) !== in_array($module, $wanted, true) && ! self::isOperator($actor)) {
                throw new AuthorizationException;
            }
        }

        return $this->firmRepository->updateFirmModules($firm, $wanted);
    }

    private static function isOperator(User $actor): bool
    {
        $role = $actor->role;

        return $role instanceof Role && $role->slug === RoleService::ADMINISTRATOR_SLUG;
    }
}
