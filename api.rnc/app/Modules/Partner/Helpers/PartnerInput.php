<?php

namespace App\Modules\Partner\Helpers;

/**
 * The partner form's normalisation, shared by create and edit (arcus: normalise in
 * `prepareForValidation`): names trimmed, the e-mail lower-cased, an empty phone or city is null.
 * Only the keys that arrived are returned, so a PATCH keeps "absent" apart from "cleared".
 */
final class PartnerInput
{
    private const TRIMMED = ['name', 'specialty', 'email', 'phone', 'city'];

    private const NULLABLE = ['phone', 'city'];

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input): array
    {
        $normalized = [];

        foreach (self::TRIMMED as $key) {
            if (! array_key_exists($key, $input) || ! is_string($input[$key])) {
                continue;
            }

            $value = trim($input[$key]);
            $normalized[$key] = $value === '' && in_array($key, self::NULLABLE, true) ? null : $value;
        }

        if (isset($normalized['email'])) {
            $normalized['email'] = mb_strtolower($normalized['email']);
        }

        return $normalized;
    }
}
