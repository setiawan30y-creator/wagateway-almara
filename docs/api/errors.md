# Format respons & error

Sukses:

```json
{ "success": true, "data": { "id": "msg_01jexample", "status": "sent" }, "meta": { "request_id": "req_01jexample" } }
```

Error:

```json
{ "success": false, "error": { "code": "VALIDATION_ERROR", "message": "Request tidak valid.", "details": { "to": ["Field tujuan wajib diisi."] } }, "meta": { "request_id": "req_01jexample" } }
```

| HTTP | `error.code` | Arti |
|---|---|---|
| 401 | `UNAUTHENTICATED` | API key tidak ada, salah, atau dicabut |
| 403 | `FORBIDDEN` | Tidak berwenang |
| 404 | `NOT_FOUND` / `DEVICE_NOT_FOUND` | Resource / perangkat aktif tidak ditemukan |
| 409 | `IDEMPOTENCY_CONFLICT` | `Idempotency-Key` sudah dipakai untuk isi request berbeda |
| 422 | `VALIDATION_ERROR` | Input tidak valid (`details` per field) |
| 429 | `RATE_LIMITED` | Melebihi batas request per menit |
| 500 | `SERVER_ERROR` | Kesalahan server (tanpa stack trace) |

Header `X-Request-Id` selalu dikirim dan sama dengan `meta.request_id` — sertakan saat melapor masalah.
