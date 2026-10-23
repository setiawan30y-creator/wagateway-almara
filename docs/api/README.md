# ALMARA Gateway WA — Dokumentasi API

Versi API: **v1** (aplikasi 0.1.0) · Base URL: `{APP_URL}/api`

| Method | Path | Fungsi | Status |
|---|---|---|---|
| POST | `/v1/messages/send` | Kirim pesan teks | ✅ tersedia |
| GET | `/v1/messages` | Daftar pesan (filter `status`, `to`, pagination) | ✅ tersedia |
| GET | `/v1/messages/{id}` | Detail pesan | ✅ tersedia |
| GET | `/v1/devices` | Daftar perangkat (tanpa kredensial) | ✅ tersedia |
| GET/POST | `/webhooks/meta` | Webhook Meta Cloud API (status pesan) | ✅ tersedia |
| POST | `/v1/messages/media`, `/v1/campaigns`, `/v1/webhooks`, ... | Roadmap blueprint | ⏳ belum |

Kontrak lengkap: [`openapi.yaml`](openapi.yaml) / [`openapi.json`](openapi.json) (dihasilkan dari YAML yang sama).
Postman: [`../postman`](../postman).

- [Autentikasi](authentication.md)
- [Format error](errors.md)
- [Rate limit & pagination](rate-limits.md)
- [Webhook](webhooks.md)
- [Changelog](changelog.md)

Contoh request/response JSON ada di [`examples/`](examples). Semua contoh memakai data fiktif.

<sub>@2026-Alifiandra</sub>
