# Changelog API

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
