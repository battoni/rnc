<?php

namespace App\Modules\Booking\Exceptions;

use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A booking refusal the slot picker acts on, with a stable `code` next to the i18n key
 * (`arcus-api-architecture`, "A domain failure the SPA has to branch on carries a code").
 */
class BookingException extends RuntimeException
{
    public const CODE_SLOT_TAKEN = 'slot_taken';

    public const CODE_SLOT_UNAVAILABLE = 'slot_unavailable';

    public const CODE_EMAIL_REQUIRED = 'email_required';

    public const CODE_ALREADY_BOOKED = 'already_booked';

    private const STATUS_CONFLICT = 409;

    private const STATUS_UNPROCESSABLE = 422;

    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status,
    ) {
        parent::__construct($message);
    }

    /** Somebody else took it between the picker loading and the click (R16): pick another. */
    public static function slotTaken(): self
    {
        return new self('booking.slotTaken', self::CODE_SLOT_TAKEN, self::STATUS_CONFLICT);
    }

    /** Not a slot the agenda offers: a holiday, a weekend, outside the hours, past the window. */
    public static function slotUnavailable(): self
    {
        return new self('booking.slotUnavailable', self::CODE_SLOT_UNAVAILABLE, self::STATUS_UNPROCESSABLE);
    }

    /** The confirmation needs an address and the lead left only a phone (AM5). */
    public static function emailRequired(): self
    {
        return new self('booking.emailRequired', self::CODE_EMAIL_REQUIRED, self::STATUS_UNPROCESSABLE);
    }

    /** One meeting per lead; changing it is the team's (F5). */
    public static function alreadyBooked(): self
    {
        return new self('booking.alreadyBooked', self::CODE_ALREADY_BOOKED, self::STATUS_CONFLICT);
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error(message: $this->getMessage(), extraData: ['code' => $this->errorCode], status: $this->status);
    }
}
