<?php

namespace App\Modules\Channel\Exceptions;

use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A channel refusal the "Canal RNC" screen acts on, with a stable `code` next to the i18n key
 * (`arcus-api-architecture`, "A domain failure the SPA has to branch on carries a code"): the screen
 * that sent a stale advance reloads the board instead of showing a sentence.
 */
class ChannelException extends RuntimeException
{
    public const CODE_INVALID_TRANSITION = 'invalid_transition';

    private const STATUS_UNPROCESSABLE = 422;

    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status,
    ) {
        parent::__construct($message);
    }

    /** A move off the channel's graph (R7): a skipped stage, a step back, staying put, or out of ativo. */
    public static function invalidTransition(): self
    {
        return new self('channel.invalidTransition', self::CODE_INVALID_TRANSITION, self::STATUS_UNPROCESSABLE);
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error(message: $this->getMessage(), extraData: ['code' => $this->errorCode], status: $this->status);
    }
}
