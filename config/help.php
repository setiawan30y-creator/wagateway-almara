<?php

/*
|--------------------------------------------------------------------------
| Bantuan per halaman (tombol "?" di setiap halaman)
|--------------------------------------------------------------------------
| Kunci = nama route (exact) atau pola dengan * (mis. "contacts.*").
| fungsi : penjelasan singkat halaman
| langkah: cara pakai berurutan
| tips   : catatan penting
| doc    : slug halaman Panduan terkait (opsional) + anchor
| @2026-Alifiandra
*/

return [

    'dashboard' => [
        'judul' => 'Dashboard',
        'fungsi' => 'Ringkasan kondisi akun usaha Anda: jumlah pesan 7 hari, pesan gagal, percakapan yang belum dibaca, kontak, perangkat, dan broadcast yang sedang berjalan.',
        'langkah' => [
            'Lihat kartu angka di atas untuk kondisi terkini.',
            'Grafik "Lalu lintas pesan" membandingkan pesan keluar & masuk per hari.',
            'Klik nama pada "Percakapan terbaru" untuk langsung membalas di Inbox.',
            'Periksa status perangkat — harus "connected" agar pesan terkirim.',
        ],
        'tips' => [
            'Label di samping logo (GRATIS / PRO) menunjukkan paket akun Anda. Kotak kuning muncul bila masa sewa hampir habis.',
            'Angka "Gagal" tinggi? Cek perangkat dan menu Riwayat untuk melihat penyebabnya.',
            'Di komputer (Laragon), klik dua kali mulai.bat agar jendela Scheduler & Engine berjalan — tanpa keduanya broadcast dan pesan WhatsApp tidak diproses.',
        ],
    ],

    'inbox.*' => [
        'judul' => 'Inbox',
        'fungsi' => 'Tempat membaca dan membalas semua chat pelanggan dari semua nomor WhatsApp dalam satu layar.',
        'langkah' => [
            'Pilih percakapan di kolom kiri. Gunakan tab Open / Belum dibaca / Milik saya untuk menyaring.',
            'Tulis balasan di kotak bawah, tekan Enter untuk kirim (Shift+Enter untuk baris baru).',
            'Klik 📎 Lampiran untuk mengirim gambar, dokumen, audio, atau video (maks. 16 MB).',
            'Klik ✨ Saran AI agar AI menyusun draf balasan (perlu AI Agent aktif), lalu edit sebelum dikirim.',
            'Pakai "Balasan cepat" untuk mengisi teks dari Template.',
            'Panel kanan: tugaskan ke CS, tulis catatan internal (tidak terlihat pelanggan), buat Tiket, atau matikan AI untuk chat ini.',
            'Ubah status Open / Pending / Closed dan prioritas di bagian atas percakapan.',
            'Tab "Grup" menampilkan chat grup WhatsApp (👥). Nama pengirim tampil di atas tiap pesan; balasan Anda terkirim ke grup.',
        ],
        'tips' => [
            'Chat lama dari HP ikut muncul setelah scan QR (riwayat terbaru yang dikirim WhatsApp) — tidak memicu auto-reply / AI.',
            'Di chat grup, auto-reply, AI Agent, dan kata kunci STOP tidak aktif agar tidak mengganggu anggota.',
            'Belum punya nomor tersambung? Gunakan tombol "Simulasi pesan masuk" untuk mencoba.',
            'Saat CS membalas, AI otomatis berhenti di chat itu selama waktu yang diatur di AI Agent.',
            'Lewat Meta API, pesan teks bebas hanya bisa dikirim ≤24 jam sejak pelanggan terakhir chat.',
        ],
    ],

    'contacts.index' => [
        'judul' => 'Kontak & CRM',
        'fungsi' => 'Daftar semua pelanggan / lead. Kontak dibuat otomatis saat ada chat, atau bisa ditambah, di-import, dan disinkron dari HP.',
        'langkah' => [
            'Cari dan saring berdasarkan tahap, tag, atau izin (opt-in / opt-out).',
            '+ Kontak untuk menambah satu kontak; Import CSV untuk banyak kontak sekaligus (kolom: name, phone, email, tags).',
            'Centang beberapa kontak lalu pilih Aksi massal: tambah/hapus tag, masukkan ke rombongan (isi nama rombongan), tandai opt-in/opt-out, ubah tahap.',
            'Klik nama untuk melihat profil, riwayat chat, dan riwayat izin.',
            'Tombol Pipeline menampilkan kontak per tahap penjualan (kanban).',
            'Sinkron dari HP: mengambil kontak yang tersimpan di HP (perangkat Scan QR). Nama yang sudah Anda isi tidak ditimpa.',
        ],
        'tips' => [
            'Nama kontak dipakai untuk {{name}} / {{first_name}} di template. Kontak tanpa nama disapa "Pelanggan".',
            'Broadcast (bawaan) hanya dikirim ke kontak berstatus "Izin (opt-in)". Tandai opt-in hanya untuk yang memang setuju.',
            'Kontak hasil sinkron / import berstatus "belum ada izin" kecuali Anda centang "sudah memberi izin".',
            'WhatsApp mengirim daftar kontak beberapa saat setelah scan QR. Bila hasil sinkron masih 0, tunggu beberapa menit lalu coba lagi.',
            'Kontak yang membalas STOP otomatis menjadi opt-out dan tidak pernah dikirimi broadcast.',
        ],
    ],

    'contacts.*' => [
        'judul' => 'Detail / Form Kontak',
        'fungsi' => 'Melihat atau mengubah data satu pelanggan: nomor, tahap pipeline, tag, izin menerima pesan, penanggung jawab, dan catatan.',
        'langkah' => [
            'Nomor boleh ditulis 08xx, 628xx, atau +628xx — otomatis dirapikan.',
            'Tag dipisahkan koma (mis. vip, reseller). Tag dipakai untuk memilih penerima broadcast.',
            'Ubah "Izin menerima pesan" hanya bila pelanggan benar-benar setuju / menolak.',
            'Di halaman detail: Buka chat, + Tiket, atau lihat timeline pesan & riwayat izin.',
        ],
        'tips' => [],
    ],

    'tickets.*' => [
        'judul' => 'Tiket (Helpdesk)',
        'fungsi' => 'Mencatat keluhan / permintaan pelanggan agar ditangani sampai selesai, lengkap dengan batas waktu (SLA) dan penanggung jawab.',
        'langkah' => [
            '+ Tiket untuk membuat tiket baru, atau dari Inbox klik "+ Tiket" agar pelanggan terisi otomatis.',
            'Pilih kategori, prioritas (menentukan batas waktu SLA), dan CS yang ditugaskan.',
            'Centang "Kirim nomor tiket ke pelanggan" agar pelanggan menerima nomor tiket via WhatsApp.',
            'Di detail tiket: ubah status, tambah catatan internal, lalu klik "Selesaikan & beri tahu pelanggan" bila beres.',
            'Tab "Lewat SLA" menampilkan tiket yang terlambat.',
        ],
        'tips' => [
            'Tiket yang lewat SLA dieskalasi otomatis (prioritas naik + notifikasi admin) — perlu jendela "php artisan schedule:work" berjalan.',
            'Lama SLA per prioritas diatur di menu Pengaturan.',
        ],
    ],

    'send.*' => [
        'judul' => 'Kirim Pesan',
        'fungsi' => 'Mengirim pesan WhatsApp langsung ke beberapa nomor sekaligus, dengan atau tanpa lampiran — langsung terkirim, tanpa jeda broadcast.',
        'langkah' => [
            'Pilih perangkat (nomor WhatsApp) pengirim.',
            'Isi nomor tujuan — pisahkan dengan koma atau baris baru.',
            'Tulis pesan. Bila melampirkan berkas, pesan menjadi keterangan (caption).',
            'Klik Kirim. Hasil dapat dilihat di menu Riwayat.',
        ],
        'tips' => [
            'Untuk kirim ke banyak kontak (puluhan / ratusan), gunakan Broadcast: ada jeda acak, kuota harian, dan pengaman anti-blokir.',
            'Paket Gratis: maksimal 100 pesan keluar per hari dan setiap pesan diberi watermark.',
            'Kirim hanya ke orang yang memberi izin agar nomor tidak diblokir WhatsApp.',
        ],
    ],

    'messages.*' => [
        'judul' => 'Riwayat Pesan',
        'fungsi' => 'Catatan semua pesan masuk & keluar beserta statusnya (queued, sent, delivered, read, failed, received).',
        'langkah' => [
            'Cari berdasarkan nomor, isi pesan, atau ID; saring berdasarkan status & perangkat.',
            'Klik Detail untuk melihat isi lengkap, lampiran, dan pesan error.',
            'Pesan berstatus failed bisa dikirim ulang dari halaman detail.',
            'Export CSV untuk mengunduh riwayat.',
        ],
        'tips' => [
            'Kolom "Sumber" menunjukkan asal pesan: Panel, API, Broadcast, atau Masuk.',
        ],
    ],

    'campaigns.*' => [
        'judul' => 'Broadcast',
        'fungsi' => 'Mengirim pesan massal ke rombongan / tag / tahap tertentu, dengan varian pesan yang diacak agar tidak seragam, plus pengaman anti-blokir otomatis.',
        'langkah' => [
            'Siapkan Template (boleh beberapa, masing-masing dengan varian A/B/C dan lampiran foto / file).',
            '+ Campaign → isi nama, pilih "Kirim melalui" = nomor Scan QR Anda (bukan Simulasi), dan kecepatan.',
            'Template pesan: centang template, lalu pilih varian mana saja yang dipakai (A / B / C). Varian dari beberapa template boleh dicampur; "Pilih semua" untuk semuanya.',
            'Penerima: centang Rombongan (dan bila perlu tag / tahap — semuanya saling mempersempit). Kotak HIJAU = jumlah yang akan dikirimi; kotak MERAH = tidak ada yang terkirim, beserta alasannya.',
            'Opsional "3. Lampiran foto / file": berlaku untuk semua penerima. Bila kosong, lampiran milik template terpilih yang dikirim.',
            '"4. Kirim": pilih 🚀 Kirim sekarang, 🕒 Jadwalkan, atau 📝 Simpan draft, lalu klik tombol di bawahnya. Draft dikirim nanti lewat tombol "🚀 Kirim sekarang" di halaman campaign.',
            'Halaman campaign: panel "Proses pengiriman" menampilkan realtime nomor yang sedang dikirim (menunggu jeda / mengetik / mengirim), antrean berikutnya, dan yang baru terkirim. Kotak kuning/merah menjelaskan bila pengiriman menunggu.',
            'Jeda / Lanjutkan / Batalkan bila perlu. Duplikat: salin campaign (template, varian, lampiran) untuk rombongan lain atau kirim ulang.',
        ],
        'tips' => [
            'Penerima "dilewati" bila: belum opt-in, opt-out, atau sudah menerima broadcast dalam rentang Frequency cap (bawaan 24 jam). Tandai opt-in lewat Rombongan → "Tandai anggota opt-in".',
            '"Sedang jam tenang — pengiriman menunggu": broadcast tidak dikirim pada jam tenang (bawaan 21:00–08:00) dan otomatis lanjut setelahnya. Ubah di Pengaturan.',
            'Anti-blokir (Scan QR): pesan dikirim satu per satu dengan jeda acak + "sedang mengetik…", dibatasi kuota harian per nomor (nomor baru dipanaskan bertahap). Kuota habis → lanjut besok otomatis.',
            'Variasi kata: tulis {Halo|Hai|Selamat siang} di template — tiap penerima mendapat salah satunya.',
            'Perangkat "Simulasi" tidak benar-benar mengirim ke WhatsApp.',
            'Biarkan jendela Scheduler & Engine (mulai.bat) terbuka sampai campaign selesai. Status "Selesai" muncul setelah antrean di engine habis terkirim.',
            'Fitur Broadcast hanya untuk paket Pro.',
        ],
    ],

    'lists.*' => [
        'judul' => 'Rombongan Penerima',
        'fungsi' => 'Kelompok kontak per peruntukan (mis. Reseller, Pelanggan VIP, Peserta Event) yang dipilih saat membuat broadcast.',
        'langkah' => [
            '+ Rombongan → beri nama & keterangan peruntukan.',
            'Tempel nomor, boleh dengan nama — satu orang per baris: "081234567890, Nama Lengkap" atau "Nama - 0812…". Bisa juga salin 2 kolom (Nomor | Nama) dari Excel.',
            'Dari tag: masukkan semua kontak yang punya tag tertentu (saat tombol diklik).',
            'Dari anggota grup WhatsApp: masukkan anggota grup (sinkron dulu di menu Grup WA) untuk dikirimi pesan pribadi.',
            'Salin dari rombongan lain, atau dari menu Kontak: centang → Aksi massal → "Masukkan ke rombongan".',
            'Kotak kuning "anggota belum memberi izin": centang pernyataan lalu "Tandai … anggota opt-in" agar mereka ikut broadcast.',
            'Klik "Broadcast ke rombongan ini" untuk langsung membuat campaign. Centang anggota → "Keluarkan dari rombongan" untuk menghapus dari kelompok.',
        ],
        'tips' => [
            'Angka "Siap broadcast (opt-in)" = jumlah anggota yang benar-benar akan dikirimi.',
            'Satu kontak boleh masuk beberapa rombongan; saat broadcast ke beberapa rombongan, kontak yang sama tetap hanya dikirimi sekali.',
            'Nama yang sudah ada di Kontak tidak ditimpa saat menempel nomor.',
            'Anggota dari grup WA masuk tanpa nama & "belum ada izin" — tandai opt-in hanya bila mereka setuju.',
        ],
    ],

    'templates.*' => [
        'judul' => 'Template',
        'fungsi' => 'Menyimpan teks pesan (dan lampiran foto / file) siap pakai untuk Broadcast dan Balasan cepat di Inbox, dengan variabel personalisasi.',
        'langkah' => [
            '+ Template → isi nama & kategori, centang Aktif.',
            'Tulis Varian A (wajib). Varian B & C opsional — di Broadcast Anda bisa memilih varian mana yang dipakai.',
            'Klik tombol variabel untuk menyisipkan {{name}}, {{first_name}}, {{phone}}, {{email}}.',
            'Variasi kata: tulis {Halo|Hai|Selamat siang} — tiap penerima mendapat salah satunya.',
            'Lampiran foto / file (opsional): ikut terkirim setiap kali template dipakai broadcast; isi varian menjadi keterangannya. Saat mengubah, centang "Hapus lampiran ini" atau pilih berkas baru untuk mengganti.',
            'Lihat hasil di kotak Pratinjau, lalu Simpan.',
        ],
        'tips' => [
            'Template nonaktif tidak muncul di pilihan Broadcast.',
            'Buat beberapa template / varian dengan gaya berbeda agar pesan tidak seragam (lebih aman dari deteksi robot/spam).',
            'Format WhatsApp: *tebal*, _miring_, ~coret~.',
            'Performa tiap varian tampil di daftar template & detail campaign.',
        ],
    ],

    'smart-links.*' => [
        'judul' => 'Smart Link',
        'fungsi' => 'Membuat link pendek / QR yang membuka chat WhatsApp Anda dengan pesan otomatis, lalu melacak klik, chat, dan konversi.',
        'langkah' => [
            '+ Smart Link → isi nama (slug dibuat otomatis), nomor tujuan, dan pesan awal.',
            'Opsional: tag otomatis untuk lead, UTM untuk laporan sumber, tanggal kedaluwarsa.',
            'Di halaman detail: salin link atau unduh QR untuk dipasang di bio Instagram, website, brosur, atau iklan.',
            'Pantau klik → chat → konversi (kontak yang tahapnya menjadi Customer).',
        ],
        'tips' => [
            'Agar bisa diklik orang lain, aplikasi harus bisa diakses dari internet (lihat Panduan bagian 6).',
            'Kode [#slug] di akhir pesan dipakai untuk melacak asal chat — biarkan pelanggan mengirimnya.',
        ],
    ],

    'auto-replies.*' => [
        'judul' => 'Auto-Reply',
        'fungsi' => 'Membalas otomatis berdasarkan kata kunci, menyapa kontak baru, atau membalas di luar jam kerja.',
        'langkah' => [
            '+ Aturan → pilih pemicu: Kata kunci, Sambutan kontak baru, atau Di luar jam kerja.',
            'Untuk kata kunci: tulis kata dipisah koma (mis. harga, price list) dan pilih cara mencocokkan.',
            'Tulis balasan (boleh pakai {{first_name}}), atur cooldown & prioritas, lalu Simpan.',
            'Coba di kotak "Uji kata kunci".',
        ],
        'tips' => [
            'Satu pesan hanya dibalas satu aturan. Angka prioritas kecil diperiksa lebih dulu.',
            'Auto-Reply dicek sebelum AI Agent — cocok untuk pertanyaan yang jawabannya selalu sama.',
            'Jam kerja diatur di menu Pengaturan.',
        ],
    ],

    'ai.*' => [
        'judul' => 'AI Agent',
        'fungsi' => 'AI menjawab chat pelanggan sendiri berdasarkan Basis Pengetahuan Anda, dan menyerahkan ke CS bila tidak tahu.',
        'langkah' => [
            'Daftar di openrouter.ai → Keys → Create Key, salin key (sk-or-…).',
            'Tempel di kolom API key, centang "Aktifkan AI Agent", Simpan.',
            'Isi Basis pengetahuan: jam buka, alamat, produk & harga, ongkir, cara order, kebijakan. Satu topik per item.',
            'Coba di kotak "Uji AI" sebelum dipakai ke pelanggan.',
            'Pantau "Riwayat pemanggilan" untuk melihat jawaban sukses, diserahkan ke CS, atau error.',
        ],
        'tips' => [
            'Model gratis: ±50 permintaan/hari tanpa saldo, ±1.000/hari setelah isi saldo $10 (dapat berubah).',
            'AI berhenti otomatis saat CS membalas, dan bisa dimatikan per chat dari Inbox.',
            'Pelanggan yang mengetik "cs", "admin", dll. langsung diserahkan ke CS.',
        ],
    ],

    'devices.pair' => [
        'judul' => 'Scan QR',
        'fungsi' => 'Menautkan nomor WhatsApp ke gateway seperti WhatsApp Web.',
        'langkah' => [
            'Pastikan engine berjalan: klik dua kali mulai.bat (atau PowerShell → cd C:\\laragon\\www\\wagateway-almara\\engine → npm start).',
            'Di HP buka WhatsApp → ⋮ / Pengaturan → Perangkat tertaut → Tautkan perangkat.',
            'Arahkan kamera ke QR. QR berganti otomatis ±20 detik.',
            'Setelah terhubung, halaman kembali ke daftar perangkat. Kontak & chat terbaru dari HP ikut masuk beberapa saat kemudian.',
        ],
        'tips' => [
            'Buka aplikasi lewat alamat APP_URL (Laragon: http://wagateway-almara.test, bukan https).',
            'Muncul "Engine QR tidak dapat dihubungi"? Engine belum jalan atau token tidak sama (lihat Panduan bagian 10).',
        ],
    ],

    'devices.*' => [
        'judul' => 'Perangkat',
        'fungsi' => 'Mengelola nomor WhatsApp yang dipakai untuk mengirim & menerima pesan.',
        'langkah' => [
            'Tambah nomor — Scan QR: isi nama, lalu scan QR dari HP (cara termudah).',
            '+ Manual: untuk Meta Cloud API (resmi) — isi Phone Number ID & Access Token.',
            'Cek: memeriksa koneksi sekaligus mendaftarkan ulang alamat aplikasi ke engine (pakai bila pesan masuk / status tidak muncul).',
            'Jadikan utama: nomor default untuk pengiriman. Logout: melepas nomor dari WhatsApp (perlu scan ulang).',
            'Sinkron kontak / Sinkron grup (Scan QR yang terhubung): mengambil kontak HP ke menu Kontak dan grup ke menu Grup WA.',
            'Kolom Status menampilkan kuota broadcast hari ini (mis. 12/60 · warm-up hari 3).',
        ],
        'tips' => [
            'Nomor baru dipanaskan bertahap: hari 1–3 maks 30, 4–7 maks 60, 8–14 maks 120, 15–21 maks 200 broadcast/hari.',
            'Jendela engine menampilkan "fetch failed"? Pastikan APP_URL benar (http), lalu klik Cek.',
            'Perangkat "Simulasi" tidak mengirim sungguhan — untuk uji coba saja dan tidak dihitung dalam batas nomor paket.',
            'Jumlah nomor dibatasi paket (Gratis: 1). Kredensial disimpan terenkripsi.',
        ],
    ],

    'groups.*' => [
        'judul' => 'Grup WhatsApp',
        'fungsi' => 'Melihat grup yang diikuti nomor Scan QR beserta anggotanya, membuka chat grup, dan mengirim pesan / lampiran ke grup.',
        'langkah' => [
            'Klik "Sinkron grup dari HP" untuk mengambil daftar grup & anggota terbaru.',
            'Buka chat: percakapan grup terbuka di Inbox — baca & balas seperti chat biasa.',
            'Anggota: lihat daftar nomor & admin, kirim pesan / lampiran ke grup, atau simpan anggota ke Kontak (opsional dengan tag).',
            'Lewat API: kirim ke grup dengan "to" berisi ID grup (…@g.us) yang tertera di halaman detail grup.',
        ],
        'tips' => [
            'Hanya untuk perangkat Scan QR — Meta Cloud API tidak mendukung grup.',
            'Grup "khusus admin" hanya bisa dikirimi bila nomor Anda admin grup tersebut.',
            'Anggota yang disimpan ke Kontak tetap "belum ada izin"; jangan broadcast ke mereka tanpa persetujuan.',
            'Sebagian nomor anggota bisa tersembunyi (fitur privasi WhatsApp) dan tampil "(nomor disembunyikan)".',
        ],
    ],

    'api-keys.*' => [
        'judul' => 'API Key',
        'fungsi' => 'Kunci akses agar aplikasi lain (mis. aplikasi kasir) bisa mengirim pesan lewat REST API gateway ini.',
        'langkah' => [
            'Isi label (mis. Aplikasi Kasir) → Buat API key.',
            'Salin key yang muncul — hanya ditampilkan sekali.',
            'Gunakan di header Authorization: Bearer <key>. Contoh PowerShell & cURL ada di halaman ini.',
            'Cabut key bila tidak dipakai lagi atau bocor.',
        ],
        'tips' => [
            'Dokumentasi lengkap endpoint ada di menu Panduan → REST API.',
        ],
        'doc' => 'api',
    ],

    'webhooks.*' => [
        'judul' => 'Webhook',
        'fungsi' => 'Mengirim notifikasi otomatis ke sistem lain saat ada kejadian: pesan masuk, status pesan, opt-out, campaign selesai, tiket.',
        'langkah' => [
            '+ Webhook → isi nama, URL tujuan, dan pilih event.',
            'Simpan signing secret yang muncul (sekali) untuk verifikasi tanda tangan di sistem Anda.',
            'Klik Tes untuk mencoba. Lihat hasil di "Pengiriman terakhir".',
        ],
        'tips' => [
            'Contoh verifikasi tanda tangan ada di Panduan → Webhook.',
        ],
        'doc' => 'webhook',
    ],

    'users.*' => [
        'judul' => 'Pengguna & Role',
        'fungsi' => 'Mengelola akun tim di akun usaha Anda (owner, admin, CS) dan hak aksesnya.',
        'langkah' => [
            '+ Pengguna → isi nama, email, password, dan role.',
            'Owner/Admin: semua menu. Agent (CS): Inbox, Kontak, Rombongan, Grup WA, Tiket, Kirim, Riwayat.',
            'Hapus centang "Aktif" untuk menonaktifkan akun tanpa menghapusnya.',
        ],
        'tips' => [
            'Pengguna hanya melihat data akun usahanya sendiri.',
            'Email harus unik di seluruh aplikasi (satu email = satu akun).',
        ],
    ],

    'logs.*' => [
        'judul' => 'Log',
        'fungsi' => 'Jejak aktivitas untuk pemeriksaan: Audit (aksi pengguna), API (request dari aplikasi lain), Webhook (pengiriman event).',
        'langkah' => [
            'Pilih tab Audit / API / Webhook.',
            'Gunakan Request ID dari respons API untuk menelusuri masalah.',
        ],
        'tips' => [],
    ],

    'admin.tenants.*' => [
        'judul' => 'Penyewa (panel super-admin)',
        'fungsi' => 'Mengelola semua akun usaha yang menyewa aplikasi: paket, masa sewa, pembayaran, penangguhan, dan bantuan langsung ke akun mereka.',
        'langkah' => [
            '+ Penyewa: isi nama usaha, paket, masa berlaku, nomor WhatsApp kontak, serta email & password login pemiliknya. Kirim data login itu ke penyewa.',
            'Detail penyewa → "Catat pembayaran & perpanjang": pilih paket, tambah hari, isi nominal. Masa sewa otomatis diperpanjang.',
            '"Masuk ke akun ini" untuk melihat aplikasi persis seperti penyewa (membantu pengaturan / cek masalah). Klik "Kembali ke panel penyewa" untuk keluar.',
            'Tangguhkan: penyewa tidak bisa login, API ditolak, dan pengiriman berhenti — data tetap aman. Aktifkan lagi kapan saja.',
            'Hapus (hanya untuk penyewa yang sudah ditangguhkan) menghapus permanen semua datanya.',
        ],
        'tips' => [
            'Pengingat perpanjangan otomatis dikirim ke WhatsApp kontak penyewa H-7, H-3, H-1, dan hari H dari nomor utama Anda (perlu scheduler berjalan).',
            'Masa sewa lewat + 3 hari tenggang → penyewa otomatis turun ke paket Gratis (watermark, 1 nomor, 100 pesan/hari).',
            'Filter "Segera habis" untuk melihat penyewa yang perlu ditagih minggu ini.',
            'Pendaftaran mandiri (paket Gratis) bisa diaktifkan dengan SIGNUP_ENABLED=true di .env.',
        ],
    ],

    'license.*' => [
        'judul' => 'Lisensi / Paket',
        'fungsi' => 'Menampilkan paket akun usaha Anda (Gratis / Pro), masa berlaku, pemakaian kuota, dan perbandingan paket.',
        'langkah' => [
            'Lihat paket aktif, masa berlaku, jumlah nomor, dan pesan keluar hari ini.',
            'Untuk upgrade / perpanjang: hubungi penyedia aplikasi. Paket diperbarui oleh penyedia; bila Anda menerima kode lisensi, tempel di sini → Aktifkan.',
            'Tabel Perbandingan paket menunjukkan fitur tiap paket.',
        ],
        'tips' => [
            'Paket Gratis: 1 nomor, 100 pesan/hari, tanpa broadcast & AI, pesan diberi watermark.',
            'Masa sewa yang lewat masih berlaku 3 hari (masa tenggang) sebelum turun ke Gratis. Pengingat dikirim ke WhatsApp Anda H-7, H-3, H-1, dan hari H.',
        ],
    ],

    'settings.*' => [
        'judul' => 'Pengaturan',
        'fungsi' => 'Aturan akun usaha Anda: pengaman broadcast & anti-blokir, kata kunci STOP/MULAI, penugasan otomatis, SLA tiket, dan jam kerja.',
        'langkah' => [
            'Jam tenang: broadcast tidak dikirim pada rentang jam ini (bawaan 21:00–08:00) dan otomatis lanjut setelahnya. Hilangkan centang untuk mematikan.',
            '"Broadcast hanya ke kontak opt-in": disarankan tetap dicentang. Bila dimatikan, kontak "belum ada izin" ikut dikirimi (opt-out tetap tidak).',
            'Frequency cap: jarak minimal antar broadcast ke kontak yang sama (0 = nonaktif). Pesan yang gagal tidak dihitung.',
            'Auto-pause: campaign dijeda otomatis bila tingkat gagal / opt-out melewati batas.',
            'Anti-blokir (Scan QR): jeda acak minimum–maksimum antar pesan, "sedang mengetik…", batas broadcast per nomor per hari, dan pemanasan nomor baru.',
            'SLA tiket: batas waktu penanganan per prioritas. Jam kerja: dipakai Auto-Reply "di luar jam kerja" dan mode AI.',
            'Klik Simpan pengaturan — campaign yang sedang menunggu ikut memakai pengaturan baru dalam ±1 menit.',
        ],
        'tips' => [
            'Untuk uji coba malam hari, matikan sementara jam tenang dan/atau ubah Frequency cap ke 0 — kembalikan setelah selesai.',
            'Pemanasan nomor baru: hari 1–3: 30, 4–7: 60, 8–14: 120, 15–21: 200 broadcast/hari.',
            'Pengaturan berlaku per akun usaha (tidak memengaruhi penyewa lain).',
        ],
    ],

    'notifications.*' => [
        'judul' => 'Notifikasi',
        'fungsi' => 'Pemberitahuan di dalam aplikasi: pesan baru, penugasan, tiket lewat SLA, campaign selesai, perangkat terputus, AI menyerahkan ke CS.',
        'langkah' => [
            'Klik notifikasi untuk membuka halaman terkait.',
            'Tandai semua dibaca untuk membersihkan angka di ikon lonceng.',
        ],
        'tips' => [],
    ],

    'account.*' => [
        'judul' => 'Akun',
        'fungsi' => 'Mengubah nama, email, dan password akun Anda.',
        'langkah' => [
            'Ubah nama / email lalu Simpan.',
            'Untuk ganti password: isi password lama, password baru, dan ulangi password baru.',
        ],
        'tips' => [
            'Segera ganti password bawaan setelah instalasi.',
        ],
    ],

    'docs.*' => [
        'judul' => 'Panduan',
        'fungsi' => 'Dokumentasi lengkap: cara pemasangan, fitur, REST API, webhook, dan engine Scan QR.',
        'langkah' => [
            'Pilih dokumen di sebelah kiri (atau di atas pada ponsel).',
            'Klik daftar isi untuk melompat ke bagian tertentu.',
            'Tombol Salin pada kotak perintah untuk menyalin perintah PowerShell.',
            'Cetak / PDF untuk menyimpan panduan.',
        ],
        'tips' => [
            '"Cara pemasangan" bagian 10 berisi pemecahan masalah umum (engine, token, fetch failed, database).',
            'Tombol ? di setiap halaman berisi panduan singkat khusus halaman itu.',
        ],
    ],

    '*' => [
        'judul' => 'Bantuan',
        'fungsi' => 'Halaman ini bagian dari ALMARA Gateway WA.',
        'langkah' => [
            'Buka menu Panduan untuk dokumentasi lengkap.',
        ],
        'tips' => [],
    ],
];
