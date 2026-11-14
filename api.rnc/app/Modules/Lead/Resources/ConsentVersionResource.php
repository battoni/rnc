<?php

namespace App\Modules\Lead\Resources;

use App\Modules\Lead\Models\FirmConsentVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One version of the firm's consent on the manager's history (rnc-4 R33): the number, the words as
 * stored (`{escritorio}` unfilled — the screen previews them), when, and who wrote them (null for a
 * seeded version or one copied from the platform default). An allow-list: the author is a name, never
 * the account.
 *
 * @mixin FirmConsentVersion
 */
class ConsentVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $author = $this->author;

        return [
            'version' => $this->version,
            'text' => $this->text,
            'created_at' => $this->created_at->toIso8601String(),
            'author' => $author ? ['id' => $author->id, 'name' => trim($author->name.' '.$author->last_name)] : null,
        ];
    }
}
