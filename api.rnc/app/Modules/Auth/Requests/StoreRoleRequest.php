<?php

namespace App\Modules\Auth\Requests;

use App\Modules\Auth\Helpers\PermissionGroupsHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /*
         * An unknown group id is a 422, not a silent shrink: the service skipped ids it did not
         * recognise, and skipping is indistinguishable from "remove this group".
         */
        $knownGroupIds = array_column(PermissionGroupsHelper::getPermissionGroupsStructure(), 'id');

        return [
            'name' => ['required', 'string', 'max:255', 'unique:roles'],
            'description' => ['nullable', 'string'],
            'permission_group_ids' => ['required', 'array', 'min:1'],
            'permission_group_ids.*' => ['required', 'string', Rule::in($knownGroupIds)],
        ];
    }

    /**
     * A taken name is a rule the screen shows in the reader's language, so it travels as a key
     * (the service answers the same key when the unique index catches a race).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'rolesAndPermissions.form.nameTaken',
        ];
    }
}
