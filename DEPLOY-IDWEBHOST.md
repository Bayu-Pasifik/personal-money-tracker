# Deploy ke IDWebHost via Terminal (cPanel)

Panduan ini spesifik untuk hosting **IDWebHost** yang pakai cPanel dan sudah menyediakan fitur **Terminal**. Alur intinya: `git clone` repo langsung dari GitHub ke server (bukan upload file manual), lalu setup lewat command line.

Sebelum mulai, baca dulu `SETUP.md` untuk daftar lengkap kredensial yang perlu disiapkan (Gemini API key, token bot Telegram, dll).

---

## 0. Prasyarat di sisi IDWebHost

- Paket hosting yang **support PHP ≥ 8.2** dan fitur **Terminal** aktif (biasanya paket Business ke atas — cek di menu cPanel, cari ikon "Terminal"). Kalau tidak ada, hubungi support IDWebHost untuk mengaktifkan.
- Akses ke **cPanel** (username/password dari email aktivasi hosting).
- Domain/subdomain sudah diarahkan (DNS) ke hosting ini dan **SSL aktif** (AutoSSL cPanel biasanya otomatis, cek di menu "SSL/TLS Status").

---

## 1. Buat Subdomain untuk API

Repo ini punya 2 bagian: `backend` (Laravel, butuh PHP) dan `frontend` (React, hasil build-nya statis). Keduanya dipisah domain/subdomain.

1. cPanel → menu **Domains** atau **Subdomains** → buat subdomain, misal `api.domainkamu.com`.
2. Document root biarkan default dulu (nanti diarahkan ulang ke `backend/public` di langkah 4).
3. Domain utama (`domainkamu.com`) dipakai untuk frontend — tidak perlu subdomain baru kalau memang mau taruh di root.

---

## 2. Buat Database MySQL

cPanel → **MySQL® Databases**:

1. Buat database baru, misal `namauser_fintrack`. (cPanel biasanya prefix otomatis dengan username hosting.)
2. Buat user database baru + password kuat.
3. Di bagian "Add User to Database", tambahkan user itu ke database, centang **All Privileges**.
4. Catat: nama database, username, password — dipakai di `.env` nanti.

---

## 3. Buka Terminal & Clone Repo

cPanel → cari ikon **Terminal** (biasanya di bagian "Advanced").

```bash
# cek kamu masuk ke home directory hosting
pwd
# biasanya: /home/namauser

# clone repo dari GitHub
git clone https://github.com/Bayu-Pasifik/personal-money-tracker.git
cd personal-money-tracker
```

Kalau repo private nanti, `git clone` akan minta username/token GitHub (bukan password — GitHub sudah tidak terima password biasa, pakai [personal access token](https://github.com/settings/tokens)).

---

## 4. Setup Backend (Laravel)

```bash
cd ~/personal-money-tracker/backend

# cek composer tersedia
composer -V
```

Kalau `composer: command not found`, download manual:

```bash
curl -sS https://getcomposer.org/installer | php
# pakai sebagai: php composer.phar install ...
```

Install dependency (pakai `composer` atau `php composer.phar` sesuai hasil cek di atas):

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

### 4.1 Edit `.env`

Pakai editor terminal (`nano` paling gampang):

```bash
nano .env
```

Isi/sesuaikan baris berikut (`Ctrl+O` lalu Enter untuk save, `Ctrl+X` untuk keluar):

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.domainkamu.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=namauser_fintrack
DB_USERNAME=namauser_dbuser
DB_PASSWORD=password-database-kamu

TELEGRAM_BOT_TOKEN=token-dari-botfather
TELEGRAM_WEBHOOK_SECRET=string-acak-bebas

GEMINI_API_KEY=key-dari-aistudio.google.com
GEMINI_PARSER_MODEL=gemini-2.0-flash
GEMINI_ADVISORY_MODEL=gemini-2.0-flash

FRONTEND_URL=https://domainkamu.com
```

### 4.2 Migrasi database

```bash
php artisan migrate --force
```

### 4.3 Buat user asli (bukan user contoh)

```bash
php artisan tinker --execute="
App\Models\User::create([
    'name' => 'Nama Kamu',
    'email' => 'email-kamu@example.com',
    'password' => bcrypt('password-yang-kuat'),
]);
"
```

### 4.4 Arahkan document root subdomain ke `backend/public`

Balik ke cPanel (bukan Terminal) → **Domains**/**Subdomains** → edit `api.domainkamu.com` → ubah **Document Root** jadi:

```
personal-money-tracker/backend/public
```

Atau lewat Terminal, buat symlink kalau cPanel tidak izinkan edit document root langsung:

```bash
# contoh, sesuaikan path public_html subdomain kamu
rm -rf ~/api.domainkamu.com
ln -s ~/personal-money-tracker/backend/public ~/api.domainkamu.com
```

### 4.5 Set permission storage

```bash
cd ~/personal-money-tracker/backend
chmod -R 775 storage bootstrap/cache
```

Test: buka `https://api.domainkamu.com/api/ping` di browser, harus muncul `{"status":"ok",...}`.

---

## 5. Setup Bot Telegram

Masih di Terminal, folder `backend`:

```bash
php artisan telegram:set-webhook
```

Harus muncul `Webhook terdaftar: https://api.domainkamu.com/api/telegram/webhook`. Kalau gagal, cek lagi `TELEGRAM_BOT_TOKEN` dan pastikan SSL subdomain API sudah aktif.

---

## 6. Setup Cron Job (Reminder Harian)

Dua cara, pilih salah satu:

**A. Lewat cPanel (lebih aman, direkomendasikan)**
cPanel → **Cron Jobs** → tambah cron baru, "Common Settings" pilih **Once Per Minute**, command:

```bash
php /home/namauser/personal-money-tracker/backend/artisan schedule:run >> /dev/null 2>&1
```

**B. Lewat Terminal**

```bash
crontab -e
```

Tambah baris (format sama seperti di atas), save, keluar.

---

## 7. Setup Frontend (React)

Cek dulu apakah Node.js tersedia di Terminal:

```bash
node -v
npm -v
```

### 7a. Kalau Node.js tersedia di server
Build langsung di Terminal:

```bash
cd ~/personal-money-tracker/frontend
npm install
```

Buat `.env.local` isi URL API production:

```bash
nano .env.local
```

```env
VITE_API_URL=https://api.domainkamu.com/api
```

Build:

```bash
npm run build
```

Arahkan document root domain utama (`domainkamu.com`) ke folder `frontend/dist` — sama seperti langkah 4.4, lewat cPanel **Domains** atau symlink:

```bash
rm -rf ~/public_html
ln -s ~/personal-money-tracker/frontend/dist ~/public_html
```

### 7b. Kalau Node.js TIDAK tersedia di server
Build di komputer kamu sendiri (lokal), lalu upload folder `dist/` lewat Terminal pakai `scp`/`rsync` dari komputer lokal, **atau** paling gampang: commit folder `dist/` ke branch terpisah di GitHub, lalu di Terminal server tinggal:

```bash
cd ~/personal-money-tracker/frontend
git fetch origin
git checkout <branch-dist>
```

Lalu arahkan document root domain utama ke folder itu seperti 7a.

---

## 8. Verifikasi Akhir

- `https://api.domainkamu.com/api/ping` → `{"status":"ok"}`
- `https://domainkamu.com` → halaman login FinTrack AI muncul
- Login pakai user yang dibuat di langkah 4.3
- Halaman **Pengaturan** → **Buat Kode Koneksi** → kirim `/start KODE` ke bot Telegram → dapat balasan konfirmasi

---

## 9. Update Aplikasi di Kemudian Hari

Kalau ada perubahan kode baru di GitHub, tinggal masuk Terminal lagi:

```bash
cd ~/personal-money-tracker
git pull origin main

cd backend
composer install --no-dev --optimize-autoloader
php artisan migrate --force

cd ../frontend
npm install && npm run build   # kalau Node.js tersedia di server
```

Tidak perlu ulang setup subdomain/cron/webhook — itu semua tetap dipakai selama path folder tidak berubah.
