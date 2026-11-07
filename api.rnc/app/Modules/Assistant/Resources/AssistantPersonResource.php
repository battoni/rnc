<?php

namespace App\Modules\Assistant\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A person as the assistant's screens name them — a reviewer, an editor, a specialist: the id and the
 * full name, nothing of the account.
 *
 * @mixin User
 */
class AssistantPersonResource extends JsonResource
{
    /** @return array{id: int, name: string}|null */
    public static function of(?User $user): ?array
    {
        return $user === null ? null : ['id' => $user->id, 'name' => trim($user->name.' '.$user->last_name)];
    }

    /** @return array{id: int, name: string} */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => trim($this->name.' '.$this->last_name)];
    }
}
