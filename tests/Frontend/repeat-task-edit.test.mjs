import assert from 'node:assert/strict';
import test from 'node:test';

globalThis.document = { getElementById: () => null };
const { updateRecurringTask } = await import('../../public/assets/js/services/repeat-service.js');
delete globalThis.document;

test('single recurring edit posts the canonical form payload without a page reload', async () => {
  const requests = [];
  globalThis.fetch = async (url, options) => {
    requests.push({ url, options });
    return {
      ok: true,
      status: 200,
      text: async () => JSON.stringify({
        success: true,
        scope: 'single',
        task_id: 42,
        task: { id: 42, title: 'Updated occurrence' }
      })
    };
  };

  const formData = new FormData();
  formData.set('csrf_token', 'csrf');
  formData.set('task_id', '42');
  formData.set('scope', 'single');
  formData.set('mode', 'edit');
  formData.set('task_title', 'Updated occurrence');
  formData.set('due_at', '2026-09-21T09:30');
  formData.set('has_time', '1');
  formData.set('reminders', JSON.stringify([{ value: 30, unit: 'minutes' }]));
  formData.set('repeat_config', '');

  const result = await updateRecurringTask(formData);

  assert.equal(result.scope, 'single');
  assert.equal(requests.length, 1);
  assert.equal(requests[0].url, '/api/repeat-tasks/update');
  assert.equal(requests[0].options.method, 'POST');
  assert.equal(requests[0].options.headers.Accept, 'application/json');
  assert.equal(requests[0].options.body.get('scope'), 'single');
  assert.equal(requests[0].options.body.get('repeat_config'), '');
  assert.equal(requests[0].options.body.get('due_at'), '2026-09-21T09:30');
});
