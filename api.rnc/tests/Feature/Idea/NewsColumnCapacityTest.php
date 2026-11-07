<?php

use Illuminate\Support\Facades\DB;

/*
 * Review of 2026-09-17 (ARC-09): `news.link` was varchar(255) while the request accepts 2048
 * characters, and `news.body` was TEXT (65,535 BYTES) while the request accepts 60,000
 * characters — accented text passes that in bytes. On MySQL strict mode both land as
 * "Data too long" and a 500 after validation said yes. SQLite ignores column lengths, so the
 * suite could never see it: this test compiles the create migrations with the MySQL grammar
 * (pretend mode, no server needed) and reads the column types from the SQL.
 */

function newsCapacityMysqlSql(string $migration): string
{
    $instance = require database_path("migrations/{$migration}");
    $previous = DB::getDefaultConnection();

    DB::setDefaultConnection('mysql');

    try {
        $log = DB::connection('mysql')->pretend(fn () => $instance->up());
    } finally {
        DB::setDefaultConnection($previous);
    }

    return collect($log)->pluck('query')->implode("\n");
}

describe('the create migrations hold what validation accepts', function () {
    it('gives news.link room for the 2048 characters the request allows', function () {
        expect(newsCapacityMysqlSql('2026_09_01_100400_create_news_table.php'))
            ->toContain('`link` varchar(2048) null');
    });

    it('stores a 60,000-character body in more than 65,535 bytes', function () {
        expect(newsCapacityMysqlSql('2026_09_01_100400_create_news_table.php'))
            ->toContain('`body` mediumtext not null');
    });

    it('stores a 20,000-character tip, which can exceed 65,535 bytes, in tips and in its history', function (string $migration) {
        expect(newsCapacityMysqlSql($migration))->toContain('`content` mediumtext not null');
    })->with([
        'tips' => '2026_06_11_000001_create_tips_table.php',
        'tip_histories' => '2026_06_11_000002_create_tip_histories_table.php',
    ]);
});
