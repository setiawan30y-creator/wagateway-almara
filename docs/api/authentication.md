# Autentikasi

1. Login ke panel → menu **API Key** → isi label → **Buat API key**.
2. Key penuh (`alm_…`) **hanya ditampilkan sekali**. Database menyimpan hash SHA-256, bukan key aslinya.
3. Kirim key di salah satu header:

```
Authorization: Bearer alm_xxxxxxxxxxxxxxxx
X-Api-Key: alm_xxxxxxxxxxxxxxxx
```

- Cabut key kapan saja dari menu API Key (respon berikutnya `401 UNAUTHENTICATED`).
- Rotasi: buat key baru → ganti di aplikasi Anda → cabut key lama.
- Panel admin memakai login sesi (email + password), terpisah dari API key.
- Selalu gunakan HTTPS di produksi.
