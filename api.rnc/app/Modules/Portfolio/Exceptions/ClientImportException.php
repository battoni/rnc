<?php

namespace App\Modules\Portfolio\Exceptions;

use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A CSV the import refuses as a whole (rnc-1 R25, A13), with a stable `code` the import dialog
 * branches on (`arcus-api-architecture`, "A domain failure the SPA has to branch on carries a
 * code"). Nothing is written when one is thrown.
 */
class ClientImportException extends RuntimeException
{
    public const CODE_ENCODING = 'import_encoding';

    public const CODE_HEADER = 'import_header';

    public const CODE_EMPTY = 'import_empty';

    public const CODE_TOO_MANY = 'import_too_many';

    public const CODE_INVALID = 'import_invalid';

    private const STATUS_UNPROCESSABLE = 422;

    /** @param  array<string, mixed>  $details */
    public function __construct(string $message, public readonly string $errorCode, public readonly array $details = [])
    {
        parent::__construct($message);
    }

    public static function encoding(): self
    {
        return new self('portfolio.import.encoding', self::CODE_ENCODING);
    }

    /** @param  list<string>  $missing */
    public static function header(array $missing): self
    {
        return new self('portfolio.import.header', self::CODE_HEADER, ['missing' => $missing]);
    }

    public static function empty(): self
    {
        return new self('portfolio.import.empty', self::CODE_EMPTY);
    }

    public static function tooMany(int $limit): self
    {
        return new self('portfolio.import.tooMany', self::CODE_TOO_MANY, ['limit' => $limit]);
    }

    /** @param  list<array{line: int, field: string|null, code: string}>  $errors */
    public static function invalid(array $errors, int $total): self
    {
        return new self('portfolio.import.invalid', self::CODE_INVALID, ['errors' => $errors, 'total_errors' => $total]);
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error(message: $this->getMessage(), extraData: ['code' => $this->errorCode, ...$this->details], status: self::STATUS_UNPROCESSABLE);
    }
}
