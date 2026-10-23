# Webhook

## Meta Cloud API → Gateway (masuk)

- Callback URL: `{APP_URL}/api/webhooks/meta`
- Verify token: nilai `META_WEBHOOK_VERIFY_TOKEN` di `.env`
- Signature: bila `META_APP_SECRET` diisi, header `X-Hub-Signature-256` divalidasi (HMAC-SHA256 body).
- Subscribe field **messages** di Meta App Dashboard.
- Event `statuses` memperbarui status pesan: `sent → delivered → read` (atau `failed`). Callback duplikat/terlambat diabaikan karena status tidak boleh mundur.
- Pesan masuk (inbox) baru dicatat di log; modul Inbox ada di roadmap.

## Gateway → aplikasi Anda (keluar)

Belum tersedia di versi 0.1.0 (roadmap: event `message.status`, signature, retry).
