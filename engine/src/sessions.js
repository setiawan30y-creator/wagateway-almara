/*
 * Session manager — satu sesi = satu nomor WhatsApp (perangkat).
 * Driver (Baileys) disuntikkan agar logika HTTP & sesi bisa diuji tanpa WhatsApp.
 * @2026-Alifiandra
 */
import { mkdirSync, readFileSync, writeFileSync, existsSync, rmSync } from 'node:fs';
import { join } from 'node:path';
import { randomBytes } from 'node:crypto';

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const rand = (min, max) => min + Math.random() * Math.max(0, max - min);

const SAFE_NAME = /^[a-zA-Z0-9_-]{1,64}$/;
export const BATCH = { contacts: 500, history: 200 };

function chunk(list, size) {
  const out = [];
  for (let i = 0; i < list.length; i += size) out.push(list.slice(i, i + size));
  return out;
}

export class SessionManager {
  /**
   * @param {object} opts
   * @param {string} opts.storageDir
   * @param {(ctx: object) => Promise<object>} opts.createDriver  factory driver per sesi
   * @param {(url: string, token: string, payload: object) => Promise<void>} opts.notify  kirim event ke gateway
   * @param {object} opts.logger
   */
  constructor({ storageDir, createDriver, notify, logger, sleepFn }) {
    this.sleep = sleepFn || sleep;
    this.storageDir = storageDir;
    this.createDriver = createDriver;
    this.notify = notify;
    this.logger = logger;
    this.sessions = new Map();
    mkdirSync(storageDir, { recursive: true });
    this.configPath = join(storageDir, 'sessions.json');
    this.config = existsSync(this.configPath) ? JSON.parse(readFileSync(this.configPath, 'utf8') || '{}') : {};
  }

  static validName(name) {
    return typeof name === 'string' && SAFE_NAME.test(name);
  }

  saveConfig() {
    writeFileSync(this.configPath, JSON.stringify(this.config, null, 2));
  }

  /** Pulihkan sesi yang sudah pernah login saat engine dinyalakan ulang. */
  async restore() {
    for (const name of Object.keys(this.config)) {
      if (existsSync(join(this.storageDir, name, 'creds.json'))) {
        await this.start(name).catch((e) => this.logger.error({ err: e, name }, 'gagal memulihkan sesi'));
      }
    }
  }

  async start(name, { webhookUrl, webhookToken } = {}) {
    if (webhookUrl !== undefined || webhookToken !== undefined) {
      this.config[name] = {
        ...(this.config[name] || {}),
        ...(webhookUrl !== undefined ? { webhookUrl } : {}),
        ...(webhookToken !== undefined ? { webhookToken } : {}),
      };
      this.saveConfig();
    } else if (!this.config[name]) {
      this.config[name] = {};
      this.saveConfig();
    }

    const existing = this.sessions.get(name);
    if (existing && existing.state !== 'logged_out' && existing.state !== 'closed') return existing;

    const session = {
      name,
      state: 'connecting',
      qr: null,
      qrAt: null,
      phone: null,
      pushName: null,
      lastError: null,
      driver: null,
      groups: null,
      humanQueue: [],
      humanBusy: false,
      lastHumanAt: 0,
    };
    this.sessions.set(name, session);

    session.driver = await this.createDriver({
      name,
      authDir: join(this.storageDir, name),
      logger: this.logger,
      onQr: (qr) => {
        session.state = 'qr';
        session.qr = qr;
        session.qrAt = Date.now();
      },
      onOpen: ({ phone, pushName }) => {
        session.state = 'connected';
        session.qr = null;
        session.phone = phone || null;
        session.pushName = pushName || null;
        session.lastError = null;
        this.emit(name, { type: 'session', status: 'connected', phone: session.phone, name: session.pushName });
      },
      onClose: ({ loggedOut, reason }) => {
        session.lastError = reason || null;
        if (loggedOut) {
          session.state = 'logged_out';
          session.phone = null;
          rmSync(join(this.storageDir, name), { recursive: true, force: true });
          this.emit(name, { type: 'session', status: 'logged_out', reason });
        } else {
          session.state = 'connecting';
          this.emit(name, { type: 'session', status: 'reconnecting', reason });
        }
      },
      onMessage: (msg) => this.emit(name, { type: 'message', ...msg }),
      onStatus: (st) => this.emit(name, { type: 'status', ...st }),
      onContacts: (list) => this.mergeContacts(name, list),
      onHistory: (items) => {
        for (const part of chunk(items, BATCH.history)) this.emit(name, { type: 'history', messages: part });
      },
      onGroups: (groups) => {
        session.groups = groups;
        this.emit(name, { type: 'groups', groups: groups.map(({ participants, ...g }) => g) });
      },
    });

    return session;
  }

  emit(name, payload) {
    const cfg = this.config[name] || {};
    if (!cfg.webhookUrl) return Promise.resolve();
    return this.notify(cfg.webhookUrl, cfg.webhookToken || '', { session: name, ...payload }).catch((e) =>
      this.logger.warn({ err: e?.message, name }, 'gagal meneruskan event ke gateway'),
    );
  }

  contactsPath(name) {
    return join(this.storageDir, `${name}.contacts.json`);
  }

  /** Kontak tersimpan (cache file) — {phone: name|null}. */
  loadContacts(name) {
    const p = this.contactsPath(name);
    if (!existsSync(p)) return {};
    try {
      return JSON.parse(readFileSync(p, 'utf8') || '{}');
    } catch {
      return {};
    }
  }

  /** Gabungkan kontak baru ke cache lalu teruskan yang berubah ke gateway (per batch). */
  mergeContacts(name, list) {
    const cache = this.loadContacts(name);
    const changed = [];
    for (const c of list || []) {
      const phone = String(c.phone || '').replace(/\D/g, '');
      if (phone.length < 8) continue;
      const nm = c.name ? String(c.name).slice(0, 120) : null;
      if (!(phone in cache) || (nm && cache[phone] !== nm)) {
        cache[phone] = nm || cache[phone] || null;
        changed.push({ phone, name: cache[phone] });
      }
    }
    if (!changed.length) return 0;
    writeFileSync(this.contactsPath(name), JSON.stringify(cache));
    for (const part of chunk(changed, BATCH.contacts)) this.emit(name, { type: 'contacts', contacts: part });
    return changed.length;
  }

  contacts(name) {
    return Object.entries(this.loadContacts(name)).map(([phone, nm]) => ({ phone, name: nm }));
  }

  /** Kirim ulang seluruh kontak dari cache ke gateway (tombol "Sinkron kontak"). */
  resendContacts(name) {
    const list = this.contacts(name);
    for (const part of chunk(list, BATCH.contacts)) this.emit(name, { type: 'contacts', contacts: part });
    return list.length;
  }

  async groups(name) {
    const s = this.connected(name);
    const groups = await s.driver.groups();
    s.groups = groups;
    return groups;
  }

  connected(name) {
    const s = this.get(name);
    if (!s || s.state !== 'connected') {
      const err = new Error('Sesi belum terhubung. Scan QR terlebih dahulu.');
      err.status = 409;
      throw err;
    }
    return s;
  }

  get(name) {
    return this.sessions.get(name) || null;
  }

  status(name) {
    const s = this.get(name);
    if (!s) {
      return { connected: false, state: 'idle', message: 'Sesi belum dimulai. Buka halaman pairing untuk scan QR.' };
    }
    const messages = {
      connecting: 'Menghubungkan ke WhatsApp…',
      qr: 'Menunggu scan QR.',
      connected: `Terhubung${s.phone ? ' sebagai +' + s.phone : ''}.`,
      logged_out: 'Perangkat keluar (logout). Scan QR lagi untuk menghubungkan.',
      closed: 'Sesi ditutup.',
    };
    return {
      connected: s.state === 'connected',
      state: s.state,
      phone: s.phone,
      name: s.pushName,
      message: messages[s.state] || s.state,
      last_error: s.lastError,
      queue: s.humanQueue.length,
    };
  }

  async sendText(name, to, text) {
    return this.connected(name).driver.sendText(to, text);
  }

  async sendMedia(name, to, media) {
    return this.connected(name).driver.sendMedia(to, media);
  }

  /**
   * Antrean kirim "seperti manusia" (untuk broadcast): jeda acak antar pesan + status mengetik.
   * Langsung mengembalikan ID pesan; pengiriman sebenarnya berjalan di latar belakang.
   * Gagal -> event status "failed" ke gateway.
   * @param {{kind: 'text'|'media', to: string, text?: string, media?: object, humanize: {min_delay?: number, max_delay?: number, typing?: boolean}}} job
   */
  enqueue(name, job) {
    const s = this.connected(name);
    const id = s.driver.newMessageId?.() || '3EB0' + randomBytes(10).toString('hex').toUpperCase();
    s.humanQueue.push({ ...job, id });
    const position = s.humanQueue.length;
    this.drain(name, s);
    return { id, queued: true, position };
  }

  async drain(name, s) {
    if (s.humanBusy) return;
    s.humanBusy = true;

    try {
      while (s.humanQueue.length) {
        const job = s.humanQueue[0];
        const h = job.humanize || {};
        const min = Math.max(0, Number(h.min_delay ?? 8)) * 1000;
        const max = Math.max(min, Number(h.max_delay ?? 25) * 1000);
        const gap = rand(min, max);
        const wait = Math.max(0, s.lastHumanAt + gap - Date.now());
        if (wait > 0) await this.sleep(wait);

        s.humanQueue.shift();

        try {
          if (s.state !== 'connected') throw new Error('Sesi WhatsApp terputus sebelum pesan terkirim.');
          if (h.typing !== false && s.driver.typing) {
            const len = (job.text || job.media?.caption || '').length;
            await s.driver.typing(job.to, Math.min(6000, Math.max(1200, len * 50)) * (0.8 + Math.random() * 0.4));
          }
          if (job.kind === 'media') await s.driver.sendMedia(job.to, job.media, { messageId: job.id });
          else await s.driver.sendText(job.to, job.text, { messageId: job.id });
          this.emit(name, { type: 'status', id: job.id, status: 'sent' });
        } catch (e) {
          this.emit(name, { type: 'status', id: job.id, status: 'failed', error: String(e?.message || e).slice(0, 300) });
        }

        s.lastHumanAt = Date.now();
      }
    } finally {
      s.humanBusy = false;
    }
  }

  async logout(name) {
    const s = this.get(name);
    if (s?.driver) {
      await s.driver.logout().catch(() => {});
    }
    rmSync(join(this.storageDir, name), { recursive: true, force: true });
    rmSync(this.contactsPath(name), { force: true });
    this.sessions.delete(name);
    delete this.config[name];
    this.saveConfig();
  }

  async shutdown() {
    const pending = [];
    for (const [name, s] of this.sessions.entries()) {
      for (const job of s.humanQueue.splice(0)) {
        pending.push(this.emit(name, { type: 'status', id: job.id, status: 'failed', error: 'Engine dimatikan sebelum pesan terkirim.' }));
      }
    }
    await Promise.race([Promise.all(pending), sleep(3000)]);
    for (const s of this.sessions.values()) {
      await s.driver?.close?.().catch(() => {});
    }
  }
}
