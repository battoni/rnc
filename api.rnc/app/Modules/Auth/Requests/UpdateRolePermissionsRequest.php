<?php

namespace App\Modules\Auth\Requests;

use App\Modules\Auth\Repositories\RoleRepository;
use App\Modules\Auth\Requests\Concerns\ResolvesActorPermissions;
use App\Modules\Auth\Rules\DeclaredPermissionKeys;
use App\Modules\Auth\Rules\GrantableByActor;
use App\Modules\Auth\Services\RoleService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRolePermissionsRequest extends FormRequest
{
    use ResolvesActorPermissions;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permissions' => ['required', 'array', new DeclaredPermissionKeys, ...$this->grantCap()],
            'permissions.*' => ['required', 'boolean'],
        ];
    }

    /**
     * The grant cap, except on the administrator role.
     *
     * Granting a key TO the administrator is not an escalation: the actor gains nothing unless they
     * already hold that role, and then they hold everything. It is how a matrix left incomplete is
     * restored — capping it is what made the lockout permanent. Revokes on that role are refused in
     * `RoleService`, which also re-asserts the whole catalogue on every save.
     *
     * @return array<int, GrantableByActor>
     */
    private function grantCap(): array
    {
        $role = app(RoleRepository::class)->findRoleById((int) $this->route('id'));

        return $role?->slug === RoleService::ADMINISTRATOR_SLUG ? [] : [new GrantableByActor($this->actorPermissionKeys())];
    }
}
