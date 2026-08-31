# Task.md — FinTrack AI

### Daftar Tugas Implementasi (turunan dari PRD.md v1.1 & StyleGuide.md v1.0)

**Cara pakai:** file ini jadi pegangan kerja untuk AI/dev assistant (mis. Claude Code). Kerjakan urut per fase, centang tiap task yang selesai. Setiap task ditulis sekecil mungkin agar bisa diselesaikan dan diverifikasi satu-persatu, bukan sekaligus besar.

---

## Fase 0 — Setup Proyek

### Backend (Laravel)
- [x] Init project Laravel baru (`composer create-project laravel/laravel backend`), pastikan versi PHP sesuai yang tersedia di shared hosting (≥ 8.2)
- [x] Setup koneksi database MySQL/MariaDB di `.env`, test koneksi
- [x] Setup Laravel Sanctum untuk auth API
- [x] Setup CORS (`config/cors.php`) agar bisa diakses dari domain/subdomain frontend
- [x] Setup struktur folder API (`routes/api.php`), buat health-check endpoint (`GET /api/ping`)
- [x] Siapkan `.env.example` dengan semua variabel yang dibutuhkan (DB, Telegram bot token, Claude API key) — **jangan pernah commit `.env` asli**

### Frontend (React)
- [x] Init project Vite + React + TypeScript (`npm create vite@latest frontend -- --template react-ts`)
- [x] Setup Tailwind CSS, masukkan token warna & font dari StyleGuide.md ke `tailwind.config.ts` (warna, font-family, spacing scale) — pakai Tailwind v4 CSS-first `@theme` di `src/index.css` (setara fungsinya dengan `tailwind.config.ts`)
- [x] Import font: Fraunces, Inter, IBM Plex Mono (via self-host atau Google Fonts)
- [x] Setup struktur folder (`components/`, `pages/`, `lib/`, `hooks/`, `types/`)
- [x] Setup axios/fetch wrapper untuk komunikasi ke API Laravel + interceptor token Sanctum
- [x] Setup routing dasar (React Router): halaman Login, Dashboard, Transaksi, Advisory, Pengaturan

### Infrastruktur
- [ ] Cek akses terminal shared hosting: pastikan bisa jalankan `composer`, `php artisan`, `npm run build`
- [ ] Tentukan struktur domain: `api.domain.com` → Laravel `public/`, domain utama/subdomain lain → build statis React
- [ ] Pastikan SSL aktif di domain yang dipakai untuk webhook Telegram (wajib HTTPS)

---

## Fase 1 — MVP

### 1.1 Database & Model
- [x] Migration `users` (tambahan kolom: `telegram_chat_id`, `reminder_time`)
- [x] Migration `categories` (`user_id`, `name`, `type` income/expense, `is_default`, `color_key`)
- [x] Migration `transactions` (`user_id`, `category_id`, `amount`, `type`, `description`, `raw_input_text`, `source`, `ai_comment`, `transaction_date`)
- [x] Migration `reminder_logs` (`user_id`, `date`, `sent_at`, `was_needed`)
- [x] Seeder kategori default (Makanan, Transportasi, Belanja, Hiburan, Tagihan, Kesehatan, Gaji, Lainnya) sesuai palet kategori di StyleGuide 5.3
- [x] Model Eloquent + relasi (`User hasMany Transaction`, `Transaction belongsTo Category`, dst.)

### 1.2 Integrasi AI — Parsing Transaksi
- [x] Buat service class `TransactionParserService` yang memanggil Claude API dengan tool-calling (schema: `amount`, `type`, `category`, `description`)
- [x] Susun system prompt parser: instruksi format nominal Indonesia (`rb`, `k`, `jt`), instruksi "jangan menebak jika ambigu — minta klarifikasi"
- [x] Tulis unit test dengan berbagai contoh input (`"makan malam 30rb"`, `"gaji freelance 500k"`, `"beli baju 150.000"`, input ambigu) untuk verifikasi akurasi parsing sebelum lanjut ke integrasi bot
- [x] Buat endpoint internal `POST /api/ai/parse-transaction` (dipakai webhook Telegram maupun input manual dari web)

### 1.3 Telegram Bot
- [ ] Daftarkan bot lewat BotFather, simpan token di `.env` — **belum dilakukan, butuh token asli dari user** (kode sudah siap pakai lewat `php artisan telegram:set-webhook` begitu `TELEGRAM_BOT_TOKEN` diisi)
- [x] Buat route webhook `POST /api/telegram/webhook`, daftarkan URL webhook ke Telegram (command `telegram:set-webhook` disiapkan, tinggal jalan setelah token diisi)
- [x] Handler: terima pesan → panggil `TransactionParserService` → simpan ke `transactions` jika valid
- [x] Balasan konfirmasi ke user (format sesuai contoh di PRD lampiran 16, angka pakai pemisah ribuan Indonesia)
- [x] Generate komentar AI singkat setelah transaksi tersimpan (mengacu StyleGuide 7 — 1 kalimat, proporsional, tidak menghakimi)
- [x] Handler untuk input ambigu → bot balas pertanyaan klarifikasi, simpan state sementara (mis. cache/session) sampai user jawab
- [x] Command `/undo` — batalkan transaksi terakhir milik user tsb
- [x] Command `/start` — hubungkan `telegram_chat_id` ke akun user (verifikasi kepemilikan akun, lewat kode koneksi sekali pakai dari `POST /api/telegram/connect-code`)
- [x] Handle multi-transaksi dalam satu pesan (pesan multi-baris)

### 1.4 REST API untuk Dashboard
- [x] `GET /api/transactions` (filter: tanggal, kategori, tipe; pagination)
- [x] `POST /api/transactions` (input manual dari web)
- [x] `PUT /api/transactions/{id}` (edit)
- [x] `DELETE /api/transactions/{id}` (hapus)
- [x] `GET /api/summary?month=YYYY-MM` (total pemasukan, pengeluaran, saldo, breakdown per kategori)
- [x] `GET /api/categories`
- [x] Endpoint auth: `POST /api/login`, `POST /api/logout` (Sanctum)

### 1.5 Web Dashboard — Halaman
- [x] Halaman Login (single-user, sesuai StyleGuide 5.1 untuk styling tombol)
- [x] Halaman Dashboard: 3 kartu ringkasan (Saldo, Pemasukan, Pengeluaran bulan berjalan) — grid sesuai StyleGuide 4
- [x] Grafik tren bulanan (line chart, Recharts) — warna sesuai StyleGuide 5.5
- [x] Grafik breakdown kategori (pie chart) — warna sesuai palet kategori StyleGuide 5.3
- [x] Tabel riwayat transaksi bergaya ledger (garis tipis, nominal mono rata kanan) sesuai StyleGuide 4
- [x] Form tambah/edit transaksi manual (modal)
- [x] Implementasi badge "cap" (StyleGuide 5.2) untuk transaksi yang baru masuk secara live (polling 15 detik di Dashboard, pendekatan paling ringan untuk shared hosting)
- [x] Empty state & error message sesuai copy guideline StyleGuide 7

Diuji langsung di browser (login, dashboard, tambah/edit/hapus transaksi, filter, halaman Pengaturan generate kode koneksi) — ditemukan & diperbaiki bug: form tambah transaksi default `category_id` ke kategori pertama tanpa filter tipe (income/expense), berisiko salah simpan kategori kalau user tidak menyentuh dropdown. Diperbaiki di frontend (reset kategori saat tipe berganti) dan diperkuat validasi backend (`category_id` harus match `type` & `user_id`).

### 1.6 Reminder Harian
- [x] Buat Artisan command `reminder:check-daily` — cek transaksi hari ini per user, kirim pesan Telegram jika kosong, catat ke `reminder_logs`
- [x] Daftarkan schedule di `routes/console.php` (atau `Kernel.php`) sesuai `reminder_time` user — command jalan tiap menit, internal filter per user berdasarkan `reminder_time` masing-masing
- [ ] Setup 1 baris Cron Job di cPanel: `* * * * * php /home/USER/artisan schedule:run >> /dev/null 2>&1` — **belum dilakukan, butuh akses shared hosting asli** (kode & instruksi sudah siap)
- [x] Test idempotency: pastikan tidak kirim reminder dobel walau cron/schedule run berkali-kali dalam rentang waktu yang sama

### 1.7 QA Fase 1
- [x] Test end-to-end: kirim pesan Telegram → cek muncul di DB → cek muncul di dashboard — dicover otomatis lewat `TelegramWebhookTest` (webhook → parse → simpan) + verifikasi manual di browser (data tampil benar di Dashboard & tabel Transaksi)
- [x] Test reminder: kosongkan transaksi hari ini, pastikan reminder terkirim sesuai jam yang diatur — `ReminderCheckDailyCommandTest` (5 skenario, termasuk idempotency)
- [x] Test auth: pastikan endpoint API tidak bisa diakses tanpa token — `DashboardApiTest::test_transactions_endpoint_requires_authentication` + ownership check antar-user
- [x] Review manual terhadap StyleGuide (warna, font, spacing) — tidak ada elemen yang menyimpang dari token yang ditentukan — diverifikasi lewat browser testing (login, dashboard, CRUD transaksi, filter, cap badge, halaman Pengaturan) dan grep hex color di source: hanya `index.css` (definisi token), `categoryColors.ts`, dan `TrendLineChart.tsx` (warna literal untuk Recharts SVG props) yang berisi hex, semuanya persis nilai StyleGuide §2 & §5.3/5.5

**Status Fase 1 (MVP): kode lengkap, 22/22 test backend lulus, frontend build bersih.**
Yang masih butuh tindakan dari kamu (di luar jangkauan environment ini):
1. Daftarkan bot ke @BotFather, isi `TELEGRAM_BOT_TOKEN` di `.env`, lalu jalankan `php artisan telegram:set-webhook`.
2. Isi `ANTHROPIC_API_KEY` di `.env` supaya parsing transaksi & komentar AI benar-benar memanggil Claude (saat ini kode sudah lengkap dan teruji lewat mock, tinggal pasang kunci asli).
3. Saat deploy ke shared hosting: pasang 1 baris cron cPanel (`* * * * * php /home/USER/artisan schedule:run >> /dev/null 2>&1`) untuk reminder harian.
4. Bug yang ditemukan & diperbaiki selama QA: form tambah transaksi di web sempat bisa salah simpan kategori kalau user tidak menyentuh dropdown kategori — sudah diperbaiki di frontend & diperkuat validasi di backend (lihat commit "feat(frontend): build dashboard, transactions, settings pages...").

---

## Fase 2

- [x] Endpoint advisory: `POST /api/advisory/ask` — ambil ringkasan data user sebagai konteks, kirim ke Claude API (percakapan bebas, bukan tool-calling). Riwayat percakapan disimpan per sesi (idle 30 menit) di `advisory_sessions` untuk memori kontekstual jangka pendek (FR-2.4)
- [x] Command Telegram `/tanya <pertanyaan>` — panggil endpoint advisory yang sama
- [x] Panel chat advisory di web (styling sesuai StyleGuide 5.4 — bubble AI vs user, angka tetap mono inline). Diuji di browser (bubble user hijau, empty state, error state saat `ANTHROPIC_API_KEY` belum diisi menampilkan pesan error yang jelas sesuai StyleGuide §7)
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
