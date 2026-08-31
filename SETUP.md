# Setup & Menjalankan FinTrack AI

Panduan ini menjelaskan semua yang perlu disiapkan untuk menjalankan proyek ini, baik untuk pengembangan lokal maupun deploy ke shared hosting. Struktur proyek: `backend/` (Laravel API) dan `frontend/` (React SPA).

---

## 1. Yang Harus Disiapkan Sebelum Mulai

### 1.1 Software wajib
| Software | Versi minimum | Cek dengan |
|---|---|---|
| PHP | 8.2 | `php -v` |
| Composer | 2.x | `composer -V` |
| Node.js | 18+ (dipakai 22 saat dev) | `node -v` |
| npm | 9+ | `npm -v` |
| MySQL / MariaDB | 8.x / 10.x | `mysql --version` |

### 1.2 Akun/kredensial yang harus kamu siapkan sendiri
Tidak bisa dijalankan tanpa dua hal ini:

1. **Anthropic API key** (Claude) — untuk parsing transaksi, komentar AI, dan advisory.
   - Daftar/ambil key di [console.anthropic.com](https://console.anthropic.com).
   - Butuh saldo/billing aktif di akun Anthropic.

2. **Telegram Bot Token** — untuk bot Telegram.
   - Chat ke [@BotFather](https://t.me/BotFather) di Telegram, kirim `/newbot`, ikuti instruksinya.
   - Simpan token yang diberikan (format `123456789:ABCdefGhIJKlmNoPQRstuVWXyz`).
   - Domain publik dengan **HTTPS aktif** wajib ada sebelum registrasi webhook (Telegram menolak URL non-HTTPS). Untuk dev lokal, pakai tunnel (lihat §3.3).

---

## 2. Setup Backend (Laravel)

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

### 2.1 Isi `.env`
Buka `backend/.env`, isi bagian berikut (nilai lain sudah ada default yang masuk akal):

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=fintrack_ai
DB_USERNAME=root
DB_PASSWORD=

TELEGRAM_BOT_TOKEN=          # token dari BotFather
TELEGRAM_WEBHOOK_SECRET=     # string acak bebas, buat sendiri (mis. openssl rand -hex 20)

ANTHROPIC_API_KEY=           # dari console.anthropic.com
ANTHROPIC_PARSER_MODEL=claude-haiku-4-5-20251001
ANTHROPIC_ADVISORY_MODEL=claude-sonnet-5

FRONTEND_URL=http://localhost:5173   # ganti ke domain frontend asli saat deploy
```

### 2.2 Buat database & migrasi

```bash
# buat database kosong dulu (lewat phpMyAdmin, Laragon, atau CLI):
mysql -u root -e "CREATE DATABASE fintrack_ai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

php artisan migrate --seed
```

`--seed` otomatis membuat 1 user contoh (`test@example.com`, cek `database/seeders/DatabaseSeeder.php` untuk password) beserta kategori default. **Untuk pemakaian asli, buat user sendiri** lewat tinker atau hapus seeder user contoh:

```bash
php artisan tinker --execute="
App\Models\User::create([
    'name' => 'Nama Kamu',
    'email' => 'email-kamu@example.com',
    'password' => bcrypt('password-yang-kuat'),
]);
"
```

### 2.3 Jalankan server dev

```bash
php artisan serve
# default: http://127.0.0.1:8000
```

---

## 3. Setup Telegram Bot

### 3.1 Daftarkan webhook
Setelah `TELEGRAM_BOT_TOKEN` terisi dan backend bisa diakses publik via HTTPS:

```bash
php artisan telegram:set-webhook
```

Perintah ini otomatis mendaftarkan `https://domain-kamu.com/api/telegram/webhook` ke Telegram Bot API, memakai `APP_URL` dari `.env` dan `TELEGRAM_WEBHOOK_SECRET`.

### 3.2 Hubungkan akun Telegram ke akun FinTrack AI
1. Login ke web dashboard → halaman **Pengaturan** → klik **Buat Kode Koneksi**.
2. Di Telegram, kirim `/start KODE` ke bot kamu (KODE dari langkah 1, berlaku 10 menit).
3. Setelah itu bot langsung bisa dipakai: ketik transaksi bebas, `/tanya <pertanyaan>`, `/undo`, dll.

### 3.3 Testing lokal (opsional)
Telegram butuh HTTPS publik, jadi untuk testing di localhost pakai tunnel:

```bash
# contoh pakai ngrok
ngrok http 8000
# copy URL https://xxxx.ngrok-free.app ke APP_URL di .env, lalu jalankan ulang:
php artisan telegram:set-webhook
```

---

## 4. Setup Frontend (React)

```bash
cd frontend
npm install
cp .env.example .env.local
```

Isi `frontend/.env.local`:

```env
VITE_API_URL=http://127.0.0.1:8000/api   # sesuaikan ke URL backend kamu
```

Jalankan dev server:

```bash
npm run dev
# default: http://localhost:5173
```

Login pakai akun yang dibuat di langkah 2.2.

---

## 5. Reminder Harian (Scheduler)

Reminder otomatis ("belum ada catatan hari ini") jalan lewat Laravel Scheduler, dicek tiap menit tapi hanya kirim pesan pas jam yang cocok dengan `reminder_time` masing-masing user (default `20:00`, bisa diganti lewat query langsung ke tabel `users` untuk saat ini — belum ada UI-nya).

**Untuk dev lokal**, jalankan scheduler manual di terminal terpisah:

```bash
cd backend
php artisan schedule:work
```

**Untuk production/shared hosting**, pasang cron job cPanel (menu "Cron Jobs"), jalan tiap menit:

```
* * * * * php /home/USERNAME/path/ke/backend/artisan schedule:run >> /dev/null 2>&1
```

---

## 6. Deploy ke Shared Hosting (cPanel)

Sesuai arsitektur di `PRD.md` §8:

1. **Backend Laravel**: upload ke subdomain terpisah, mis. `api.domainkamu.com`, dengan document root diarahkan ke folder `backend/public/`.
2. **Cek versi PHP** di cPanel (menu "MultiPHP Manager" atau serupa) → pastikan PHP ≥ 8.2 untuk domain/subdomain itu.
3. **Composer via SSH/Terminal cPanel**: `composer install --no-dev --optimize-autoloader`.
4. **`.env` di server**: isi sama seperti §2.1, tapi `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://api.domainkamu.com`, `FRONTEND_URL=https://domainkamu.com`.
5. **Migrasi**: `php artisan migrate --force` (via SSH/terminal cPanel).
6. **SSL**: pastikan aktif di subdomain API (wajib untuk webhook Telegram).
7. **Cron job**: pasang baris di §5.
8. **Set webhook**: `php artisan telegram:set-webhook` (via SSH/terminal cPanel).
9. **Frontend React**: `npm run build` di lokal/CI, upload isi folder `frontend/dist/` ke domain utama (mis. `domainkamu.com`) atau subdomain lain, document root langsung ke folder itu (bukan Laravel).
10. **CORS**: `FRONTEND_URL` di `.env` backend harus persis sama dengan domain frontend (termasuk `https://`), supaya browser tidak diblokir CORS.

---

## 7. Checklist Ringkas

- [ ] PHP 8.2+, Composer, Node 18+, MySQL terpasang
- [ ] Anthropic API key aktif dan ada billing
- [ ] Bot Telegram dibuat via BotFather, token disimpan
- [ ] `backend/.env` terisi lengkap (DB, Telegram, Anthropic, Frontend URL)
- [ ] Database dibuat, `php artisan migrate --seed` sukses
- [ ] User asli dibuat (bukan cuma user contoh dari seeder)
- [ ] `frontend/.env.local` terisi, `npm install` sukses
- [ ] Domain HTTPS publik siap (production) atau tunnel (dev) untuk webhook Telegram
- [ ] `php artisan telegram:set-webhook` sukses (`ok:true` dari Telegram)
- [ ] Kode koneksi Telegram dipakai lewat halaman Pengaturan → `/start KODE`
- [ ] Cron job / `schedule:work` jalan untuk reminder harian
