import assert from 'node:assert/strict';
import test from 'node:test';
// The navigation module reads the embedded translation catalog at import time.
globalThis.document = { getElementById: () => null };
const { getDashboardNavigationView } = await import('../../public/assets/js/modules/navigation.js');
delete globalThis.document;

test('recurring dashboard URLs preserve same-origin navigation and filter state', () => {
  const base = 'https://mytodo.php/';
  for (const path of ['/recurring-tasks', '/recurring-tasks/?filter=paused', '/recurring-tasks?filter=active&partial=1', '/legacy?view=recurring-tasks']) {
    assert.equal(getDashboardNavigationView(path, base), 'recurring-tasks');
  }
});

test('recurring navigation rejects another origin', () => {
  assert.equal(getDashboardNavigationView('https://example.com/recurring-tasks', 'https://mytodo.php/'), null);
});
