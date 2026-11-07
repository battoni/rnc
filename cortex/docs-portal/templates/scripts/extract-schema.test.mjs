import { test } from 'node:test';
import assert from 'node:assert/strict';
import { MACRO_COLUMNS, parseColumns } from './extract-schema.mjs';

test('parseColumns: a Blueprint macro stamps its columns into the table', () => {
  const body = `
    $table->id();
    $table->addressColumns();
    $table->timestamps();
  `;
  const { columns } = parseColumns('venues', body);
  assert.deepEqual(
    columns.map((column) => `${column.name}:${column.type}:${column.nullable ? 'null' : 'required'}`),
    [
      'id:bigint:required',
      'cep:varchar:null',
      'street:varchar:required',
      'number:varchar:required',
      'complement:varchar:null',
      'district:varchar:required',
      'city:varchar:required',
      'state:varchar(2):required',
      'lat:decimal(10,7):null',
      'lng:decimal(10,7):null',
      'created_at:timestamp:null',
      'updated_at:timestamp:null',
    ],
  );

  const locality = parseColumns('regions', '$table->localityColumns();').columns.map((column) => column.name);
  assert.deepEqual(locality, ['state', 'city', 'cep', 'region', 'lat', 'lng']);
});

// The case above tests the parser alone, because the template has no API of its own. docs.rnc's
// copy of this file also checks MACRO_COLUMNS against the macros the API registers — keep that check
// when the project has an API to read.
