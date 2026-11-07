<?php

namespace App\Modules\Assistant\Exceptions;

use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * An assistant refusal the screens act on, with a stable `code` next to the i18n key
 * (`arcus-api-architecture`, "A domain failure the SPA has to branch on carries a code").
 */
class AssistantException extends RuntimeException
{
    public const CODE_NOT_REVIEWER = 'not_reviewer';

    public const CODE_NOT_WRITER = 'not_writer';

    public const CODE_NOT_SPECIALIST = 'not_specialist';

    public const CODE_RETIRED = 'retired';

    private const STATUS_FORBIDDEN = 403;

    private const STATUS_UNPROCESSABLE = 422;

    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status,
    ) {
        parent::__construct($message);
    }

    /** Only a specialist who did not write the note's last change reviews it (R11, W10). */
    public static function notReviewer(): self
    {
        return new self('assistant.notReviewer', self::CODE_NOT_REVIEWER, self::STATUS_FORBIDDEN);
    }

    /** The knowledge base is `assistant.manage`'s or a specialist's (R10, AM4). */
    public static function notWriter(): self
    {
        return new self('assistant.notWriter', self::CODE_NOT_WRITER, self::STATUS_FORBIDDEN);
    }

    /** A question goes only to an active specialist of the firm (R13). */
    public static function notSpecialist(): self
    {
        return new self('assistant.notSpecialist', self::CODE_NOT_SPECIALIST, self::STATUS_UNPROCESSABLE);
    }

    /** An aposentada note is neither edited nor reviewed (W55). */
    public static function retired(): self
    {
        return new self('assistant.retired', self::CODE_RETIRED, self::STATUS_UNPROCESSABLE);
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error(message: $this->getMessage(), extraData: ['code' => $this->errorCode], status: $this->status);
    }
}
