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
| Fonnte | `POST {APP_URL}/api/send` | Header `Authorization: alm_xxx` (tanpa "Bearer") | `target` (boleh banyak, dipisah koma), `message`, opsional `url` (gambar/berkas publik, mis. QRIS) atau upload `file` — `message` jadi caption |
| Wablas | `POST` atau `GET {APP_URL}/api/send-message` | Header `Authorization: alm_xxx` atau parameter `token` | `phone`, `message` |

Respons: `{"status": true, …}` bila pesan masuk antrean, `{"status": false, "reason"/"message": "…"}` bila gagal.
Token juga diterima di semua endpoint `/api/v1/*` lewat `Authorization: Bearer alm_xxx`, `Authorization: alm_xxx`, `X-Api-Key`, atau parameter `token` / `api_key`.

### Token per perangkat (seperti token device Fonnte)

Setiap nomor di menu **Perangkat** otomatis punya token API — kolom **Aksi** → **🔑 Salin token**. Tombol **🔗 Integrasi** (juga di kolom Aksi) menampilkan token, URL gaya Fonnte / Wablas / REST, link uji GET, dan contoh cURL — semuanya bisa disalin.

- Pesan yang dikirim dengan token sebuah nomor **otomatis keluar dari nomor itu** (tidak perlu `device_id`). Urutan pemilihan nomor: `device_id` di request → nomor milik token → nomor utama.
- **Buat ulang token** di panel Integrasi mencabut token lama seketika. Menghapus perangkat juga mencabut tokennya.
- API key di menu **API Key** juga bisa diikat ke nomor tertentu ("Kirim dari") dan bisa dilihat / disalin kapan saja. Key buatan sebelum versi 1.0.2 hanya tersimpan hash-nya (tampil "Key lama") — tetap berfungsi, tetapi tidak bisa ditampilkan ulang.
- Key disimpan terenkripsi dengan `APP_KEY`; jangan mengganti `APP_KEY` setelah aplikasi dipakai.

Uji cepat dari browser:
```
{APP_URL}/api/send-message?token=alm_xxx&phone=08xxxxxxxxxx&message=Halo
```
