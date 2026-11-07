<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Where the SPA lives
    |--------------------------------------------------------------------------
    |
    | The API sometimes has to send a person to the app — a share link is read by
    | a crawler here and by a human there. Read through config and never through
    | `env()` at the call site: `php artisan config:cache` runs on every deploy,
    | and after it `env()` outside a config file answers null.
    |
    | The file is named after the bootstrap on purpose: `specialize()` rewrites
    | the word, so each project receives it as `config/<project>.php` with the
    | call sites already pointing at the right key.
    |
    */

    'frontend_url' => rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/'),

    /*
    |--------------------------------------------------------------------------
    | The brand, as an e-mail can render it
    |--------------------------------------------------------------------------
    |
    | An e-mail client has no CSS variables, no stylesheet and no theme: every
    | colour has to arrive inline, as a literal. So the two or three values the
    | mail chrome needs live here rather than being read from the app's theme,
    | which the API cannot see anyway.
    |
    | Keep these in step with `app.rnc/src/styles/theme/themes/<theme>.css` by
    | hand. It is three values, and they change about as often as a logo does.
    |
    */

    'brand' => [
        'name' => env('BRAND_NAME', 'Franco Contabilidade'),

        /*
         * These are sampled from the LOGO FILE, not from the theme CSS, and the difference
         * matters by exactly one hex unit: the theme says #c0e021 and #141413, the asset is
         * drawn on #bfe021 and #141313. Invisible on its own — but the logo is a square tile
         * with its background baked in, so a band painted in the theme's value leaves a
         * faint seam around it. The band takes the asset's colour and the tile disappears
         * into it.
         */
        'accent' => env('BRAND_ACCENT', '#14c3ff'),
        'ink' => env('BRAND_INK', '#00375a'),

        /*
         * The mark, embedded in the message rather than linked. A linked image needs a
         * public URL that is still reachable years later; an embedded one travels with the
         * mail. Relative to `public/`, so a project drops its own file in and changes
         * nothing else.
         *
         * Empty means "no logo file": the band shows the brand's initial in an accent tile
         * instead (rnc-1 F4 — Franco's mark is text, W6). Set BRAND_LOGO to a path to use one.
         *
         * Cropped to the glyph on purpose. The app's logo tile is a 500px square in which
         * the mark occupies 27% of the width — at a header's 40px that leaves a 10px glyph
         * beside 19px text, which reads as a speck rather than a logo.
         */
        'logo' => env('BRAND_LOGO') ?: null,
    ],

];
