# Task.md — FinTrack AI

### Daftar Tugas Implementasi (turunan dari PRD.md v1.1 & StyleGuide.md v1.0)

**Cara pakai:** file ini jadi pegangan kerja untuk AI/dev assistant (mis. Claude Code). Kerjakan urut per fase, centang tiap task yang selesai. Setiap task ditulis sekecil mungkin agar bisa diselesaikan dan diverifikasi satu-persatu, bukan sekaligus besar.

---

## Fase 0 — Setup Proyek

### Backend (Laravel)
- [ ] Init project Laravel baru (`composer create-project laravel/laravel backend`), pastikan versi PHP sesuai yang tersedia di shared hosting (≥ 8.2)
- [ ] Setup koneksi database MySQL/MariaDB di `.env`, test koneksi
- [ ] Setup Laravel Sanctum untuk auth API
- [ ] Setup CORS (`config/cors.php`) agar bisa diakses dari domain/subdomain frontend
- [ ] Setup struktur folder API (`routes/api.php`), buat health-check endpoint (`GET /api/ping`)
- [ ] Siapkan `.env.example` dengan semua variabel yang dibutuhkan (DB, Telegram bot token, Claude API key) — **jangan pernah commit `.env` asli**

### Frontend (React)
- [ ] Init project Vite + React + TypeScript (`npm create vite@latest frontend -- --template react-ts`)
- [ ] Setup Tailwind CSS, masukkan token warna & font dari StyleGuide.md ke `tailwind.config.ts` (warna, font-family, spacing scale)
- [ ] Import font: Fraunces, Inter, IBM Plex Mono (via self-host atau Google Fonts)
- [ ] Setup struktur folder (`components/`, `pages/`, `lib/`, `hooks/`, `types/`)
- [ ] Setup axios/fetch wrapper untuk komunikasi ke API Laravel + interceptor token Sanctum
- [ ] Setup routing dasar (React Router): halaman Login, Dashboard, Transaksi, Advisory, Pengaturan

### Infrastruktur
- [ ] Cek akses terminal shared hosting: pastikan bisa jalankan `composer`, `php artisan`, `npm run build`
- [ ] Tentukan struktur domain: `api.domain.com` → Laravel `public/`, domain utama/subdomain lain → build statis React
- [ ] Pastikan SSL aktif di domain yang dipakai untuk webhook Telegram (wajib HTTPS)

---

## Fase 1 — MVP

### 1.1 Database & Model
- [ ] Migration `users` (tambahan kolom: `telegram_chat_id`, `reminder_time`)
- [ ] Migration `categories` (`user_id`, `name`, `type` income/expense, `is_default`, `color_key`)
- [ ] Migration `transactions` (`user_id`, `category_id`, `amount`, `type`, `description`, `raw_input_text`, `source`, `ai_comment`, `transaction_date`)
- [ ] Migration `reminder_logs` (`user_id`, `date`, `sent_at`, `was_needed`)
- [ ] Seeder kategori default (Makanan, Transportasi, Belanja, Hiburan, Tagihan, Kesehatan, Gaji, Lainnya) sesuai palet kategori di StyleGuide 5.3
- [ ] Model Eloquent + relasi (`User hasMany Transaction`, `Transaction belongsTo Category`, dst.)

### 1.2 Integrasi AI — Parsing Transaksi
- [ ] Buat service class `TransactionParserService` yang memanggil Claude API dengan tool-calling (schema: `amount`, `type`, `category`, `description`)
- [ ] Susun system prompt parser: instruksi format nominal Indonesia (`rb`, `k`, `jt`), instruksi "jangan menebak jika ambigu — minta klarifikasi"
- [ ] Tulis unit test dengan berbagai contoh input (`"makan malam 30rb"`, `"gaji freelance 500k"`, `"beli baju 150.000"`, input ambigu) untuk verifikasi akurasi parsing sebelum lanjut ke integrasi bot
- [ ] Buat endpoint internal `POST /api/ai/parse-transaction` (dipakai webhook Telegram maupun input manual dari web)

### 1.3 Telegram Bot
- [ ] Daftarkan bot lewat BotFather, simpan token di `.env`
- [ ] Buat route webhook `POST /api/telegram/webhook`, daftarkan URL webhook ke Telegram
- [ ] Handler: terima pesan → panggil `TransactionParserService` → simpan ke `transactions` jika valid
- [ ] Balasan konfirmasi ke user (format sesuai contoh di PRD lampiran 16, angka pakai pemisah ribuan Indonesia)
- [ ] Generate komentar AI singkat setelah transaksi tersimpan (mengacu StyleGuide 7 — 1 kalimat, proporsional, tidak menghakimi)
- [ ] Handler untuk input ambigu → bot balas pertanyaan klarifikasi, simpan state sementara (mis. cache/session) sampai user jawab
- [ ] Command `/undo` — batalkan transaksi terakhir milik user tsb
- [ ] Command `/start` — hubungkan `telegram_chat_id` ke akun user (verifikasi kepemilikan akun)
- [ ] Handle multi-transaksi dalam satu pesan (pesan multi-baris)

### 1.4 REST API untuk Dashboard
- [ ] `GET /api/transactions` (filter: tanggal, kategori, tipe; pagination)
- [ ] `POST /api/transactions` (input manual dari web)
- [ ] `PUT /api/transactions/{id}` (edit)
- [ ] `DELETE /api/transactions/{id}` (hapus)
- [ ] `GET /api/summary?month=YYYY-MM` (total pemasukan, pengeluaran, saldo, breakdown per kategori)
- [ ] `GET /api/categories`
- [ ] Endpoint auth: `POST /api/login`, `POST /api/logout` (Sanctum)

### 1.5 Web Dashboard — Halaman
- [ ] Halaman Login (single-user, sesuai StyleGuide 5.1 untuk styling tombol)
- [ ] Halaman Dashboard: 3 kartu ringkasan (Saldo, Pemasukan, Pengeluaran bulan berjalan) — grid sesuai StyleGuide 4
- [ ] Grafik tren bulanan (line chart, Recharts) — warna sesuai StyleGuide 5.5
- [ ] Grafik breakdown kategori (pie chart) — warna sesuai palet kategori StyleGuide 5.3
- [ ] Tabel riwayat transaksi bergaya ledger (garis tipis, nominal mono rata kanan) sesuai StyleGuide 4
- [ ] Form tambah/edit transaksi manual (modal atau halaman terpisah)
- [ ] Implementasi badge "cap" (StyleGuide 5.2) untuk transaksi yang baru masuk secara live (polling atau websocket sederhana — tentukan pendekatan realtime yang paling ringan untuk shared hosting, mis. polling interval singkat)
- [ ] Empty state & error message sesuai copy guideline StyleGuide 7

### 1.6 Reminder Harian
- [ ] Buat Artisan command `reminder:check-daily` — cek transaksi hari ini per user, kirim pesan Telegram jika kosong, catat ke `reminder_logs`
- [ ] Daftarkan schedule di `routes/console.php` (atau `Kernel.php`) sesuai `reminder_time` user
- [ ] Setup 1 baris Cron Job di cPanel: `* * * * * php /home/USER/artisan schedule:run >> /dev/null 2>&1`
- [ ] Test idempotency: pastikan tidak kirim reminder dobel walau cron/schedule run berkali-kali dalam rentang waktu yang sama

### 1.7 QA Fase 1
- [ ] Test end-to-end: kirim pesan Telegram → cek muncul di DB → cek muncul di dashboard
- [ ] Test reminder: kosongkan transaksi hari ini, pastikan reminder terkirim sesuai jam yang diatur
- [ ] Test auth: pastikan endpoint API tidak bisa diakses tanpa token
- [ ] Review manual terhadap StyleGuide (warna, font, spacing) — tidak ada elemen yang menyimpang dari token yang ditentukan

---

## Fase 2

- [ ] Endpoint & UI advisory: `POST /api/advisory/ask` — ambil ringkasan data user sebagai konteks, kirim ke Claude API (percakapan bebas, bukan tool-calling)
- [ ] Command Telegram `/tanya <pertanyaan>` — panggil endpoint advisory yang sama
- [ ] Panel chat advisory di web (styling sesuai StyleGuide 5.4 — bubble AI vs user, angka tetap mono inline)
- [ ] Simpan riwayat sesi advisory (`advisory_sessions`) untuk konteks jangka pendek dalam sesi yang sama
- [ ] CRUD kategori custom dari halaman Pengaturan web
- [ ] Koreksi transaksi via chat (selain `/undo`, mis. "ganti kategori transaksi terakhir jadi Hiburan")

---

## Fase 3

- [ ] Migration & model `budgets` (`user_id`, `category_id`, `month`, `limit_amount`)
- [ ] UI setting budget per kategori di halaman Pengaturan
- [ ] Logic notifikasi early-warning saat pengeluaran kategori mendekati/melewati limit (trigger dari job yang jalan setiap transaksi baru tersimpan)
- [ ] Export laporan bulanan (PDF dan/atau Excel) — sesuaikan library yang ringan untuk shared hosting (mis. `barryvdh/laravel-dompdf`)
- [ ] Dukungan multi-wallet (`wallets` table, relasi ke `transactions`)

---

## Fase 4 (opsional)

- [ ] Desain ulang auth untuk mendukung multi-user (registrasi mandiri)
- [ ] Isolasi data antar user diverifikasi ulang di semua endpoint (policy/authorization Laravel)
- [ ] Onboarding flow untuk user baru (hubungkan Telegram, atur kategori awal)

---

## Catatan untuk AI/Dev Assistant yang Mengerjakan

- Rujuk **StyleGuide.md** untuk semua keputusan visual — jangan improvisasi warna/font di luar token yang sudah ditentukan.
- Rujuk **PRD.md** untuk detail behavior (format nominal, alur klarifikasi AI, dsb.) sebelum mengambil keputusan desain sistem sendiri.
- Kerjakan checklist urut per fase; jangan lompat ke Fase 2/3 sebelum Fase 1 lulus QA (7).
- Setiap kali task selesai, update checklist ini (`[ ]` → `[x]`) supaya jadi source of truth progres proyek.
