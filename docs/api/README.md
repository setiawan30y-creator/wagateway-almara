# ALMARA Gateway WA — Dokumentasi API

Versi API: **v1** (aplikasi 0.4.0) · Base URL: `{APP_URL}/api`

| Method | Path | Fungsi | Status |
|---|---|---|---|
| POST | `/v1/messages/send` | Kirim pesan teks | ✅ tersedia |
| GET | `/v1/messages` | Daftar pesan (filter `status`, `to`, pagination) | ✅ tersedia |
| GET | `/v1/messages/{id}` | Detail pesan | ✅ tersedia |
| GET | `/v1/devices` | Daftar perangkat (tanpa kredensial) | ✅ tersedia |
| GET/POST | `/v1/contacts` | Daftar / upsert kontak (tag, consent) | ✅ tersedia |
| GET | `/v1/templates` | Template aktif + varian | ✅ tersedia |
| GET/POST | `/v1/campaigns`, GET `/v1/campaigns/{id}` | Broadcast + statistik | ✅ tersedia |
| GET/POST/DELETE | `/v1/webhooks` | Webhook keluar (HMAC) | ✅ tersedia |
| GET | `/v1/analytics` | Ringkasan analitik | ✅ tersedia |
| GET/POST | `/webhooks/meta` | Webhook Meta (pesan masuk + status) | ✅ tersedia |
| POST | `/webhooks/qr/{device}` | Pesan masuk engine QR | ✅ tersedia |
| POST | `/v1/messages/media` | Kirim media (multipart) | ✅ tersedia |
| GET/POST | `/v1/tickets` | Tiket helpdesk | ✅ tersedia |
| GET | `/v1/smart-links` | Smart link + statistik | ✅ tersedia |
| GET | `/v1/groups` | Roadmap blueprint | ⏳ belum |

Kontrak lengkap: [`openapi.yaml`](openapi.yaml) / [`openapi.json`](openapi.json) (dihasilkan dari YAML yang sama).
Postman: [`../postman`](../postman).

- [Autentikasi](authentication.md)
- [Format error](errors.md)
- [Rate limit & pagination](rate-limits.md)
- [Webhook](webhooks.md)
- [Changelog](changelog.md)

Contoh request/response JSON ada di [`examples/`](examples). Semua contoh memakai data fiktif.

<sub>@2026-Alifiandra</sub>

## Kompatibel aplikasi lain (gaya Fonnte / Wablas)

Banyak aplikasi billing / kasir sudah punya pengaturan untuk gateway WhatsApp populer. Cukup ganti **alamat** dan **token** dengan milik ALMARA:

| Gaya | Alamat | Token | Parameter |
|---|---|---|---|
| Fonnte | `POST {APP_URL}/api/send` | Header `Authorization: alm_xxx` (tanpa "Bearer") | `target` (boleh banyak, dipisah koma), `message` |
| Wablas | `POST` atau `GET {APP_URL}/api/send-message` | Header `Authorization: alm_xxx` atau parameter `token` | `phone`, `message` |

Respons: `{"status": true, …}` bila pesan masuk antrean, `{"status": false, "reason"/"message": "…"}` bila gagal.
Token juga diterima di semua endpoint `/api/v1/*` lewat `Authorization: Bearer alm_xxx`, `Authorization: alm_xxx`, `X-Api-Key`, atau parameter `token` / `api_key`.
