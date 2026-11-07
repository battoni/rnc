<?php

namespace App\Modules\Diagnostic\Requests;

use App\Modules\Diagnostic\Enums\DiagnosticPath;
use App\Modules\Diagnostic\Enums\Sector;
use App\Modules\Tracking\Enums\TrafficSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Step 1 starts (`POST /api/public/diagnostics`): how the visitor answers it, the sector a sector
 * page's CTA carried, and the visit's derived source. No personal data, no stage, no firm (AM13).
 */
class StartDiagnosticRequest extends FormRequest
{
    private const UUID_VERSION = 4;

    private const MEDIUM_MAX_LENGTH = 60;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'visitor' => ['nullable', 'string', 'uuid:'.self::UUID_VERSION],
            'path' => ['required', 'string', Rule::enum(DiagnosticPath::class)],
            'sector' => ['nullable', 'string', Rule::enum(Sector::class)],
            'source' => ['required', 'string', Rule::enum(TrafficSource::class)],
            'medium' => ['nullable', 'string', 'max:'.self::MEDIUM_MAX_LENGTH],
        ];
    }
}
