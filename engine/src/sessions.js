/*
 * Session manager — satu sesi = satu nomor WhatsApp (perangkat).
 * Driver (Baileys) disuntikkan agar logika HTTP & sesi bisa diuji tanpa WhatsApp.
 * @2026-Alifiandra
 */
import { mkdirSync, readFileSync, writeFileSync, existsSync, rmSync } from 'node:fs';
import { join } from 'node:path';

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
  constructor({ storageDir, createDriver, notify, logger }) {
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
    if (!cfg.webhookUrl) return;
    this.notify(cfg.webhookUrl, cfg.webhookToken || '', { session: name, ...payload }).catch((e) =>
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
    };
  }

  async sendText(name, to, text) {
    return this.connected(name).driver.sendText(to, text);
  }

  async sendMedia(name, to, media) {
    return this.connected(name).driver.sendMedia(to, media);
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
    for (const s of this.sessions.values()) {
      await s.driver?.close?.().catch(() => {});
    }
  }
}
