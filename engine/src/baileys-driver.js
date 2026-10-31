/*
 * Driver WhatsApp Web Multi-Device memakai Baileys.
 * - Pesan pribadi & grup (masuk/keluar), media, status terkirim/dibaca
 * - Sinkron kontak & riwayat chat terbaru yang dikirim WhatsApp saat perangkat ditautkan
 * - Daftar grup + anggota, kirim ke grup
 * Catatan: ini jalur tidak resmi (bukan Meta Cloud API). Gunakan dengan bijak —
 * kirim hanya ke kontak yang memberi izin, hindari spam, agar nomor tidak diblokir.
 * @2026-Alifiandra
 */
export async function createBaileysDriver(ctx) {
  const baileys = await import('baileys');
  const makeWASocket = baileys.makeWASocket || baileys.default?.default || baileys.default;
  const {
    useMultiFileAuthState, DisconnectReason, Browsers, fetchLatestBaileysVersion,
    jidNormalizedUser, isJidGroup, isJidBroadcast, isJidStatusBroadcast, isPnUser, isLidUser,
    normalizeMessageContent, getContentType, downloadMediaMessage, generateMessageIDV2,
  } = baileys;

  const MAX_MEDIA = 16 * 1024 * 1024;
  const MEDIA_KINDS = { imageMessage: 'image', videoMessage: 'video', audioMessage: 'audio', documentMessage: 'document', stickerMessage: 'image' };
  const MEDIA_LABEL = {
    imageMessage: '[gambar]', videoMessage: '[video]', audioMessage: '[audio]', documentMessage: '[dokumen]',
    stickerMessage: '[stiker]', locationMessage: '[lokasi]', contactMessage: '[kontak]', contactsArrayMessage: '[kontak]',
  };
  const SKIP_KINDS = new Set(['protocolMessage', 'reactionMessage', 'senderKeyDistributionMessage', 'pollUpdateMessage', 'messageContextInfo']);

  let sock = null;
  let stopped = false;
  let retry = 0;
  const lidToPn = new Map(); // lid -> pn (digits)
  const groupCache = new Map(); // jid -> subject

  const digits = (jid) => (jid ? String(jid).split('@')[0].split(':')[0].replace(/\D/g, '') : null);

  /** Ubah jid (PN / LID) menjadi nomor telepon (digit) bila memungkinkan. */
  async function phoneOf(jid, alt) {
    if (!jid) return null;
    if (isPnUser(jid)) return digits(jidNormalizedUser(jid));
    if (alt && isPnUser(alt)) return digits(jidNormalizedUser(alt));
    if (isLidUser?.(jid)) {
      const key = jidNormalizedUser(jid);
      if (lidToPn.has(key)) return lidToPn.get(key);
      try {
        const pn = await sock?.signalRepository?.lidMapping?.getPNForLID(key);
        if (pn) {
          const d = digits(pn);
          lidToPn.set(key, d);
          return d;
        }
      } catch { /* abaikan */ }
    }
    return null;
  }

  function rememberMappings(list) {
    for (const m of list || []) {
      if (m?.lid && m?.pn) lidToPn.set(jidNormalizedUser(m.lid), digits(m.pn));
    }
  }

  function textOf(content, kind) {
    const text =
      content.conversation ||
      content.extendedTextMessage?.text ||
      content.imageMessage?.caption ||
      content.videoMessage?.caption ||
      content.documentMessage?.caption ||
      content.buttonsResponseMessage?.selectedDisplayText ||
      content.listResponseMessage?.title ||
      content.templateButtonReplyMessage?.selectedDisplayText ||
      '';
    return { text, label: MEDIA_LABEL[kind] || null };
  }

  async function groupSubject(jid) {
    if (groupCache.has(jid)) return groupCache.get(jid);
    try {
      const meta = await sock.groupMetadata(jid);
      groupCache.set(jid, meta?.subject || null);
      return meta?.subject || null;
    } catch {
      return null;
    }
  }

  /** Ringkas satu WAMessage menjadi objek sederhana; null bila diabaikan. */
  async function simplify(m, { withMedia = false } = {}) {
    const jid = m?.key?.remoteJid;
    if (!jid || isJidBroadcast(jid) || isJidStatusBroadcast(jid)) return null;

    const content = normalizeMessageContent(m.message);
    const kind = getContentType(content);
    if (!content || !kind || SKIP_KINDS.has(kind)) return null;

    const { text, label } = textOf(content, kind);
    const isGroup = isJidGroup(jid);
    const fromMe = !!m.key.fromMe;

    let media = null;
    if (withMedia && MEDIA_KINDS[kind]) {
      const info = content[kind] || {};
      const size = Number(info.fileLength || 0);
      if (!size || size <= MAX_MEDIA) {
        try {
          const buf = await downloadMediaMessage(m, 'buffer', {}, { logger: ctx.logger, reuploadRequest: sock.updateMediaMessage });
          if (buf && buf.length <= MAX_MEDIA) {
            media = { type: MEDIA_KINDS[kind], mime: info.mimetype || 'application/octet-stream', name: info.fileName || null, data: Buffer.from(buf).toString('base64') };
          }
        } catch (e) {
          ctx.logger.warn({ err: e?.message }, 'gagal mengunduh media');
        }
      }
    }

    const body = (media ? text : [label, text].filter(Boolean).join(' ')).trim();
    if (!body && !media) return null;

    const ts = Number(m.messageTimestamp?.toNumber ? m.messageTimestamp.toNumber() : m.messageTimestamp || 0) || Math.floor(Date.now() / 1000);

    if (isGroup) {
      const participant = m.key.participant || m.participant;
      return {
        id: m.key.id,
        from_me: fromMe,
        message: body,
        media,
        timestamp: ts,
        name: m.pushName || null,
        from: fromMe ? null : await phoneOf(participant, m.key.participantAlt),
        group: { jid, subject: await groupSubject(jid) },
      };
    }

    const phone = await phoneOf(jid, m.key.remoteJidAlt);
    if (!phone) return null;

    return { id: m.key.id, from_me: fromMe, message: body, media, timestamp: ts, name: fromMe ? null : (m.pushName || null), from: phone };
  }

  async function contactsFrom(list) {
    const out = [];
    for (const c of list || []) {
      if (!c?.id || isJidGroup(c.id) || isJidBroadcast(c.id)) continue;
      const phone = (c.phoneNumber && digits(c.phoneNumber)) || (await phoneOf(c.id, c.phoneNumber));
      if (!phone || phone.length < 8) continue;
      const name = c.name || c.notify || c.verifiedName || null;
      out.push({ phone, name });
    }
    return out;
  }

  async function connect() {
    const { state, saveCreds } = await useMultiFileAuthState(ctx.authDir);
    let version;
    try {
      ({ version } = await fetchLatestBaileysVersion());
    } catch {
      version = undefined; // pakai versi bawaan library
    }

    sock = makeWASocket({
      auth: state,
      version,
      logger: ctx.logger.child({ session: ctx.name }),
      browser: Browsers.ubuntu('ALMARA Gateway'),
      markOnlineOnConnect: false,
      syncFullHistory: false, // WhatsApp tetap mengirim riwayat chat terbaru saat pertama ditautkan
    });

    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('connection.update', (u) => {
      if (u.qr) ctx.onQr(u.qr);

      if (u.connection === 'open') {
        retry = 0;
        ctx.onOpen({ phone: digits(sock.user?.id), pushName: sock.user?.name || null });
        // muat daftar grup ke cache & kirim ke gateway
        listGroups().then((g) => ctx.onGroups?.(g)).catch(() => {});
      }

      if (u.connection === 'close') {
        const code = u.lastDisconnect?.error?.output?.statusCode;
        const loggedOut = code === DisconnectReason.loggedOut;
        ctx.onClose({ loggedOut, reason: u.lastDisconnect?.error?.message || String(code || '') });

        if (!loggedOut && !stopped) {
          // restartRequired (515) terjadi normal setelah scan QR -> sambung ulang segera
          const delay = code === DisconnectReason.restartRequired ? 0 : Math.min(30000, 2000 * 2 ** retry++);
          setTimeout(() => connect().catch((e) => ctx.logger.error({ err: e }, 'reconnect gagal')), delay);
        }
      }
    });

    sock.ev.on('lid-mapping.update', (m) => rememberMappings([m]));

    // Riwayat & kontak yang dikirim WhatsApp saat perangkat ditautkan
    sock.ev.on('messaging-history.set', async ({ contacts, messages, chats, lidPnMappings }) => {
      try {
        rememberMappings(lidPnMappings);

        for (const ch of chats || []) {
          if (ch?.id && isJidGroup(ch.id) && ch.name) groupCache.set(ch.id, ch.name);
        }

        const cs = await contactsFrom(contacts);
        if (cs.length) ctx.onContacts?.(cs);

        const items = [];
        for (const m of messages || []) {
          const s = await simplify(m);
          if (s) items.push(s);
        }
        if (items.length) ctx.onHistory?.(items);
      } catch (e) {
        ctx.logger.warn({ err: e?.message }, 'gagal memproses riwayat');
      }
    });

    sock.ev.on('contacts.upsert', async (list) => {
      const cs = await contactsFrom(list);
      if (cs.length) ctx.onContacts?.(cs);
    });

    sock.ev.on('contacts.update', async (list) => {
      const cs = await contactsFrom(list.filter((c) => c.name || c.notify));
      if (cs.length) ctx.onContacts?.(cs);
    });

    sock.ev.on('groups.upsert', (list) => {
      for (const g of list || []) groupCache.set(g.id, g.subject);
      listGroups().then((g) => ctx.onGroups?.(g)).catch(() => {});
    });

    sock.ev.on('groups.update', (list) => {
      for (const g of list || []) if (g?.id && g.subject) groupCache.set(g.id, g.subject);
    });

    // Pesan baru (pribadi & grup)
    sock.ev.on('messages.upsert', async ({ messages, type }) => {
      if (type !== 'notify') return;

      for (const m of messages) {
        if (m.key?.fromMe) continue;
        try {
          const s = await simplify(m, { withMedia: true });
          if (s) ctx.onMessage(s);
        } catch (e) {
          ctx.logger.warn({ err: e?.message }, 'gagal memproses pesan masuk');
        }
      }
    });

    sock.ev.on('messages.update', (updates) => {
      for (const { key, update } of updates) {
        if (!key?.fromMe || update?.status === undefined || update.status === null) continue;
        // proto WebMessageInfo.Status: 0 ERROR, 1 PENDING, 2 SERVER_ACK, 3 DELIVERY_ACK, 4 READ, 5 PLAYED
        const map = { 0: 'failed', 2: 'sent', 3: 'delivered', 4: 'read', 5: 'read' };
        const status = map[update.status];
        if (status) ctx.onStatus({ id: key.id, status });
      }
    });
  }

  async function listGroups() {
    const all = await sock.groupFetchAllParticipating();
    const me = digits(sock.user?.id);
    const out = [];

    for (const g of Object.values(all || {})) {
      groupCache.set(g.id, g.subject);
      const participants = [];
      for (const p of g.participants || []) {
        const phone = (p.phoneNumber && digits(p.phoneNumber)) || (await phoneOf(p.id, p.phoneNumber));
        participants.push({ phone, admin: p.admin || null });
      }
      out.push({
        jid: g.id,
        subject: g.subject || '(tanpa nama)',
        desc: g.desc || null,
        size: g.size || participants.length,
        announce: !!g.announce,
        is_admin: participants.some((p) => p.phone && p.phone === me && p.admin),
        participants,
      });
    }

    return out;
  }

  /** Tujuan: nomor (digit) atau jid grup (…@g.us). */
  async function resolveTarget(to) {
    const raw = String(to || '').trim();
    if (raw.endsWith('@g.us')) return raw;

    const number = raw.replace(/\D/g, '');
    const jid = `${number}@s.whatsapp.net`;
    const [check] = (await sock.onWhatsApp(jid).catch(() => [])) || [];
    if (check && check.exists === false) {
      const err = new Error('Nomor tidak terdaftar di WhatsApp.');
      err.status = 422;
      throw err;
    }
    return check?.jid || jid;
  }

  await connect();

  const opts = (o) => (o && o.messageId ? { messageId: o.messageId } : undefined);

  return {
    newMessageId() {
      return generateMessageIDV2(sock?.user?.id);
    },
    /** Tampilkan "sedang mengetik…" selama ms lalu berhenti (simulasi manusia). */
    async typing(to, ms) {
      const jid = await resolveTarget(to);
      await sock.sendPresenceUpdate('composing', jid).catch(() => {});
      await new Promise((r) => setTimeout(r, ms));
      await sock.sendPresenceUpdate('paused', jid).catch(() => {});
    },
    async sendText(to, text, o) {
      const sent = await sock.sendMessage(await resolveTarget(to), { text }, opts(o));
      return { id: sent?.key?.id || null };
    },
    async sendMedia(to, { type, mime, name, caption, buffer }, o) {
      const content =
        type === 'image' ? { image: buffer, caption: caption || undefined, mimetype: mime } :
        type === 'video' ? { video: buffer, caption: caption || undefined, mimetype: mime } :
        type === 'audio' ? { audio: buffer, mimetype: mime, ptt: /ogg|opus/.test(mime || '') } :
        { document: buffer, mimetype: mime || 'application/octet-stream', fileName: name || 'berkas', caption: caption || undefined };
      const sent = await sock.sendMessage(await resolveTarget(to), content, opts(o));
      return { id: sent?.key?.id || null };
    },
    async groups() {
      return listGroups();
    },
    async logout() {
      stopped = true;
      await sock?.logout();
    },
    async close() {
      stopped = true;
      sock?.end(undefined);
    },
  };
}
