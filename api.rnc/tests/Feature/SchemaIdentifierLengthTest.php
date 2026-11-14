<?php

use Illuminate\Support\Facades\Schema;

/*
| MySQL refuses an identifier over 64 characters (SQLSTATE 1059) while SQLite takes any length,
| so a long auto-generated index name passes the whole suite and breaks the first `migrate` on
| the server (rnc-4 F7b: `client_contracts_firm_id_client_id_kind_start_year_end_year_unique`).
| SQLite keeps no foreign key name: the name is rebuilt the way Laravel builds it.
*/
const MYSQL_IDENTIFIER_MAX = 64;

it('keeps every table, column, index and foreign key name within MySQL\'s 64 characters', function () {
    $tooLong = [];

    foreach (Schema::getTableListing(schemaQualified: false) as $table) {
        $names = [$table, ...Schema::getColumnListing($table)];

        foreach (Schema::getIndexes($table) as $index) {
            if ($index['primary']) {
                continue;
            }
            $names[] = $index['name'];
        }

        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            $names[] = $foreignKey['name']
                ?? str_replace(['-', '.'], '_', strtolower($table.'_'.implode('_', $foreignKey['columns']).'_foreign'));
        }

        foreach ($names as $name) {
            if (strlen($name) > MYSQL_IDENTIFIER_MAX) {
                $tooLong[] = "{$table}: {$name} (".strlen($name).')';
            }
        }
    }

    expect($tooLong)->toBe([]);
});
