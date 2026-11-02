import { JSDOM } from 'jsdom';
import assert from 'node:assert/strict';
const dom = new JSDOM('<!doctype html><body><div id="phone"></div></body>', { url: 'https://app.test/_native/preview' });
globalThis.location = dom.window.location;
const { renderFrame } = await import('../../resources/preview/preview.js');
const phone = dom.window.document.getElementById('phone');
const actions = [];
const frame = { version: 1, root: { type: 'vstack', properties: { id: 'root', spacing: 8, padding: [8,16] }, children: [
  { type: 'text', properties: { id: 'root.0', text: '<img src=x onerror=alert(1)>', size: 20, weight: 'bold' }, children: [] },
  { type: 'button', properties: { id: 'root.1', text: 'Go', action: 'go', payload: { a: 1 } }, children: [] },
  { type: 'input', properties: { id: 'root.2', name: 'q', onChange: 'typing' }, children: [] },
  { type: 'toggle', properties: { id: 'root.3', name: 't', label: 'Push', value: true, action: 'flip' }, children: [] },
  { type: 'image', properties: { id: 'root.4', source: 'javascript:alert(1)' }, children: [] },
  { type: 'image', properties: { id: 'root.5', source: 'https://cdn.test/a.png', alt: 'ok' }, children: [] },
  { type: 'topbar', properties: { id: 'root.6', title: 'Home', actions: [{ icon: 'search', action: 'search' }] }, children: [] },
  { type: 'bottomnav', properties: { id: 'root.7' }, children: [{ type: 'navitem', properties: { id: 'home', label: 'Home', action: 'nav.home', active: true, badge: '3' }, children: [] }] },
  { type: 'hologram', properties: {}, children: [] },
  { type: 'text', properties: { id: 'root.9', text: 'hidden', visible: false }, children: [] },
]}};
renderFrame(frame, phone, { onAction: (a) => actions.push(a) });
const q = (s) => phone.querySelector(s);

assert.equal(phone.querySelectorAll('img[src="x"]').length, 0, 'text must not become an <img>');
assert.equal(q('[data-native-id="root.0"]').textContent, '<img src=x onerror=alert(1)>');
assert.equal(q('[data-native-id="root.4"]').getAttribute('src'), null, 'javascript: image blocked');
assert.equal(q('[data-native-id="root.5"]').getAttribute('src'), 'https://cdn.test/a.png');
q('[data-native-id="root.1"]').click();
const input = q('[data-native-id="root.2"]'); input.value = 'hey'; input.dispatchEvent(new dom.window.Event('input'));
q('[data-native-id="root.3"] input').click();
q('.nv-topbar button').click();
q('.nv-bottomnav button').click();
assert.deepEqual(actions.map(a => a.action), ['go','typing','flip','search','nav.home']);
assert.deepEqual(actions[0].payload, { a: 1 });
assert.equal(actions[1].value, 'hey'); assert.equal(actions[2].value, false);
assert.equal(q('[data-node-type="hologram"]').textContent, '[unsupported: hologram]');
assert.equal(q('[data-native-id="root.9"]').hidden, true);
assert.equal(q('[data-native-id="root"]').style.padding, '8px 16px');
assert.match(q('.nv-bottomnav button').textContent, /Home \(3\)/);
renderFrame({ version: 2 }, phone); assert.match(phone.textContent, /not a version 1 frame/);
let deep = { type: 'text', properties: {}, children: [] }; for (let i = 0; i < 100; i++) deep = { type: 'vstack', properties: {}, children: [deep] };
renderFrame({ version: 1, root: deep }, phone); assert.ok(phone.textContent.includes('[invalid node]'), 'depth capped');
console.log('preview renderer: all assertions passed');
