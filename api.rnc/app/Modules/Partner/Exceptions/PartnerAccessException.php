<?php

namespace App\Modules\Partner\Exceptions;

use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A refusal the "Acesso ao portal" form acts on (rnc-2 R2), with a stable `code` next to the i18n
 * key (`arcus-api-architecture`, "A domain failure the SPA has to branch on carries a code").
 */
class PartnerAccessException extends RuntimeException
{
    public const CODE_ACCESS_EXISTS = 'partner_access_exists';

    public const CODE_ACCESS_MISSING = 'partner_access_missing';

    public const CODE_PARTNER_INACTIVE = 'partner_inactive';

    private const STATUS_CONFLICT = 409;

    private const STATUS_NOT_FOUND = 404;

    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status,
    ) {
        parent::__construct($message);
    }

    /** One partner, at most one active login (R1, D1). */
    public static function exists(): self
    {
        return new self('partners.access.exists', self::CODE_ACCESS_EXISTS, self::STATUS_CONFLICT);
    }

    /** Nothing to regenerate or revoke: the partner has no active login. */
    public static function missing(): self
    {
        return new self('partners.access.missing', self::CODE_ACCESS_MISSING, self::STATUS_NOT_FOUND);
    }

    /** A deactivated partner gets no login — its old one was closed with it (R2). */
    public static function partnerInactive(): self
    {
        return new self('partners.access.partnerInactive', self::CODE_PARTNER_INACTIVE, self::STATUS_CONFLICT);
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error(message: $this->getMessage(), extraData: ['code' => $this->errorCode], status: $this->status);
    }
}
