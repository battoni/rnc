<?php

namespace App\Modules\Idea\Requests;

use App\Modules\Idea\Enums\IdeaArea;
use App\Modules\Idea\Enums\IdeaType;
use Illuminate\Validation\Rule;

/**
 * The rules an idea obeys, written once — the same reason `NewsRequestRules` exists.
 *
 * Store and Update each carried their own copy of the 2 MB ceiling and the format list. Raise the
 * ceiling on create, forget update, and the two disagree with nothing to catch it. They stay two
 * Requests because Update also accepts `removeAttachment`.
 */
trait IdeaRequestRules
{
    /**
     * 2 MB, and no SVG at any size.
     *
     * The size is about the droplet, which is 24 GB shared with six projects and has filled to
     * zero once already. The format list is about something worse: an SVG is XML that runs
     * script, and an uploaded one served from the app's own origin is stored XSS against every
     * person who opens the idea. `image` alone would let it through — it accepts SVG — so the
     * explicit `mimes` list is the rule that matters, and it is a list of raster formats on
     * purpose.
     */
    private const MAX_ATTACHMENT_KILOBYTES = 2048;

    private const ALLOWED_ATTACHMENT_FORMATS = 'jpg,jpeg,png,webp';

    /** @return array<string, array<int, mixed>> */
    private function ideaFieldRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:5000'],
            'type' => ['required', 'string', Rule::in(IdeaType::values())],
            'area' => ['nullable', 'string', Rule::in(IdeaArea::values())],
            'attachment' => [
                'nullable',
                'file',
                'image',
                'mimes:'.self::ALLOWED_ATTACHMENT_FORMATS,
                'max:'.self::MAX_ATTACHMENT_KILOBYTES,
            ],
        ];
    }
}
