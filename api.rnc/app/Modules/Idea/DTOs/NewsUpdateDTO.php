<?php

namespace App\Modules\Idea\DTOs;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;

class NewsUpdateDTO extends Data
{
    /**
     * `publish` decides whether the entry goes out now or waits as a draft; the timestamp is the
     * service's to write, never the client's.
     *
     * `removeCover` is separate from `cover` because "sent no file" and "delete the cover" are
     * different intents that a multipart request cannot otherwise tell apart — an absent field is
     * how every other unchanged field arrives.
     *
     * `clearAreas` is the same distinction one field over. Multipart has no way to write an empty
     * array, so a writer who removes the last tag sends nothing — which is exactly how a request
     * that is not about areas arrives, and `syncAreas` would leave the tags standing.
     */
    public function __construct(
        public string $title,
        public ?string $subtitle,
        public string $body,
        public ?string $link,
        public ?int $ideaId,
        public ?bool $publish,
        public ?UploadedFile $cover,
        public ?bool $removeCover,
        /** @var array<int, string>|null */
        public ?array $areas,
        public ?bool $clearAreas,
    ) {}
}
