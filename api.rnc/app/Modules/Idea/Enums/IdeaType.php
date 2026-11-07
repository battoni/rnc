<?php

namespace App\Modules\Idea\Enums;

/**
 * What the person is telling us. Three kinds, because the maintainer reads them differently:
 * a PROBLEM is triaged against the current build, an IMPROVEMENT changes something that already
 * works, and an IDEA proposes something that does not exist yet.
 *
 * The board is called "Ideias" and one of its types is also `idea` — that is deliberate, not an
 * oversight. The screen's name is the invitation ("tell me an idea"); the type is the shape of
 * what arrived.
 */
enum IdeaType: string
{
    case IMPROVEMENT = 'improvement';
    case PROBLEM = 'problem';
    case IDEA = 'idea';

    /** @return string[] */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
