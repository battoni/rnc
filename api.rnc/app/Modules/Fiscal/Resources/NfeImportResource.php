<?php

namespace App\Modules\Fiscal\Resources;

use App\Modules\Fiscal\Models\NfeImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One import batch (R17): who, how, when (UTC, ISO-8601 with offset), files read, documents stored
 * and every refusal `{file, code}`. Never a path, never a byte of a file.
 *
 * @mixin NfeImport
 */
class NfeImportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'source' => $this->source,
            'user_id' => $this->user_id === null ? null : (int) $this->user_id,
            'files' => (int) $this->files,
            'stored' => (int) $this->stored,
            'refused' => $this->refused,
            'at' => $this->at->toIso8601String(),
        ];
    }
}
