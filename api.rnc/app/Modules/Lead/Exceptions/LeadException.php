<?php

namespace App\Modules\Lead\Exceptions;

use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A refusal the diagnostic screen has to act on, with a stable `code` next to the i18n key
 * (`arcus-api-architecture`, "A domain failure the SPA has to branch on carries a code").
 */
class LeadException extends RuntimeException
{
    public const CODE_LOCKED = 'lead_locked';

    private const STATUS_CONFLICT = 409;

    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status,
    ) {
        parent::__construct($message);
    }

    /** The meeting is booked: the answers it was booked on no longer change (plan AM3). */
    public static function locked(): self
    {
        return new self('diagnostic.locked', self::CODE_LOCKED, self::STATUS_CONFLICT);
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error(message: $this->getMessage(), extraData: ['code' => $this->errorCode], status: $this->status);
    }
}
