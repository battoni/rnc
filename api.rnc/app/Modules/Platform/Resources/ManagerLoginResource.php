<?php

namespace App\Modules\Platform\Resources;

use App\Models\User;
use App\Modules\Firm\DTOs\ManagerLogin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A manager login the console made or reset (rnc-4 R25): who, whether new, and the password — in
 * this one answer only; it is stored nowhere but as a hash. Also the manager part of a new firm.
 *
 * @property ManagerLogin $resource
 */
class ManagerLoginResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'manager' => self::managerOf($this->resource->manager),
            'password' => $this->resource->password,
            'created' => $this->resource->created,
        ];
    }

    /** @return array{id: int, username: string, email: string|null} */
    public static function managerOf(User $manager): array
    {
        return ['id' => $manager->id, 'username' => $manager->username, 'email' => $manager->email];
    }
}
