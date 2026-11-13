<?php

namespace App\Modules\Diagnostic\Exceptions;

use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/** A refusal the diagnostic screen acts on, with a stable `code` next to the i18n key. */
class DiagnosticException extends RuntimeException
{
    public const CODE_PROFILE_INCOMPLETE = 'profile_incomplete';

    private const STATUS_UNPROCESSABLE = 422;

    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status = self::STATUS_UNPROCESSABLE,
    ) {
        parent::__construct($message);
    }

    /** No result without the sector, the regime and the band: the screen goes back to the quiz. */
    public static function profileIncomplete(): self
    {
        return new self('diagnostic.profileIncomplete', self::CODE_PROFILE_INCOMPLETE);
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error(message: $this->getMessage(), extraData: ['code' => $this->errorCode], status: $this->status);
    }
}
