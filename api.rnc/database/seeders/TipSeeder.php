<?php

namespace Database\Seeders;

use App\Modules\Tip\Enums\TipEvent;
use App\Modules\Tip\Models\Tip;
use App\Modules\Tip\Models\TipHistory;
use Illuminate\Database\Seeder;

/**
 * The tips that shipped hard-coded in the frontend's locale files, moved into the database where
 * they can be edited without a deploy.
 *
 * This is a one-way migration of CONTENT, not a mirror kept in sync: the i18n keys were deleted
 * in the same change. Re-running this seeder is safe (firstOrCreate on the anchor) but it will
 * NOT overwrite a tip someone has since edited — the whole point of the feature is that the
 * database wins over what a developer once wrote.
 *
 * Version 1 is written for every tip so the original wording stays restorable.
 */
class TipSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::TIPS as $anchorKey => $content) {
            $tip = Tip::firstOrCreate(
                ['anchor_key' => $anchorKey],
                ['content' => $content],
            );

            if (! $tip->wasRecentlyCreated) {
                continue;
            }

            TipHistory::create([
                'tip_id' => $tip->id,
                'content' => $content,
                'event' => TipEvent::Created->value,
            ]);
        }
    }

    /**
     * Anchor key => tip body, as sanitiser-safe HTML.
     *
     * @var array<string, string>
     */
    private const TIPS = [
        /*
         * The one tip the bootstrap ships, and it is deliberately about the FEATURE rather than
         * about a screen. It used to sit on `finance.overview`, explaining demo wallet balances —
         * and it left with that view, which is the argument for anchoring the example somewhere
         * the bootstrap will not delete. Written in English because that is the bootstrap's
         * canonical language; a project translates it by EDITING it, which is the whole point of
         * the feature — the text lives in the database, not in a deploy.
         *
         * The three other anchors ship with no tip on purpose. An anchor is a place help CAN go;
         * inventing text for it would be putting words in a product's mouth.
         */
        'personalization.appearance' => '<p>Contextual tips like this one can be switched off here, and switched back on whenever you want them.</p>',
    ];
}
