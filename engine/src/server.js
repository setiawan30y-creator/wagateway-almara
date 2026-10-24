/*
 * ALMARA WA Engine — jalankan:  npm start
 * @2026-Alifiandra
 */
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import pino from 'pino';
import QRCode from 'qrcode';
import { loadEnv } from './env.js';
import { SessionManager } from './sessions.js';
import { createApp } from './app.js';
import { createBaileysDriver } from './baileys-driver.js';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
loadEnv(join(root, '.env'));

const PORT = Number(process.env.PORT || 3000);
const HOST = process.env.HOST || '127.0.0.1';
const TOKEN = process.env.ENGINE_TOKEN || '';
const logger = pino({ level: process.env.LOG_LEVEL || 'warn' });

async function notify(url, token, payload) {
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Webhook-Token': token },
    body: JSON.stringify(payload),
    signal: AbortSignal.timeout(10000),
  });
  if (!res.ok) throw new Error('HTTP ' + res.status);
}

const manager = new SessionManager({ storageDir: join(root, 'storage'), createDriver: createBaileysDriver, notify, logger });
const app = createApp({ manager, token: TOKEN, toDataUrl: (qr) => QRCode.toDataURL(qr, { margin: 1, width: 300, errorCorrectionLevel: 'M' }) });

app.listen(PORT, HOST, async () => {
  console.log(`ALMARA WA Engine berjalan di http://${HOST}:${PORT}`);
  if (!TOKEN) console.warn('PERINGATAN: ENGINE_TOKEN kosong — isi di engine/.env untuk keamanan.');
  await manager.restore();
});

for (const sig of ['SIGINT', 'SIGTERM']) {
  process.on(sig, async () => {
    await manager.shutdown();
    process.exit(0);
  });
}
