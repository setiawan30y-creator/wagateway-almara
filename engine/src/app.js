/*
 * HTTP API engine (dipanggil oleh gateway Laravel, bukan oleh publik).
 *   GET  /health
 *   POST /sessions/start   {session, webhook_url, webhook_token}
 *   GET  /status?session=
 *   GET  /qr?session=      -> {qr: "data:image/png;base64,..."} atau connected
 *   POST /send-message     {session, to, message}
 *   POST /send-media       {session, to, type, mime, name, caption, data(base64)}
 *   POST /sessions/logout  {session}
 * Semua kecuali /health wajib header: Authorization: Bearer <ENGINE_TOKEN>
 * @2026-Alifiandra
 */
import { createServer } from 'node:http';
import { timingSafeEqual } from 'node:crypto';
import { SessionManager } from './sessions.js';

function json(res, status, data) {
  const body = JSON.stringify(data);
  res.writeHead(status, { 'Content-Type': 'application/json; charset=utf-8', 'Content-Length': Buffer.byteLength(body) });
  res.end(body);
}

function readBody(req, limit = 64 * 1024) {
  return new Promise((resolve, reject) => {
    let size = 0;
    const chunks = [];
    req.on('data', (c) => {
      size += c.length;
      if (size > limit) {
        reject(Object.assign(new Error('Body terlalu besar.'), { status: 413 }));
        req.destroy();
      } else chunks.push(c);
    });
    req.on('end', () => {
      const raw = Buffer.concat(chunks).toString('utf8');
      if (!raw) return resolve({});
      try {
        resolve(JSON.parse(raw));
      } catch {
        reject(Object.assign(new Error('JSON tidak valid.'), { status: 400 }));
      }
    });
    req.on('error', reject);
  });
}

function authorized(req, token) {
  if (!token) return true; // tanpa token hanya untuk pengembangan lokal
  const given = (req.headers.authorization || '').replace(/^Bearer\s+/i, '');
  const a = Buffer.from(given);
  const b = Buffer.from(token);
  return a.length === b.length && timingSafeEqual(a, b);
}

export function createApp({ manager, token, toDataUrl }) {
  return createServer(async (req, res) => {
    const url = new URL(req.url, 'http://engine.local');
    const path = url.pathname.replace(/\/+$/, '') || '/';

    try {
      if (req.method === 'GET' && path === '/health') {
        return json(res, 200, { ok: true, sessions: manager.sessions.size });
      }

      if (!authorized(req, token)) {
        return json(res, 401, { success: false, message: 'Token engine tidak valid.' });
      }

      const body = req.method === 'POST' ? await readBody(req, path === '/send-media' ? 24 * 1024 * 1024 : 64 * 1024) : {};
      const session = String(body.session ?? url.searchParams.get('session') ?? 'default');

      if (!SessionManager.validName(session)) {
        return json(res, 422, { success: false, message: 'Nama sesi tidak valid (huruf, angka, - dan _).' });
      }

      if (req.method === 'POST' && path === '/sessions/start') {
        await manager.start(session, { webhookUrl: body.webhook_url, webhookToken: body.webhook_token });
        return json(res, 200, { success: true, ...manager.status(session) });
      }

      if (req.method === 'GET' && path === '/status') {
        return json(res, 200, manager.status(session));
      }

      if (req.method === 'GET' && path === '/qr') {
        if (!manager.get(session)) await manager.start(session);
        const s = manager.get(session);
        const st = manager.status(session);
        if (st.connected) return json(res, 200, { connected: true, qr: null, message: st.message, phone: st.phone });
        const qr = s?.qr ? await toDataUrl(s.qr) : null;
        return json(res, 200, { connected: false, qr, message: qr ? 'Scan QR dengan WhatsApp > Perangkat tertaut.' : st.message, expires_in: qr ? 20 : null });
      }

      if (req.method === 'POST' && path === '/send-message') {
        const to = String(body.to || '').replace(/\D/g, '');
        const message = String(body.message || '');
        if (to.length < 8 || !message.trim()) {
          return json(res, 422, { success: false, message: 'Field to dan message wajib diisi.' });
        }
        const result = await manager.sendText(session, to, message);
        return json(res, 200, { success: true, id: result.id });
      }

      if (req.method === 'POST' && path === '/send-media') {
        const to = String(body.to || '').replace(/\D/g, '');
        const type = ['image', 'video', 'audio', 'document'].includes(body.type) ? body.type : 'document';
        const buffer = Buffer.from(String(body.data || ''), 'base64');
        if (to.length < 8 || !buffer.length) {
          return json(res, 422, { success: false, message: 'Field to dan data (base64) wajib diisi.' });
        }
        const result = await manager.sendMedia(session, to, { type, mime: body.mime, name: body.name, caption: body.caption, buffer });
        return json(res, 200, { success: true, id: result.id });
      }

      if (req.method === 'POST' && path === '/sessions/logout') {
        await manager.logout(session);
        return json(res, 200, { success: true, message: 'Sesi dihapus.' });
      }

      return json(res, 404, { success: false, message: 'Endpoint tidak ditemukan.' });
    } catch (e) {
      const status = e.status || 500;
      return json(res, status, { success: false, message: status === 500 ? 'Kesalahan engine: ' + e.message : e.message });
    }
  });
}
