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

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'specialist_id' => ['required', 'integer'],
            'starts_at' => ['required', 'string', 'date'],
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
