<?php

namespace App\Modules\Auth\Requests;

use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Auth\Repositories\RoleRepository;
use App\Modules\Auth\Requests\Concerns\ResolvesActorPermissions;
use App\Modules\Auth\Rules\AssignableRole;
use App\Modules\Auth\Rules\DeclaredPermissionKeys;
use App\Modules\Auth\Rules\GrantableByActor;
use App\Modules\Auth\Rules\TeamRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    use ResolvesActorPermissions;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $actorPermissionKeys = $this->actorPermissionKeys();

        return [
            'name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'role_id' => ['required', 'integer', 'exists:roles,id', new AssignableRole($actorPermissionKeys, app(RoleRepository::class)), new TeamRole($this->actorIsPlatformOperator(), app(RoleRepository::class))],
            // A specialist is bookable by a visitor; flagging one starts it on the firm's hours (rnc-4 R32).
            'is_specialist' => ['sometimes', 'boolean'],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'password' => ['required', 'string', 'min:8'],
            'permissions' => ['sometimes', 'array', new DeclaredPermissionKeys, new GrantableByActor($actorPermissionKeys)],
            'permissions.*' => ['required', 'boolean'],
        ];
    }
}
