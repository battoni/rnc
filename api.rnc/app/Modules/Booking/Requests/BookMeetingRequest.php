<?php

namespace App\Modules\Booking\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The slot the visitor picked (`POST /api/public/diagnostics/{token}/booking`, R16): a specialist,
 * the start as ISO-8601 with its offset, and the e-mail when the lead has none yet (AM5). Whether
 * the slot is free, and whether an e-mail is needed, is the service's to answer.
 */
class BookMeetingRequest extends FormRequest
{
    private const EMAIL_MAX_LENGTH = 255;

    /**
     * ISO-8601 with an offset or `Z`, as the availability answers it: a time without its zone would
     * be read in UTC and refused as a missing slot, which tells the visitor the wrong thing.
     */
    private const ISO_8601_WITH_OFFSET = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:\d{2})$/';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'specialist_id' => ['required', 'integer'],
            'starts_at' => ['required', 'string', 'regex:'.self::ISO_8601_WITH_OFFSET, 'date'],
            'email' => ['nullable', 'string', 'email', 'max:'.self::EMAIL_MAX_LENGTH],
        ];
    }

    /** An e-mail's case and its stray spaces are not part of it. */
    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (! is_string($email)) {
            return;
        }

        $this->merge(['email' => mb_strtolower(trim($email)) ?: null]);
    }
}
