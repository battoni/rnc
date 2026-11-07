import { test } from 'node:test';
import assert from 'node:assert/strict';
import { findUnmapped, moduleForTable, MODULES } from './modules.mjs';

test('findUnmapped: reports a table with no owner and an endpoint or view outside MODULES', () => {
  const known = MODULES[0].id;
  const problems = findUnmapped({
    tables: {
      owned: { name: 'owned', module: known },
      orphan: { name: 'orphan', module: moduleForTable('a_table_nobody_mapped') },
    },
    endpoints: [
      { verb: 'GET', path: '/fine', module: known },
      { verb: 'GET', path: '/lost', module: 'lost' },
    ],
    views: [{ routeName: 'stray', module: 'stray' }],
  });

  assert.deepEqual(problems, [
    'table orphan: no owner in TABLE_MODULE',
    "endpoint GET /lost: module 'lost' is not in MODULES",
    "view stray: module 'stray' is not in MODULES",
  ]);
});

// The end-to-end cases — every table, endpoint and view of the real code mapped, and a section on the
// reference pages for each module — need the project's code; docs.rnc's copy of this file shows them.
