<?php

namespace App\Modules\Partner\Exceptions;

use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A referral refusal the routing panel or the partner's portal acts on, with a stable `code` next to the i18n key
 * (`arcus-api-architecture`, "A domain failure the SPA has to branch on carries a code").
 */
class ReferralException extends RuntimeException
{
    public const CODE_CONSENT_REQUIRED = 'consent_required';

    public const CODE_ALREADY_REFERRED = 'already_referred';

    public const CODE_INVALID_TRANSITION = 'invalid_transition';

    private const STATUS_CONFLICT = 409;

    private const STATUS_UNPROCESSABLE = 422;

    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status,
    ) {
        parent::__construct($message);
    }

    /** Nobody is introduced to a partner without having said yes (R22, AM12). */
    public static function consentRequired(): self
    {
        return new self('partners.routing.consentRequired', self::CODE_CONSENT_REQUIRED, self::STATUS_UNPROCESSABLE);
    }

    /** This lead was already sent to this partner: the trail holds one line per pair. */
    public static function alreadyReferred(): self
    {
        return new self('partners.routing.alreadyReferred', self::CODE_ALREADY_REFERRED, self::STATUS_CONFLICT);
    }

    /** A move off the referral's graph (rnc-2 R7): a skipped step, a step back, or out of a terminal status. */
    public static function invalidTransition(): self
    {
        return new self('referral.invalidTransition', self::CODE_INVALID_TRANSITION, self::STATUS_UNPROCESSABLE);
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error(message: $this->getMessage(), extraData: ['code' => $this->errorCode], status: $this->status);
    }
}
