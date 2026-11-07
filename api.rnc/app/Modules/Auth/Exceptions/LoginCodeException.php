<?php

namespace App\Modules\Auth\Exceptions;

use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A refusal of the phone-code sign-in that the screen has to ACT on, so it carries a stable
 * `code` next to the i18n key: an expired code sends the person back to ask for a new one, a
 * wrong one clears the boxes, a burned one (the fifth wrong guess) sends the person back for a new
 * code, a delivery failure says to try later.
 */
class LoginCodeException extends RuntimeException
{
    public const CODE_INVALID = 'invalid_code';

    public const CODE_EXPIRED = 'code_expired';

    public const CODE_LOCKED = 'code_locked';

    public const CODE_DELIVERY_FAILED = 'delivery_failed';

    private const STATUS_UNPROCESSABLE = 422;

    private const STATUS_SERVICE_UNAVAILABLE = 503;

    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status = self::STATUS_UNPROCESSABLE,
    ) {
        parent::__construct($message);
    }

    public static function invalid(): self
    {
        return new self('auth.flow.login.codeInvalid', self::CODE_INVALID);
    }

    public static function expired(): self
    {
        return new self('auth.flow.login.codeExpired', self::CODE_EXPIRED);
    }

    public static function locked(): self
    {
        return new self('auth.flow.login.codeLocked', self::CODE_LOCKED);
    }

    public static function deliveryFailed(): self
    {
        return new self('auth.flow.login.codeDeliveryFailed', self::CODE_DELIVERY_FAILED, self::STATUS_SERVICE_UNAVAILABLE);
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error(message: $this->getMessage(), extraData: ['code' => $this->errorCode], status: $this->status);
    }
}
