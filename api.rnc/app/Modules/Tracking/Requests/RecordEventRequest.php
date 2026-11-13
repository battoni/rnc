<?php

namespace App\Modules\Tracking\Requests;

use App\Modules\Firm\Services\CurrentFirm;
use App\Modules\Tracking\Enums\EventType;
use App\Modules\Tracking\Enums\TrafficSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The whole contract of `POST /api/public/events` (rnc-1 plan AM2): `{visitor, type, path, sector?,
 * source, medium, lead_token?}` and nothing else — a `lead_id`, a `firm_id` or a `referrer` in the
 * body never reaches the DTO, because the DTO reads `validated()` (`config/data.php`).
 */
class RecordEventRequest extends FormRequest
{
    private const UUID_VERSION = 4;

    private const PATH_MAX_LENGTH = 255;

    private const MEDIUM_MAX_LENGTH = 60;

    private const LEAD_TOKEN_MAX_LENGTH = 64;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'visitor' => ['required', 'string', 'uuid:'.self::UUID_VERSION],
            'type' => ['required', 'string', Rule::enum(EventType::class)],
            'path' => ['required', 'string', 'max:'.self::PATH_MAX_LENGTH],
            // A slug of this firm's own pages: the funnel by sector counts nothing invented.
            'sector' => ['nullable', 'string', Rule::exists('sector_pages', 'slug')->where('firm_id', app(CurrentFirm::class)->id())],
            'source' => ['required', 'string', Rule::enum(TrafficSource::class)],
            'medium' => ['nullable', 'string', 'max:'.self::MEDIUM_MAX_LENGTH],
            'lead_token' => ['nullable', 'string', 'max:'.self::LEAD_TOKEN_MAX_LENGTH],
        ];
    }

    /**
     * The site sends its events with `navigator.sendBeacon`, which survives the navigation a CTA
     * click starts (the router aborts every request in flight) and posts its body as `text/plain`
     * so the browser asks no CORS preflight. Laravel parses only a JSON content type, so a plain
     * body that is JSON is read here, before the rules see it.
     */
    protected function prepareForValidation(): void
    {
        if ($this->isJson()) {
            return;
        }

        $decoded = json_decode((string) $this->getContent(), true);

        if (! is_array($decoded)) {
            return;
        }

        $this->merge($decoded);
    }
}
