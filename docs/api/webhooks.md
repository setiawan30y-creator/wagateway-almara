# Webhook

## A. Masuk dari provider → Gateway

### Meta Cloud API
- Callback URL: `{APP_URL}/api/webhooks/meta` · Verify token: `META_WEBHOOK_VERIFY_TOKEN`
- Signature `X-Hub-Signature-256` divalidasi bila `META_APP_SECRET` diisi.
- Subscribe field **messages**. Event `messages` → masuk ke Inbox; event `statuses` → update status pesan (`sent → delivered → read` / `failed`, tidak pernah mundur; callback duplikat diabaikan).

### Engine QR Multi-Device
- `POST {APP_URL}/api/webhooks/qr/{device_id}` · header `X-Webhook-Token: <Token Webhook Masuk perangkat>`
- Pesan masuk: `{"type":"message","from":"628…","message":"…","id":"…","name":"…","media":{"type":"image","mime":"image/jpeg","name":null,"data":"<base64>"}}` (media opsional, maks. 16 MB)
- Status pesan: `{"type":"status","id":"…","status":"delivered|read|failed"}`
- Status sesi: `{"type":"session","status":"connected|reconnecting|logged_out","phone":"628…"}`

## B. Keluar dari Gateway → sistem Anda

Kelola di menu **Webhook** atau `POST /api/v1/webhooks`.

| Event | Kapan |
|---|---|
| `message.received` | Pesan masuk baru |
| `message.status` | Status pesan keluar berubah (sent/delivered/read/failed) |
| `contact.opted_out` | Kontak membalas kata kunci STOP |
| `campaign.finished` | Campaign selesai atau dijeda otomatis |
| `ticket.created` | Tiket dibuat |
| `ticket.updated` | Status / prioritas / penugasan tiket berubah |

Header setiap request:

```
Content-Type: application/json
X-Almara-Event: message.received
X-Almara-Delivery: evt_01j...        (unik — gunakan untuk idempotensi)
X-Almara-Timestamp: 1792731600
X-Almara-Signature: sha256=<HMAC_SHA256(secret, timestamp + "." + body)>
```

Verifikasi (PHP):

```php
$expected = 'sha256='.hash_hmac('sha256', $_SERVER['HTTP_X_ALMARA_TIMESTAMP'].'.'.file_get_contents('php://input'), $secret);
$valid = hash_equals($expected, $_SERVER['HTTP_X_ALMARA_SIGNATURE']) && abs(time() - (int) $_SERVER['HTTP_X_ALMARA_TIMESTAMP']) < 300;
```

- Balas HTTP 2xx dalam 10 detik. Selain itu dianggap gagal dan dicoba ulang (30 dtk, 2 mnt, 10 mnt) bila queue worker aktif.
- Event bisa datang lebih dari sekali — simpan `X-Almara-Delivery` untuk menolak duplikat.
- Signing secret ditampilkan sekali; bisa dirotasi dari panel.
