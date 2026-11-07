<?php

namespace App\Modules\Idea\Enums;

/**
 * Which surface of the product an idea is about — the same list the navigation offers, so a
 * filter on the board and a screen in the sidebar are recognisably the same thing.
 *
 * Nullable on the row and defaulted to GENERAL in the form: making someone classify their own
 * feedback before we will accept it is how feedback stops arriving. The quick-capture dialog
 * fills this in from the route the person was on, which is the only moment the answer is free.
 *
 * THE BOOTSTRAP SHIPS ONLY THE NEUTRAL CASES. A product adds its own screens by editing this
 * enum — a PHP backed enum cannot be extended from outside, so there is no local extension
 * point and there is no pretending otherwise. Two consequences worth knowing before you add
 * one:
 *
 *   1. The file is MERGE in the sync manifest, so a project that edits it will meet this
 *      enum again on every rnc update, and will have to merge.
 *   2. That divergence is NOT silent: editing the unit bumps its version above `based-on`,
 *      which is exactly the gap the propagation triage flags for a closer look.
 *
 * The upside of the friction is that adding an area becomes a decision instead of a habit —
 * if a case is generic enough for the family, it belongs here, upstream, for everyone.
 */
enum IdeaArea: string
{
    case HOME = 'home';
    case ACCOUNT = 'account';
    case GENERAL = 'general';

    /** @return string[] */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
