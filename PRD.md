# PRD — FinTrack AI
### Aplikasi Pencatatan & Monitoring Keuangan Personal Berbasis AI (Telegram + Web)

**Versi:** 1.1 (revisi arsitektur untuk shared hosting)
**Tanggal:** 30 Agustus 2026
**Pemilik Produk:** Bayu
**Status:** Draft untuk pengembangan MVP

---

## 1. Ringkasan Produk

FinTrack AI adalah aplikasi pencatatan keuangan personal yang menghilangkan friksi utama dari aplikasi finance tracker pada umumnya: **proses input data**. Alih-alih membuka aplikasi, memilih kategori dari dropdown, dan mengisi form, pengguna cukup mengetik kalimat natural di Telegram (misal `"makan malam 30rb"`), dan AI akan:

1. Mem-parsing teks menjadi data transaksi terstruktur (nominal, kategori, deskripsi, waktu)
2. Menyimpannya ke database
3. Membalas dengan komentar singkat yang relevan (observasi, humor ringan, atau flag jika pengeluaran di luar kebiasaan)

Data yang masuk dapat dipantau lewat **web dashboard (React)** dalam bentuk grafik, ringkasan bulanan, dan riwayat transaksi. Pengguna juga bisa **bertanya langsung ke AI** — baik lewat Telegram maupun chat di web — untuk mendapat saran finansial kontekstual (misal: *"Aku mau upgrade PC, harga sekitar 8 juta, gimana kondisi keuanganku?"*), dengan AI menjawab berdasarkan data riwayat transaksi nyata, bukan template generik. Sistem juga mengirim **reminder otomatis** via Telegram jika pengguna belum mencatat pengeluaran pada hari berjalan.

---

## 2. Latar Belakang & Masalah

Masalah umum aplikasi pencatatan keuangan:

| Masalah | Dampak |
|---|---|
| Input manual lewat form terasa ribet | Pengguna berhenti mencatat setelah beberapa hari |
| Data hanya berupa angka, tanpa insight | Pengguna tidak tahu harus mengubah kebiasaan apa |
| Saran finansial generik ("kurangi jajan") | Tidak actionable karena tidak berbasis kondisi nyata pengguna |
| Tidak ada dorongan konsistensi | Pencatatan bolong-bolong, laporan bulanan jadi tidak akurat |

FinTrack AI menjawab keempat masalah ini dengan memindahkan titik input ke tempat yang sudah jadi kebiasaan sehari-hari (chat Telegram), dan membuat AI sebagai lapisan interpretasi + penasihat, bukan sekadar pencatat pasif.

---

## 3. Tujuan Produk

1. Menurunkan friksi pencatatan transaksi harian mendekati nol (cukup 1 baris chat)
2. Menyediakan visibilitas kondisi keuangan yang jelas lewat dashboard web
3. Memberi saran keuangan yang **grounded** pada data riwayat pengguna, bukan generik
4. Menjaga konsistensi pencatatan lewat reminder proaktif
5. Membangun sistem yang bisa berkembang (multi-akun/multi-wallet, budgeting, dsb.) tanpa perlu re-arsitektur besar

---

## 4. Target Pengguna

- **Primer:** Bayu — pengguna tunggal (single-user) di fase MVP, kebutuhan personal finance tracking harian
- **Sekunder (opsional, fase lanjut):** perluasan ke multi-user jika ingin dibagikan/dipakai orang lain

MVP dirancang **single-tenant** dulu (autentikasi sederhana, satu Telegram chat ID terhubung ke satu akun), dengan skema data yang tetap disiapkan agar mudah di-extend ke multi-user nanti.

---

## 5. User Stories

| # | Sebagai pengguna, saya ingin... | Agar... |
|---|---|---|
| US-1 | mengetik pengeluaran/pemasukan dalam bahasa natural di Telegram | tidak perlu buka aplikasi terpisah |
| US-2 | AI otomatis menentukan kategori transaksi | saya tidak perlu memilih kategori manual |
| US-3 | mendapat komentar singkat dari AI setelah input | ada rasa "diperhatikan", bukan cuma disimpan diam-diam |
| US-4 | melihat grafik pengeluaran per kategori & tren bulanan di web | memahami pola keuangan saya |
| US-5 | bertanya ke AI soal keputusan finansial (mis. upgrade PC) | mendapat saran berbasis kondisi keuangan riil saya |
| US-6 | mendapat notifikasi Telegram kalau saya belum mencatat apa pun hari itu | tetap konsisten mencatat |
| US-7 | mengedit/menghapus transaksi yang salah tercatat dari web | data tetap akurat |
| US-8 | menetapkan budget bulanan per kategori (fase lanjut) | dapat early-warning saat mendekati limit |

---

## 6. Ruang Lingkup Fitur (Functional Requirements)

### 6.1 Telegram Bot — Input & Parsing Transaksi
- **FR-1.1**: Bot menerima pesan teks bebas, mem-parsing ke: `amount`, `type` (income/expense), `category`, `description`, `timestamp`
- **FR-1.2**: Mendukung format nominal gaya Indonesia: `30rb`, `30.000`, `30k`, `1jt`, `1,5jt`, `Rp30.000`
- **FR-1.3**: Auto-kategorisasi ke daftar kategori standar (Makanan, Transportasi, Hiburan, Tagihan, Belanja, Kesehatan, Gaji, Lainnya, dst.) — bisa ditambah custom
- **FR-1.4**: Jika input ambigu (nominal tidak jelas, kalimat tidak mengandung transaksi), bot bertanya balik alih-alih menebak/silently gagal
- **FR-1.5**: Setelah transaksi tersimpan, bot membalas dengan konfirmasi ringkas + komentar kontekstual (mis. total pengeluaran kategori tsb bulan ini, atau observasi jika nominal di luar kebiasaan)
- **FR-1.6**: Mendukung koreksi cepat: `/undo` untuk membatalkan transaksi terakhir
- **FR-1.7**: Mendukung input banyak transaksi sekaligus dalam satu pesan (mis. beberapa baris)

### 6.2 AI Financial Advisory (Chat Konsultasi)
- **FR-2.1**: Pengguna bisa bertanya bebas ke AI (via Telegram command `/tanya` atau chat panel di web) terkait kondisi keuangan
- **FR-2.2**: AI mengambil konteks dari data riwayat transaksi riil (saldo, tren pengeluaran, sisa budget) sebelum menjawab — bukan jawaban template
- **FR-2.3**: Untuk pertanyaan "apakah saya mampu membeli X seharga Y", AI menghitung dampaknya terhadap arus kas/tabungan dan memberi rekomendasi + alasan
- **FR-2.4**: Riwayat percakapan advisory disimpan agar AI punya memori kontekstual jangka pendek dalam sesi yang sama

### 6.3 Web Dashboard (React)
- **FR-3.1**: Ringkasan bulanan: total pemasukan, pengeluaran, saldo/selisih
- **FR-3.2**: Grafik pengeluaran per kategori (pie/bar chart) dan tren harian/mingguan (line chart)
- **FR-3.3**: Tabel riwayat transaksi dengan filter (tanggal, kategori, tipe) dan pencarian
- **FR-3.4**: CRUD manual transaksi (edit/hapus/tambah langsung dari web, untuk kasus di luar chat)
- **FR-3.5**: Panel chat AI advisory terintegrasi di web (opsional, menggunakan AI yang sama dengan Telegram)
- **FR-3.6**: Halaman pengaturan: kategori custom, budget per kategori, koneksi akun Telegram
- **FR-3.7**: Autentikasi login sederhana (single-user di MVP)

### 6.4 Reminder & Notifikasi
- **FR-4.1**: Cron job harian mengecek apakah ada transaksi tercatat pada hari berjalan (mis. cek jam 20:00 waktu lokal)
- **FR-4.2**: Jika belum ada transaksi, bot mengirim pesan reminder via Telegram
- **FR-4.3**: Reminder time dapat dikonfigurasi dari halaman pengaturan web
- **FR-4.4** *(fase lanjut)*: Notifikasi budget warning saat pengeluaran kategori mendekati/melewati limit

---

## 7. Non-Functional Requirements

- **Latensi respons bot**: idealnya < 3 detik dari pesan masuk ke balasan tersimpan+terkirim
- **Akurasi parsing**: AI harus mengutamakan bertanya balik dibanding menebak salah pada input ambigu (fail-safe, bukan fail-silent)
- **Keamanan**: data keuangan personal — API key AI & token bot disimpan di environment variable, tidak pernah di client-side; endpoint web wajib autentikasi
- **Reliabilitas reminder**: cron job harus idempotent (tidak kirim reminder dobel jika restart/retry)
- **Portabilitas hosting**: sistem harus bisa jalan di VPS murah (bukan hanya platform serverless mahal), mengingat ini proyek personal

---

## 8. Arsitektur & Tech Stack

**Catatan revisi:** target deployment adalah **shared hosting** (bukan VPS), sehingga stack Next.js (butuh Node server yang harus terus menyala untuk API routes/webhook) tidak cocok. Shared hosting umumnya hanya menyediakan: PHP (via Apache/LiteSpeed, request-per-request — tidak butuh proses yang menyala terus), MySQL/MariaDB, dan Cron Job bawaan cPanel. Arsitektur berikut disesuaikan agar seluruhnya jalan di lingkungan itu, dan sekaligus memanfaatkan stack Laravel yang sudah kamu kuasai dari SIGAP.

```
┌─────────────────┐         ┌──────────────────────┐
│  Telegram User   │◄───────►│   Telegram Bot API    │
└─────────────────┘         └──────────┬───────────┘
                                        │ webhook (HTTPS POST per pesan)
                                        ▼
                         ┌─────────────────────────────┐
                         │   Backend: Laravel (PHP)     │
                         │   di-hosting di shared hosting│
                         │   - Route webhook Telegram    │
                         │   - Transaction parser (AI)   │
                         │   - Advisory engine (AI)      │
                         │   - REST API untuk dashboard  │
                         │   - Laravel Scheduler          │
                         │     (reminder harian)          │
                         └───────┬─────────────┬────────┘
                                 │             │
                     ┌───────────▼───┐   ┌─────▼─────────┐
                     │ MySQL/MariaDB  │   │ Claude API     │
                     │ (via Eloquent) │   │ (parsing &     │
                     │                │   │ advisory)      │
                     └────────────────┘   └────────────────┘
                                 ▲
                                 │ REST API (JSON, via Axios/fetch)
                     ┌───────────┴────────────┐
                     │  Web Dashboard: React SPA │
                     │  (Vite + TypeScript +    │
                     │  Tailwind CSS + Recharts)│
                     │  → build statis, diupload │
                     │  ke shared hosting         │
                     └────────────────────────────┘

┌──────────────────────────────────────────────────────────┐
│ cPanel Cron Job (tiap menit) → php artisan schedule:run     │
│ Laravel Scheduler jalankan task "cek transaksi hari ini,    │
│ kirim reminder jika kosong" sesuai jam yang dikonfigurasi   │
└──────────────────────────────────────────────────────────┘
```

| Layer | Pilihan | Alasan |
|---|---|---|
| Frontend | **React SPA** — Vite + TypeScript + Tailwind CSS | Sesuai permintaan React; Vite build menghasilkan file statis (HTML/JS/CSS) yang tinggal diupload ke shared hosting, tidak butuh Node server sama sekali di production |
| Charting | **Recharts** | Ringan, komposabel, tidak perlu UI kit besar |
| Backend | **Laravel (PHP)**, diakses sebagai REST API | Laravel jalan native di hampir semua shared hosting (Apache/LiteSpeed + PHP-FPM); request-based, tidak perlu proses long-running — cocok untuk webhook Telegram maupun REST API dashboard. Kamu juga sudah familiar dari SIGAP |
| Database | **MySQL/MariaDB + Eloquent ORM** | Ini yang default tersedia di hampir semua paket shared hosting (via cPanel) |
| Telegram Bot | **Route Laravel biasa** sebagai webhook endpoint (`POST /api/telegram/webhook`), pakai library `irazasyed/telegram-bot-sdk` atau HTTP client manual | Webhook cocok untuk shared hosting karena Telegram yang memanggil endpoint saat ada pesan — tidak perlu proses bot yang menyala terus-menerus |
| AI Engine | **Anthropic Claude API**, dipanggil dari Laravel via Guzzle/HTTP client bawaan Laravel (structured output/tool-calling untuk parsing transaksi ke JSON; percakapan bebas untuk advisory) | Tool-calling memastikan output parsing selalu berupa data terstruktur valid, bukan teks bebas yang rawan salah parse |
| Scheduler (reminder) | **Laravel Scheduler**, dipicu oleh 1 baris **Cron Job cPanel** (`* * * * * php /home/user/artisan schedule:run`) | Ini pola standar Laravel di shared hosting — cukup 1 cron job, semua jadwal lain diatur di kode Laravel (`app/Console/Kernel.php`) |
| Auth | **Laravel Sanctum** (token-based, untuk API ke React SPA) | Ringan, standar untuk skenario SPA + API terpisah domain/subdomain, cukup untuk single-user dan mudah di-extend ke multi-user |
| Hosting | Shared hosting (cPanel) — Laravel di subdomain `api.domain.com` (document root ke folder `public/` Laravel), React SPA build statis di domain utama atau subdomain lain | Struktur umum untuk memisahkan backend PHP dan frontend statis di satu paket shared hosting; perlu konfigurasi CORS di Laravel agar API bisa diakses dari domain frontend |

**Catatan desain penting:**
- Parsing transaksi dari AI **wajib** menggunakan structured output (JSON schema via tool-calling), bukan minta AI "jawab bebas lalu di-parse manual". Ini menghindari kelas bug paling umum di project seperti ini — salah parsing nominal atau kategori karena format teks AI tidak konsisten.
- Karena shared hosting biasanya tidak mengizinkan proses background permanen, **jangan** gunakan pendekatan long-polling untuk Telegram bot (butuh proses yang jalan terus) — wajib pakai **webhook**.
- Cek dulu ke provider shared hosting: versi PHP yang tersedia (Laravel terbaru butuh PHP ≥ 8.2), akses `composer` via SSH/terminal cPanel (biasanya tersedia di paket cPanel modern), dan apakah domain sudah punya SSL aktif (wajib untuk webhook Telegram, karena Telegram hanya mau memanggil URL HTTPS).

---

## 9. Skema Data (Model Utama)

```
User
 - id, name, telegram_chat_id, email, password_hash, reminder_time, created_at

Category
 - id, user_id, name, type (income/expense), is_default

Transaction
 - id, user_id, category_id, amount, type (income/expense),
   description, raw_input_text, source (telegram/web),
   ai_comment, transaction_date, created_at

Budget (fase lanjut)
 - id, user_id, category_id, month, limit_amount

AdvisorySession (opsional, untuk histori chat advisory)
 - id, user_id, source (telegram/web), messages (JSON), created_at

ReminderLog
 - id, user_id, date, sent_at, was_needed (boolean)
```

---

## 10. Alur Sistem Utama

**Alur 1 — Input Transaksi via Telegram**
1. User kirim pesan → Telegram webhook memanggil endpoint backend
2. Backend kirim teks ke Claude API dengan tool-definition `record_transaction(amount, type, category, description)`
3. Jika AI yakin → transaksi disimpan ke DB → generate komentar singkat → balas ke user
4. Jika AI tidak yakin (nominal/kategori ambigu) → balas dengan pertanyaan klarifikasi, tunggu balasan user sebelum simpan

**Alur 2 — Advisory / Saran Keuangan**
1. User kirim `/tanya <pertanyaan>` di Telegram atau chat di web
2. Backend ambil ringkasan data user (saldo, pengeluaran 30 hari terakhir per kategori, tren) sebagai konteks
3. Konteks + pertanyaan dikirim ke Claude API (percakapan bebas, bukan tool-calling)
4. Jawaban AI dikirim balik, mereferensikan angka riil dari data user

**Alur 3 — Reminder Harian**
1. Cron job jalan tiap hari pada jam yang dikonfigurasi user
2. Cek apakah ada transaksi dengan `transaction_date` = hari ini untuk user tsb
3. Jika tidak ada → kirim pesan reminder via Telegram, catat di `ReminderLog`

---

## 11. Desain Prompt AI (Ringkasan Pendekatan)

- **Parser transaksi**: system prompt singkat + tool schema ketat, contoh few-shot khusus format Indonesia (`rb`, `k`, `jt`, tanpa "Rp"), instruksi eksplisit "jika ambigu, jangan menebak, tanyakan balik"
- **Comment generator**: prompt diarahkan agar komentar singkat (1–2 kalimat), bervariasi, tidak generik/berulang, dan proporsional — humor ringan untuk pengeluaran wajar, flag halus untuk pengeluaran tidak biasa (bukan menghakimi)
- **Advisory**: system prompt menegaskan AI harus selalu merujuk angka konkret dari data yang diberikan, dan menyatakan asumsi/keterbatasan secara eksplisit jika data tidak cukup (mis. tidak tahu total tabungan di luar sistem ini)

---

## 12. Roadmap Pengembangan

**Fase 1 — MVP (fokus utama)**
- Setup Laravel (backend/API) + MySQL, dan React SPA (Vite) untuk frontend
- Telegram bot: input transaksi + parsing AI + komentar
- Web dashboard: ringkasan, grafik dasar, riwayat transaksi, CRUD manual
- Auth single-user
- Reminder harian dasar

**Fase 2**
- Chat advisory (Telegram `/tanya` + panel chat di web)
- Kategori custom dari web
- `/undo` dan koreksi transaksi via chat

**Fase 3**
- Budgeting per kategori + notifikasi early-warning
- Export laporan (PDF/Excel)
- Multi-wallet (mis. pisah rekening/tunai)

**Fase 4 (opsional, jika ingin dibagikan ke orang lain)**
- Multi-user penuh dengan onboarding self-service

---

## 13. Metrik Keberhasilan

- Rasio hari dengan minimal 1 transaksi tercatat / total hari (target konsistensi ≥ 90%)
- Akurasi parsing AI (persentase transaksi yang tidak perlu dikoreksi manual)
- Waktu rata-rata dari pesan Telegram terkirim → transaksi tersimpan
- Penggunaan fitur advisory (jumlah pertanyaan per bulan) sebagai indikator apakah fitur ini benar-benar dipakai, bukan sekadar gimmick

---

## 14. Risiko & Mitigasi

| Risiko | Mitigasi |
|---|---|
| AI salah parsing nominal (mis. "30" dibaca 30 bukan 30.000) | Structured output ketat + selalu tampilkan konfirmasi angka di balasan bot agar salah bisa langsung ketahuan |
| Biaya API AI membengkak jika dipakai intensif | Gunakan model yang efisien untuk task parsing (task sederhana), model lebih besar hanya untuk advisory yang butuh reasoning |
| Reminder terasa mengganggu (notif tiap hari) | Reminder time dan on/off dikonfigurasi user dari web |
| Data keuangan personal bocor | Auth wajib di semua endpoint, secrets di env var, tidak ada endpoint publik yang expose data mentah |

---

## 15. Di Luar Cakupan (Out of Scope) — MVP

- Integrasi langsung ke rekening bank/e-wallet (open banking API)
- OCR struk belanja
- Aplikasi mobile native (cukup web responsive + Telegram)
- Multi-currency

---

## 16. Lampiran — Contoh Interaksi

```
User (Telegram): makan malam 30rb
Bot: ✅ Tercatat: Makan Malam — Rp30.000 (Kategori: Makanan)
     Bulan ini kamu sudah habis Rp420rb untuk kategori Makanan.

User (Telegram): /tanya aku mau upgrade PC harga 8 juta, gimana kondisiku?
Bot: Saldo bersihmu bulan ini sekitar Rp2,1 juta setelah semua pengeluaran
     tercatat. Kalau upgrade sekarang, kamu perlu nabung ±4 bulan lagi
     dengan pola pengeluaran saat ini, atau kurangi kategori Hiburan
     (rata-rata Rp600rb/bulan) untuk mempercepatnya.
```
