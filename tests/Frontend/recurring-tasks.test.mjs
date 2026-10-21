import assert from 'node:assert/strict';
import test from 'node:test';

class FakeClassList {
  constructor(value = '') { this.values = new Set(value.split(/\s+/).filter(Boolean)); }
  add(...names) { names.forEach(name => this.values.add(name)); }
  remove(...names) { names.forEach(name => this.values.delete(name)); }
  contains(name) { return this.values.has(name); }
  toggle(name, force) {
    const shouldAdd = force === undefined ? !this.values.has(name) : force;
    if (shouldAdd) this.values.add(name); else this.values.delete(name);
    return shouldAdd;
  }
}

class FakeElement {
  constructor(tagName = 'div', attrs = {}) {
    this.tagName = tagName.toUpperCase();
    this.attributes = {};
    this.dataset = {};
    this.children = [];
    this.parentElement = null;
    this.classList = new FakeClassList(attrs.class || '');
    this.textContent = attrs.textContent || '';
    this.hidden = false;
    this.disabled = false;
    this.listeners = [];
    Object.entries(attrs).forEach(([key, value]) => {
      if (key !== 'class' && key !== 'textContent') this.setAttribute(key, value);
    });
  }
  setAttribute(name, value) {
    this.attributes[name] = String(value);
    if (name === 'class') this.classList = new FakeClassList(String(value));
    if (name.startsWith('data-')) {
      const key = name.slice(5).replace(/-([a-z])/g, (_, letter) => letter.toUpperCase());
      this.dataset[key] = String(value);
    }
  }
  getAttribute(name) { return this.attributes[name] ?? null; }
  removeAttribute(name) { delete this.attributes[name]; }
  appendChild(child) { child.parentElement = this; this.children.push(child); return child; }
  removeChild(child) { this.children = this.children.filter(item => item !== child); child.parentElement = null; }
  remove() { this.parentElement?.removeChild(this); }
  // Replaces one row without changing its surrounding list or delegated listeners.
  replaceWith(next) {
    const parent = this.parentElement;
    parent.children[parent.children.indexOf(this)] = next;
    next.parentElement = parent;
    this.parentElement = null;
  }
  contains(element) { return this === element || this.children.some(child => child.contains(element)); }
  addEventListener(type, listener, options) {
    this.listeners.push({ type, listener, signal: options?.signal });
    options?.signal?.addEventListener('abort', () => {
      this.listeners = this.listeners.filter(item => item.listener !== listener);
    }, { once: true });
  }
  click(target = this) {
    this.listeners.filter(item => item.type === 'click').forEach(item => item.listener({ target, currentTarget: this }));
  }
  matches(selector) {
    if (selector.startsWith('.')) return this.classList.contains(selector.slice(1));
    if (selector.startsWith('#')) return this.getAttribute('id') === selector.slice(1);
    const dataMatch = selector.match(/^\[data-([\w-]+)(?:="([^"]+)")?\]$/);
    if (dataMatch) {
      const key = dataMatch[1].replace(/-([a-z])/g, (_, letter) => letter.toUpperCase());
      return Object.hasOwn(this.dataset, key) && (dataMatch[2] === undefined || this.dataset[key] === dataMatch[2]);
    }
    const metaMatch = selector.match(/^meta\[name="([^"]+)"\]$/);
    return Boolean(metaMatch && this.tagName === 'META' && this.getAttribute('name') === metaMatch[1]);
  }
  closest(selector) {
    let current = this;
    while (current) { if (current.matches(selector)) return current; current = current.parentElement; }
    return null;
  }
  querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
  querySelectorAll(selector) {
    return this.children.flatMap(child => [ ...(child.matches(selector) ? [child] : []), ...child.querySelectorAll(selector) ]);
  }
  get firstChild() { return this.children[0] || null; }
}

class FakeDocument extends FakeElement {
  constructor() { super('document'); this.meta = new FakeElement('meta', { name: 'csrf-token', content: 'csrf' }); }
  // Supplies a prepared server fragment; HTML parsing itself is verified in the browser.
  createElement(tagName) {
    if (tagName === 'template') return { content: this.fragment, innerHTML: '' };
    return new FakeElement(tagName);
  }
  // Delivers the same document-level events used by the modal and recurring list.
  dispatchEvent(event) {
    this.listeners.filter(item => item.type === event.type).forEach(item => item.listener(event));
    return true;
  }
  getElementById(id) { return this.querySelector(`#${id}`); }
  querySelector(selector) {
    if (selector === 'meta[name="csrf-token"]') return this.meta;
    return super.querySelector(selector);
  }
}

function setupPage(status = 'active') {
  const document = new FakeDocument();
  const container = document.appendChild(new FakeElement('div', { class: 'recurringTasksContent' }));
  const row = container.appendChild(new FakeElement('article', { class: 'recurringRule', 'data-repeat-rule-id': '7', 'data-repeat-status': status }));
  const main = row.appendChild(new FakeElement('div', { class: 'recurringRuleMain' }));
  main.appendChild(new FakeElement('h2', { textContent: 'Morning task' }));
  row.appendChild(new FakeElement('span', { class: 'recurringStatus', textContent: status }));
  const actions = row.appendChild(new FakeElement('div', { class: 'recurringActions' }));
  actions.appendChild(new FakeElement('button', { 'data-repeat-action': 'edit', 'data-repeat-rule-id': '7', textContent: 'Edit' }));
  actions.appendChild(new FakeElement('button', { 'data-repeat-action': status === 'paused' ? 'resume' : 'pause', 'data-repeat-rule-id': '7', textContent: status === 'paused' ? 'Resume' : 'Pause' }));
  actions.appendChild(new FakeElement('button', { 'data-repeat-action': 'cancel', 'data-repeat-rule-id': '7', textContent: 'Cancel' }));
  container.appendChild(new FakeElement('p', { id: 'recurringTasksStatus' }));
  return { document, container, row, actions };
}

globalThis.document = setupPage().document;
globalThis.window = { confirm: () => true };
const recurringModule = await import('../../public/assets/js/modules/recurring-tasks.js');
delete globalThis.document;

async function withPage(status, callback) {
  const page = setupPage(status);
  globalThis.document = page.document;
  try { return await callback(page); } finally { delete globalThis.document; }
}

test('pause disables row actions while pending and renders paused Resume state', async () => {
  await withPage('active', async ({ document, container, row }) => {
    let resolveRequest;
    globalThis.fetch = () => new Promise(resolve => { resolveRequest = resolve; });
    const controller = new AbortController();
    recurringModule.initRecurringTasks(controller.signal);
    const pause = row.querySelector('[data-repeat-action="pause"]');
    container.click(pause);
    assert.equal(pause.disabled, true);
    resolveRequest({ ok: true, text: async () => JSON.stringify({ success: true, status: 'paused' }) });
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(row.dataset.repeatStatus, 'paused');
    assert.equal(row.querySelector('[data-repeat-action="resume"]').textContent, 'Resume');
    assert.equal(row.querySelector('[data-repeat-action="pause"]'), null);
    controller.abort();
  });
});

test('resume renders active Pause state and cancel leaves terminal history row without actions', async () => {
  await withPage('paused', async ({ container, row }) => {
    globalThis.fetch = async (url) => ({ ok: true, text: async () => JSON.stringify({ success: true, status: url.endsWith('resume') ? 'active' : 'cancelled' }) });
    const controller = new AbortController();
    recurringModule.initRecurringTasks(controller.signal);
    const resume = row.querySelector('[data-repeat-action="resume"]');
    container.click(resume);
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(row.dataset.repeatStatus, 'active');
    assert.ok(row.querySelector('[data-repeat-action="pause"]'));
    const cancel = row.querySelector('[data-repeat-action="cancel"]');
    container.click(cancel);
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(row.dataset.repeatStatus, 'cancelled');
    assert.equal(row.querySelector('[data-repeat-action]'), null);
    controller.abort();
  });
});

test('API failure restores controls and announces the translated error', async () => {
  await withPage('active', async ({ container, row }) => {
    globalThis.fetch = async () => { throw new Error('Server said no'); };
    const controller = new AbortController();
    recurringModule.initRecurringTasks(controller.signal);
    const pause = row.querySelector('[data-repeat-action="pause"]');
    container.click(pause);
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(pause.disabled, false);
    assert.equal(container.querySelector('#recurringTasksStatus').textContent, 'Server said no');
    controller.abort();
  });
});

test('an already-aborted signal does not register duplicate delegated handlers', async () => {
  await withPage('active', async ({ container, row }) => {
    const controller = new AbortController();
    controller.abort();
    recurringModule.initRecurringTasks(controller.signal);
    recurringModule.initRecurringTasks(controller.signal);
    assert.equal(container.listeners.length, 0);
    assert.equal(row.querySelector('[data-repeat-action="pause"]').disabled, false);
  });
});

for (const status of ['active', 'paused']) {
  test(`${status}: two edits replace the entire row and reopen the latest rule payload`, async () => {
    await withPage(status, async ({ document, container, row }) => {
      const controller = new AbortController();
      recurringModule.initRecurringTasks(controller.signal);
      let previousId = 7;
      for (const revision of [1, 2]) {
        const id = status === 'active' ? 7 + revision : 7;
        const payload = { id, title: `Saved ${revision}`, status, start_at: `2099-01-0${revision}T09:30`,
          frequency: 'weekly', week_days: [revision], ends: { type: 'count', count: revision + 3 },
          reminders: [{ value: revision * 15, unit: 'minute' }] };
        const fresh = setupPage(status);
        fresh.row.dataset.repeatRuleId = String(id);
        fresh.row.dataset.repeatRule = JSON.stringify(payload);
        fresh.row.querySelectorAll('[data-repeat-action]').forEach(button => { button.dataset.repeatRuleId = String(id); });
        document.fragment = fresh.container;
        globalThis.fetch = async (url, options) => {
          assert.equal(url, '/recurring-tasks?partial=1&filter=all');
          assert.equal(options.signal, controller.signal);
          return { ok: true, json: async () => ({ activeView: 'recurring-tasks', html: '<section>fresh row</section>' }) };
        };
        document.dispatchEvent({ type: 'recurring-rule:updated', detail: {
          container, previousRuleId: String(previousId), response: { success: true, scope: 'rule', repeat_rule_id: id }
        } });
        await new Promise(resolve => setImmediate(resolve));
        const updated = container.querySelector('.recurringRule');
        assert.equal(updated, fresh.row);
        assert.equal(row.parentElement, null);
        let opened;
        document.addEventListener('recurring-rule:edit', event => { opened = event.detail.payload; }, { signal: controller.signal });
        container.click(updated.querySelector('[data-repeat-action="edit"]'));
        assert.deepEqual(opened, payload);
        assert.ok(updated.querySelectorAll('[data-repeat-action]').every(button => button.dataset.repeatRuleId === String(id)));
        row = updated;
        previousId = id;
      }
      controller.abort();
    });
  });
}

test('a failed edit event leaves the valid row unchanged and does not refresh it', async () => {
  await withPage('active', async ({ document, container, row }) => {
    const controller = new AbortController();
    recurringModule.initRecurringTasks(controller.signal);
    globalThis.fetch = () => { throw new Error('Unexpected refresh'); };
    document.dispatchEvent({ type: 'recurring-rule:updated', detail: {
      container, previousRuleId: '7', response: { success: false, repeat_rule_id: 8 }
    } });
    assert.equal(row.dataset.repeatRuleId, '7');
    assert.equal(row.querySelector('[data-repeat-action="edit"]').disabled, false);
    controller.abort();
  });
});

test('navigation abort discards a late refresh response', async () => {
  await withPage('active', async ({ document, container, row }) => {
    const controller = new AbortController();
    recurringModule.initRecurringTasks(controller.signal);
    let resolveRequest;
    globalThis.fetch = () => new Promise(resolve => { resolveRequest = resolve; });
    document.dispatchEvent({ type: 'recurring-rule:updated', detail: {
      container, previousRuleId: '7', response: { success: true, repeat_rule_id: 8 }
    } });
    controller.abort();
    resolveRequest({ ok: true, json: async () => ({ activeView: 'recurring-tasks', html: '<section>late</section>' }) });
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(container.querySelector('.recurringRule'), row);
    assert.equal(row.dataset.repeatRuleId, '7');
  });
});

test('a refresh failure keeps old data but disables stale actions after a successful save', async () => {
  await withPage('active', async ({ document, container, row }) => {
    const controller = new AbortController();
    recurringModule.initRecurringTasks(controller.signal);
    globalThis.fetch = async () => ({ ok: false });
    document.dispatchEvent({ type: 'recurring-rule:updated', detail: {
      container, previousRuleId: '7', response: { success: true, repeat_rule_id: 8 }
    } });
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(row.dataset.repeatRuleId, '7');
    assert.ok(row.querySelectorAll('[data-repeat-action]').every(button => button.disabled));
    assert.match(container.querySelector('#recurringTasksStatus').textContent, /saved.*reload/i);
    controller.abort();
  });
});
