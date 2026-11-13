<?php

namespace App\Modules\Fiscal\Exceptions;

use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A Fiscal action the screen has to branch on (arcus "A domain failure the SPA has to branch on
 * carries a code"): "Propor revisão" on a client with nothing bought from Simples (R22) — the button
 * is disabled there, so the 422 only answers a stale screen or a direct call.
 */
class FiscalException extends RuntimeException
{
    public const CODE_NO_SIMPLES_PURCHASES = 'no_simples_purchases';

    private const STATUS_UNPROCESSABLE = 422;

    public function __construct(string $message, public readonly string $errorCode)
    {
        parent::__construct($message);
    }

    public static function noSimplesPurchases(): self
    {
        return new self('fiscal.noSimplesPurchases', self::CODE_NO_SIMPLES_PURCHASES);
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error(message: $this->getMessage(), extraData: ['code' => $this->errorCode], status: self::STATUS_UNPROCESSABLE);
    }
}
