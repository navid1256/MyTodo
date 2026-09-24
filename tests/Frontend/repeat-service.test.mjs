import assert from 'node:assert/strict';
import test from 'node:test';

globalThis.document = { getElementById: () => null };
const { pauseRepeatRule, resumeRepeatRule, cancelRepeatRule } = await import('../../public/assets/js/services/repeat-service.js');
delete globalThis.document;

function jsonResponse(payload, ok = true) {
  return { ok, status: ok ? 200 : 422, text: async () => JSON.stringify(payload) };
}

test('pause posts the owned rule id and CSRF token to the pause endpoint', async () => {
  const requests = [];
  globalThis.fetch = async (url, options) => {
    requests.push({ url, options });
    return jsonResponse({ success: true, status: 'paused', deleted_task_ids: [] });
  };

  await pauseRepeatRule(12, 'csrf-value');

  assert.equal(requests.length, 1);
  assert.equal(requests[0].url, '/api/repeat-rules/pause');
  assert.equal(requests[0].options.method, 'POST');
  assert.equal(requests[0].options.headers.Accept, 'application/json');
  assert.equal(requests[0].options.body.get('repeat_rule_id'), '12');
  assert.equal(requests[0].options.body.get('csrf_token'), 'csrf-value');
});

test('resume and cancel use their lifecycle endpoints with the same request shape', async () => {
  const requests = [];
  globalThis.fetch = async (url, options) => {
    requests.push({ url, options });
    return jsonResponse({ success: true, status: url.endsWith('resume') ? 'active' : 'cancelled' });
  };

  await resumeRepeatRule(8, 'csrf-resume');
  await cancelRepeatRule(9, 'csrf-cancel');

  assert.deepEqual(requests.map(({ url }) => url), [
    '/api/repeat-rules/resume',
    '/api/repeat-rules/cancel'
  ]);
  assert.equal(requests[0].options.body.get('repeat_rule_id'), '8');
  assert.equal(requests[0].options.body.get('csrf_token'), 'csrf-resume');
  assert.equal(requests[1].options.body.get('repeat_rule_id'), '9');
  assert.equal(requests[1].options.body.get('csrf_token'), 'csrf-cancel');
});
