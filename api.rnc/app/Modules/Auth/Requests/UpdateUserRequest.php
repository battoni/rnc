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

class UpdateUserRequest extends FormRequest
{
    use ResolvesActorPermissions;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('id');
        $actorPermissionKeys = $this->actorPermissionKeys();

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'username' => ['sometimes', 'string', 'max:255', Rule::unique('users', 'username')->ignore($userId)],
            'role_id' => ['sometimes', 'nullable', 'integer', 'exists:roles,id', new AssignableRole($actorPermissionKeys, app(RoleRepository::class)), new TeamRole($this->actorIsPlatformOperator(), app(RoleRepository::class))],
            // A specialist is bookable by a visitor; flagging one starts it on the firm's hours (rnc-4 R32).
            'is_specialist' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::enum(UserStatus::class)],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'permissions' => ['sometimes', 'array', new DeclaredPermissionKeys, new GrantableByActor($actorPermissionKeys)],
            'permissions.*' => ['required', 'boolean'],
        ];
    }
}
