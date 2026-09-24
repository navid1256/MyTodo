import assert from 'node:assert/strict';
import test from 'node:test';

globalThis.document = { getElementById: () => null };
const { updateRecurringRule } = await import('../../public/assets/js/services/repeat-service.js');
delete globalThis.document;

test('recurring rule edit posts the rule endpoint with rule payload and CSRF token', async () => {
  const requests = [];
  globalThis.fetch = async (url, options) => {
    requests.push({ url, options });
    return { ok: true, status: 200, text: async () => JSON.stringify({ success: true, status: 'paused' }) };
  };

  const formData = new FormData();
  formData.set('csrf_token', 'csrf');
  formData.set('repeat_rule_id', '9');
  formData.set('mode', 'rule');
  formData.set('task_title', 'Updated series');
  formData.set('due_at', '2026-09-30T09:00');
  formData.set('has_time', '1');
  formData.set('repeat_config', JSON.stringify({ frequency: 'weekly', week_days: [3], ends: { type: 'endlessly' } }));
  formData.set('reminders', JSON.stringify([{ value: 30, unit: 'minutes' }]));

  const result = await updateRecurringRule(formData);
  assert.equal(result.status, 'paused');
  assert.equal(requests.length, 1);
  assert.equal(requests[0].url, '/api/repeat-rules/update');
  assert.equal(requests[0].options.method, 'POST');
  assert.equal(requests[0].options.body.get('repeat_rule_id'), '9');
  assert.equal(requests[0].options.body.get('mode'), 'rule');
});
