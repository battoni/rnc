<?php

namespace App\Modules\Content\Resources;

use App\Modules\Content\Models\SectorPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One sector page, whole, for its own route. An allow-list: no id, no firm, no timestamps.
 *
 * @mixin SectorPage
 */
class PublicSectorPageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'label' => $this->label,
            'title' => $this->title,
            'lede' => $this->lede,
            'facts' => array_map(fn (array $fact) => [
                'date' => $fact['date'],
                'text' => $fact['text'],
                'source' => ['label' => $fact['source']['label'], 'url' => $fact['source']['url'] ?? null],
            ], $this->facts),
            'seo' => ['title' => $this->seo['title'], 'description' => $this->seo['description']],
        ];
    }
}
