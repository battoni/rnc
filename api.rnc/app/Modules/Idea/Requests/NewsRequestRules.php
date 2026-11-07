<?php

namespace App\Modules\Idea\Requests;

use App\Modules\Idea\Enums\IdeaArea;
use Illuminate\Validation\Rule;

/**
 * The rules a Novidades entry obeys, written once.
 *
 * Store and Update were byte-for-byte identical files, which meant the 2 MB ceiling and the four
 * allowed formats existed twice. Raise the ceiling on create, forget update, and the two disagree
 * with nothing to catch it.
 *
 * They are still two Requests, because they differ in exactly one rule and it matters: see
 * `publishRule()`.
 */
trait NewsRequestRules
{
    /** Same ceiling and same four raster formats as an idea's screenshot, for the same reasons. */
    private const MAX_COVER_KILOBYTES = 2048;

    private const ALLOWED_COVER_FORMATS = 'jpg,jpeg,png,webp';

    /** @return array<string, array<int, string>> */
    private function newsFieldRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:140'],
            'subtitle' => ['nullable', 'string', 'max:200'],
            // The editor's HTML. Length is generous because markup inflates it; what it may
            // CONTAIN is decided by NewsBodySanitizer, not here.
            'body' => ['required', 'string', 'max:60000'],
            // Where the entry sends the reader. http(s) only — the scheme is the whole risk.
            'link' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            'ideaId' => ['nullable', 'integer', 'exists:ideas,id'],
            'cover' => [
                'nullable',
                'file',
                'image',
                'mimes:'.self::ALLOWED_COVER_FORMATS,
                'max:'.self::MAX_COVER_KILOBYTES,
            ],
            'removeCover' => ['nullable', 'boolean'],
            /*
             * Which parts of the product the entry is about. The vocabulary is the Ideas board's,
             * on purpose: an idea about a screen and the entry announcing it must not be filed
             * under two different words.
             */
            'areas' => ['nullable', 'array', 'max:9'],
            'areas.*' => ['required', 'string', Rule::enum(IdeaArea::class)],
        ];
    }
}
