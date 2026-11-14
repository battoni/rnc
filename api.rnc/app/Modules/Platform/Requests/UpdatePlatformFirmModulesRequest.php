<?php

namespace App\Modules\Platform\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A firm's modules from the console (`PUT /api/platform/firms/{id}/modules`, rnc-4 R18): the WHOLE
 * opt-in list, possibly empty. Which keys exist, what needs what and who may touch `channel` are
 * `FirmModulesService`'s refusals (`modules.unknown`, `modules.missingDependency`).
 */
class UpdatePlatformFirmModulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'modules' => ['present', 'array', 'list'],
            'modules.*' => ['string', 'distinct'],
        ];
    }

    /** @return list<string> */
    public function modules(): array
    {
        return array_values($this->validated('modules'));
    }
}
