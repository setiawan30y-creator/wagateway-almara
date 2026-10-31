# Changelog API

## 0.7.0 — 2026-10-31
- Objek Campaign punya field `template_ids` (campaign multi-template; varian diacak per penerima)
- Isi template mendukung spintax `{a|b|c}` saat dirender

## 0.6.0 — 2026-10-31
- `POST /v1/messages/send` menerima `to` berupa ID grup (`…@g.us`, khusus perangkat Scan QR); error `GROUP_NOT_FOUND` bila grup belum disinkron
- Objek Message punya field `group` (`jid`, `participant`, `participant_name`) untuk pesan grup; event `message.received` dari grup mengirim `contact: null`
- Webhook engine QR: event baru `contacts`, `history`, `groups`, dan pesan grup

## 0.4.0 — 2026-10-24
- `POST /v1/messages/media` — kirim gambar / dokumen / audio / video (multipart, maks. 16 MB); objek Message punya field `media`
- Pesan masuk bermedia dari Meta & engine QR disimpan dan tampil di Inbox
- `GET/POST /v1/tickets` — ticketing dengan SLA & eskalasi
- `GET /v1/smart-links` — statistik klik / chat / konversi
- Event webhook baru: `ticket.created`, `ticket.updated`

## 0.2.0 — 2026-10-24 (Fase MVP)
- `GET/POST /v1/contacts` (upsert berdasarkan nomor, tag, consent)
- `GET /v1/templates`
- `GET/POST /v1/campaigns`, `GET /v1/campaigns/{id}` (statistik & proteksi broadcast)
- `GET/POST /v1/webhooks`, `DELETE /v1/webhooks/{id}` — webhook keluar bertanda tangan HMAC
- `GET /v1/analytics`
- Pesan masuk via webhook Meta & `POST /webhooks/qr/{device}`; field `direction`, `contact_id`, `campaign_id` di objek Message; status `received`
- Log request API (tanpa body/secret)

## 0.1.0 — 2026-10-23
- `POST /v1/messages/send` dengan idempotency key
- `GET /v1/messages`, `GET /v1/messages/{id}`, `GET /v1/devices`
- Webhook Meta Cloud API untuk status pesan
- Envelope respons & kode error standar, rate limit per API key
