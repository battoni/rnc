<?php

namespace App\Modules\Auth\Exceptions;

use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A role the domain will not delete. It renders itself so the controller stays thin, and keeps the
 * statuses the screen already reads: 403 for the administrator role, 409 for a role in use, with
 * the names of the people who still hold it. The message is an i18n key.
 */
class RoleDeletionException extends RuntimeException
{
    private const STATUS_FORBIDDEN = 403;

    private const STATUS_CONFLICT = 409;

    /**
     * @param  array<int, string>  $holders
     */
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly array $holders = [],
    ) {
        parent::__construct($message);
    }

    public static function administrator(): self
    {
        return new self('rolesAndPermissions.cannotDeleteAdministratorRole', self::STATUS_FORBIDDEN);
    }

    /**
     * @param  array<int, string>  $holders
     */
    public static function inUse(array $holders): self
    {
        return new self('rolesAndPermissions.cannotDeleteRole', self::STATUS_CONFLICT, $holders);
    }

    public function render(): JsonResponse
    {
        $extraData = $this->holders === [] ? null : ['users' => $this->holders];

        return ApiResponse::error(message: $this->getMessage(), extraData: $extraData, status: $this->status);
    }
}
