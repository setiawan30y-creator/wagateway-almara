# Rate limit, pagination, idempotency

**Rate limit** — per API key, default 60 request/menit (`WA_API_RATE_LIMIT` di `.env`). Melebihi batas → `429 RATE_LIMITED` dengan header `Retry-After`.

**Pagination** — `GET /v1/messages?page=2&per_page=50` (maks. 100). Info di `meta.pagination`.

**Idempotency** — kirim header `Idempotency-Key: <uuid>` pada `POST /v1/messages/send`.
Request ulang dengan key + isi yang sama → `200` + header `Idempotent-Replayed: true`, pesan **tidak** dikirim lagi.
Key sama dengan isi berbeda → `409 IDEMPOTENCY_CONFLICT`.
