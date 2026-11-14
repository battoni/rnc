<?php

namespace Database\Seeders\Concerns;

/**
 * The one guard of every seeder that writes invented data (rnc-4 verify P2-C2): a developer's machine
 * (`local`) and the Pest suite (`testing`) only. Anything else — `production`, a `staging` deploy, a
 * name nobody planned for — gets the reference seeders alone, which is what the runbook promises.
 * A seeder that used to refuse only `production` wrote a team, partners, leads and bookable hours on a
 * staging deploy, and `SpecialistSeeder` (no guard at all) rewrote every real specialist's hours to
 * the firm's on each `db:seed`.
 */
final class DemoEnvironment
{
    private const ENVIRONMENTS = ['local', 'testing'];

    public static function allows(): bool
    {
        return app()->environment(self::ENVIRONMENTS);
    }
}
