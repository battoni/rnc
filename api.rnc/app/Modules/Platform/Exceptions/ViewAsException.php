<?php

namespace App\Modules\Platform\Exceptions;

use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A view-as start the console has to branch on (rnc-4 R26; arcus "a domain failure the SPA has to
 * branch on carries a code"): one is already active in this session (409 — never nested), or the firm
 * named is the platform firm itself (422 — the operator already works there outside view-as).
 */
class ViewAsException extends RuntimeException
{
    public const CODE_ACTIVE = 'view_as_active';

    public const CODE_PLATFORM_FIRM = 'view_as_platform_firm';

    private const STATUS_CONFLICT = 409;

    private const STATUS_UNPROCESSABLE = 422;

    public function __construct(string $message, public readonly string $errorCode, public readonly int $status)
    {
        parent::__construct($message);
    }

    public static function alreadyActive(): self
    {
        return new self('platform.viewAsActive', self::CODE_ACTIVE, self::STATUS_CONFLICT);
    }

    public static function platformFirm(): self
    {
        return new self('platform.viewAsPlatformFirm', self::CODE_PLATFORM_FIRM, self::STATUS_UNPROCESSABLE);
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error(message: $this->getMessage(), extraData: ['code' => $this->errorCode], status: $this->status);
    }
}
