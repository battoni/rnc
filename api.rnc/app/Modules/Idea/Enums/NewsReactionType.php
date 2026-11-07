<?php

namespace App\Modules\Idea\Enums;

/**
 * The three ways to applaud an entry.
 *
 * Three and no more, on purpose. A reaction set that grows into a drawer of twenty emoji stops
 * being a gesture and becomes a decision, and a decision is exactly the friction applause must not
 * have. These three cover the range people actually mean: CLAP is "well done", HEART is "I love
 * this", ROCKET is "this changes things".
 *
 * The values are stored as strings rather than an integer, because a reaction column read straight
 * out of the database should say what it is.
 */
enum NewsReactionType: string
{
    case CLAP = 'clap';
    case HEART = 'heart';
    case ROCKET = 'rocket';

    /** @return string[] */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
