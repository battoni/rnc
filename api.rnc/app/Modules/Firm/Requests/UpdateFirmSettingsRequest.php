<?php

namespace App\Modules\Firm\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The firm's contact data (`PATCH /api/firm/settings`, rnc-4 R30): address, phone, mailbox, business
 * hours and the public footer's credentials line (W31). Every field is optional; a null clears it,
 * except the hours, which the site and a new specialist's week read (an empty list is a closed
 * week). A field with no rule here — the look, the slug, the modules — never reaches the service.
 *
 * Hours are windows `{weekdays, from, to}`: ISO weekdays (1 = segunda) and `HH:MM`, closing after
 * opening — the shape `firms.business_hours` already holds.
 */
class UpdateFirmSettingsRequest extends FormRequest
{
    /** The `string` columns' width (`address`, `mailbox`, `credentials`). */
    public const TEXT_MAX_LENGTH = 255;

    public const PHONE_MAX_LENGTH = 30;

    /** One window per weekday is the most a week needs. */
    public const MAX_WINDOWS = 7;

    private const TIME_FORMAT = 'date_format:H:i';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'address' => ['sometimes', 'nullable', 'string', 'max:'.self::TEXT_MAX_LENGTH],
            'phone' => ['sometimes', 'nullable', 'string', 'max:'.self::PHONE_MAX_LENGTH],
            'mailbox' => ['sometimes', 'nullable', 'string', 'email', 'max:'.self::TEXT_MAX_LENGTH],
            'credentials' => ['sometimes', 'nullable', 'string', 'max:'.self::TEXT_MAX_LENGTH],
            'business_hours' => ['sometimes', 'array', 'max:'.self::MAX_WINDOWS],
            'business_hours.*.weekdays' => ['required', 'array', 'min:1'],
            'business_hours.*.weekdays.*' => ['required', 'integer', 'distinct', 'between:1,7'],
            'business_hours.*.from' => ['required', 'string', self::TIME_FORMAT],
            'business_hours.*.to' => ['required', 'string', self::TIME_FORMAT, 'after:business_hours.*.from'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['address', 'phone', 'mailbox', 'credentials'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $trimmed = trim($value);
                $this->merge([$field => $trimmed === '' ? null : $trimmed]);
            }
        }
    }
}
