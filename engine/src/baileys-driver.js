/*
 * Driver WhatsApp Web Multi-Device memakai Baileys.
 * Catatan: ini jalur tidak resmi (bukan Meta Cloud API). Gunakan dengan bijak —
 * kirim hanya ke kontak yang memberi izin, hindari spam, agar nomor tidak diblokir.
 * @2026-Alifiandra
 */
export async function createBaileysDriver(ctx) {
  const baileys = await import('baileys');
  const makeWASocket = baileys.default?.default || baileys.default || baileys.makeWASocket;
  const {
    useMultiFileAuthState, DisconnectReason, Browsers, fetchLatestBaileysVersion,
    jidNormalizedUser, isJidGroup, isJidBroadcast, isJidStatusBroadcast, isPnUser,
    normalizeMessageContent, getContentType, downloadMediaMessage,
  } = baileys;

  const MAX_MEDIA = 16 * 1024 * 1024;
  const MEDIA_KINDS = { imageMessage: 'image', videoMessage: 'video', audioMessage: 'audio', documentMessage: 'document', stickerMessage: 'image' };

  let sock = null;
  let stopped = false;
  let retry = 0;

  const digits = (jid) => (jid ? String(jid).split('@')[0].split(':')[0].replace(/\D/g, '') : null);

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
      syncFullHistory: false,
    });

    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('connection.update', (u) => {
      if (u.qr) ctx.onQr(u.qr);

      if (u.connection === 'open') {
        retry = 0;
        ctx.onOpen({ phone: digits(sock.user?.id), pushName: sock.user?.name || null });
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

    sock.ev.on('messages.upsert', async ({ messages, type }) => {
      if (type !== 'notify') return;

      for (const m of messages) {
        const jid = m.key?.remoteJid;
        if (!jid || m.key.fromMe || isJidGroup(jid) || isJidBroadcast(jid) || isJidStatusBroadcast(jid)) continue;

        // WhatsApp kini memakai LID; ambil nomor asli dari remoteJidAlt bila tersedia.
        const pnJid = isPnUser(jid) ? jid : (isPnUser(m.key.remoteJidAlt) ? m.key.remoteJidAlt : null);
        if (!pnJid) continue;

        const content = normalizeMessageContent(m.message);
        const kind = getContentType(content);
        if (!content || !kind || kind === 'protocolMessage' || kind === 'reactionMessage' || kind === 'senderKeyDistributionMessage') continue;

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

        const label = {
          imageMessage: '[gambar]', videoMessage: '[video]', audioMessage: '[audio]', documentMessage: '[dokumen]',
          stickerMessage: '[stiker]', locationMessage: '[lokasi]', contactMessage: '[kontak]',
        }[kind];

        // Unduh media (maks. 16 MB) lalu teruskan sebagai base64 ke gateway
        let media = null;
        if (MEDIA_KINDS[kind]) {
          const info = content[kind] || {};
          const size = Number(info.fileLength || 0);
          if (!size || size <= MAX_MEDIA) {
            try {
              const buf = await downloadMediaMessage(m, 'buffer', {}, { logger: ctx.logger, reuploadRequest: sock.updateMediaMessage });
              if (buf && buf.length <= MAX_MEDIA) {
                media = { type: MEDIA_KINDS[kind], mime: info.mimetype || 'application/octet-stream', name: info.fileName || null, data: Buffer.from(buf).toString('base64') };
              }
            } catch (e) {
              ctx.logger.warn({ err: e?.message }, 'gagal mengunduh media masuk');
            }
          }
        }

        const body = (media ? text : [label, text].filter(Boolean).join(' ')).trim();
        if (!body && !media) continue;

        ctx.onMessage({ from: digits(jidNormalizedUser(pnJid)), message: body, id: m.key.id, name: m.pushName || null, media });
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

  await connect();

  return {
    async sendText(to, text) {
      const number = String(to).replace(/\D/g, '');
      const jid = `${number}@s.whatsapp.net`;
      const [check] = (await sock.onWhatsApp(jid).catch(() => [])) || [];
      if (check && check.exists === false) {
        const err = new Error('Nomor tidak terdaftar di WhatsApp.');
        err.status = 422;
        throw err;
      }
      const sent = await sock.sendMessage(check?.jid || jid, { text });
      return { id: sent?.key?.id || null };
    },
    async sendMedia(to, { type, mime, name, caption, buffer }) {
      const number = String(to).replace(/\D/g, '');
      const jid = `${number}@s.whatsapp.net`;
      const [check] = (await sock.onWhatsApp(jid).catch(() => [])) || [];
      if (check && check.exists === false) {
        const err = new Error('Nomor tidak terdaftar di WhatsApp.');
        err.status = 422;
        throw err;
      }
      const content =
        type === 'image' ? { image: buffer, caption: caption || undefined, mimetype: mime } :
        type === 'video' ? { video: buffer, caption: caption || undefined, mimetype: mime } :
        type === 'audio' ? { audio: buffer, mimetype: mime, ptt: /ogg|opus/.test(mime || '') } :
        { document: buffer, mimetype: mime || 'application/octet-stream', fileName: name || 'berkas', caption: caption || undefined };
      const sent = await sock.sendMessage(check?.jid || jid, content);
      return { id: sent?.key?.id || null };
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
