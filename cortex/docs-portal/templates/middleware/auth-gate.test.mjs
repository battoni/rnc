import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
  hasSessionCookie,
  isStaffUser,
  userFromMeBody,
  gateDecision,
  buildLoginRedirect,
} from './auth-gate.mjs';

test('hasSessionCookie matches only a real cookie boundary', () => {
  assert.equal(hasSessionCookie('{{API_SESSION_COOKIE}}=abc'), true);
  assert.equal(hasSessionCookie('foo=1; {{SLUG}}-session=abc'), true);
  assert.equal(hasSessionCookie('not_{{SLUG}}-session=abc'), false);
  assert.equal(hasSessionCookie('other=1'), false);
  assert.equal(hasSessionCookie(''), false);
  assert.equal(hasSessionCookie(null), false);
});

test('userFromMeBody unwraps the ApiResponse envelope', () => {
  assert.deepEqual(userFromMeBody({ data: { role: 'Administrator' } }), { role: 'Administrator' });
  assert.deepEqual(userFromMeBody({ role: 'Administrator' }), { role: 'Administrator' });
  assert.equal(userFromMeBody(null), null);
  assert.equal(userFromMeBody('nope'), null);
});

test('isStaffUser accepts the {{STAFF_PERMISSION}} permission', () => {
  assert.equal(isStaffUser({ role: 'Administrator', permissions: ['{{STAFF_PERMISSION}}'] }), true);
});

test('isStaffUser accepts the staff slug, and a role name only when one is configured', () => {
  assert.equal(isStaffUser({ role: 'Docs readers', permissions: [] }, { roleName: 'Docs readers' }), true);
  assert.equal(isStaffUser({ role: 'Administrator', role_slug: '{{STAFF_ROLE_SLUG}}', permissions: [] }), true);
});

test('isStaffUser rejects a plain member', () => {
  assert.equal(isStaffUser({ role: 'Member', role_slug: 'member', permissions: [] }), false);
  assert.equal(isStaffUser(null), false);
});

test('gateDecision serves a staff 200 and redirects everything else', () => {
  assert.equal(gateDecision(200, { data: { role: 'Member', role_slug: '{{STAFF_ROLE_SLUG}}', permissions: [] } }, { roleSlug: '{{STAFF_ROLE_SLUG}}' }), 'serve');
  assert.equal(gateDecision(200, { data: { role: 'Member', permissions: [] } }), 'redirect');
  assert.equal(gateDecision(401, null), 'redirect');
  assert.equal(gateDecision(null, null), 'redirect');
});

test('gateDecision does not open on a role merely NAMED "STAFF" when no role name is configured', () => {
  assert.equal(gateDecision(200, { data: { role: 'STAFF', role_slug: 'member', permissions: [] } }), 'redirect');
});

test('buildLoginRedirect carries the return URL', () => {
  const url = buildLoginRedirect('https://docs.{{SLUG}}.com/guide/', 'https://app.{{SLUG}}.com/login');
  const parsed = new URL(url);
  assert.equal(parsed.pathname, '/login');
  assert.equal(parsed.searchParams.get('redirect'), 'https://docs.{{SLUG}}.com/guide/');
});
