// Uji HTTP & session manager dengan driver palsu (tanpa WhatsApp). @2026-Alifiandra
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { SessionManager } from '../src/sessions.js';
import { createApp } from '../src/app.js';

function setup() {
  const events = [];
  let ctxRef;
  const manager = new SessionManager({
    storageDir: mkdtempSync(join(tmpdir(), 'eng-')),
    logger: { error() {}, warn() {}, child() { return this; } },
    notify: async (url, token, payload) => { events.push({ url, token, payload }); },
    createDriver: async (ctx) => {
      ctxRef = ctx;
      setTimeout(() => ctx.onQr('QR-STRING'), 0);
      return { sendText: async (to, text) => ({ id: 'MSG-' + to }), sendMedia: async (to, m) => ({ id: 'MED-' + m.type + '-' + m.buffer.length }), logout: async () => {}, close: async () => {} };
    },
  });
  const app = createApp({ manager, token: 'tok', toDataUrl: async (qr) => 'data:image/png;base64,' + Buffer.from(qr).toString('base64') });
  return new Promise((resolve) => app.listen(0, '127.0.0.1', () => resolve({ app, events, manager, ctx: () => ctxRef, base: `http://127.0.0.1:${app.address().port}` })));
}

const h = { Authorization: 'Bearer tok', 'Content-Type': 'application/json' };

test('alur pairing, kirim pesan, pesan masuk, logout', async () => {
  const { app, events, base, ctx } = await setup();

  assert.equal((await fetch(base + '/status?session=dev1')).status, 401);
  assert.equal((await fetch(base + '/status?session=../x', { headers: h })).status, 422);

  let r = await fetch(base + '/sessions/start', { method: 'POST', headers: h, body: JSON.stringify({ session: 'dev1', webhook_url: 'http://gw/api/webhooks/qr/1', webhook_token: 'wt' }) });
  assert.equal(r.status, 200);
  await new Promise((s) => setTimeout(s, 10));

  r = await (await fetch(base + '/qr?session=dev1', { headers: h })).json();
  assert.equal(r.connected, false);
  assert.match(r.qr, /^data:image\/png;base64,/);

  r = await fetch(base + '/send-message', { method: 'POST', headers: h, body: JSON.stringify({ session: 'dev1', to: '6281234567890', message: 'Halo' }) });
  assert.equal(r.status, 409); // belum terhubung

  ctx().onOpen({ phone: '6281200000000', pushName: 'Toko' });
  r = await (await fetch(base + '/status?session=dev1', { headers: h })).json();
  assert.equal(r.connected, true);
  assert.equal(r.phone, '6281200000000');
  assert.equal(events.at(-1).payload.status, 'connected');
  assert.equal(events.at(-1).token, 'wt');

  r = await (await fetch(base + '/send-message', { method: 'POST', headers: h, body: JSON.stringify({ session: 'dev1', to: '+62 812-3456-7890', message: 'Halo' }) })).json();
  assert.deepEqual(r, { success: true, id: 'MSG-6281234567890' });

  r = await (await fetch(base + '/send-media', { method: 'POST', headers: h, body: JSON.stringify({ session: 'dev1', to: '6281234567890', type: 'image', mime: 'image/png', data: Buffer.from('abc').toString('base64') }) })).json();
  assert.deepEqual(r, { success: true, id: 'MED-image-3' });

  ctx().onMessage({ from: '6281111111111', message: 'Pesan masuk', id: 'X1', name: 'Budi' });
  ctx().onStatus({ id: 'MSG-6281234567890', status: 'read' });
  await new Promise((s) => setTimeout(s, 5));
  assert.deepEqual(events.map((e) => e.payload.type), ['session', 'message', 'status']);
  assert.equal(events[1].url, 'http://gw/api/webhooks/qr/1');

  r = await fetch(base + '/sessions/logout', { method: 'POST', headers: h, body: JSON.stringify({ session: 'dev1' }) });
  assert.equal(r.status, 200);
  r = await (await fetch(base + '/status?session=dev1', { headers: h })).json();
  assert.equal(r.state, 'idle');

  app.close();
});
