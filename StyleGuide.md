# Style Guide — FinTrack AI
### Panduan Visual & Konten untuk Web Dashboard

**Versi:** 1.0
**Terkait:** PRD.md v1.1
**Cakupan:** React SPA (dashboard web) — palet, tipografi, komponen, motion, dan gaya penulisan (copy)

---

## 1. Konsep & Filosofi Desain

FinTrack AI hidup di dua dunia sekaligus: **chat santai di Telegram** (tempat data masuk) dan **buku besar keuangan yang rapi** (tempat data dibaca). Style guide ini dibangun dari ketegangan itu — bukan menutupinya.

**Ide besar: "Buku kas digital, ditulis dari chat."**

Metafora yang dipakai konsisten di seluruh produk adalah **ledger/buku kas tradisional** — baris bergaris tipis, angka rata kanan dalam kolom, tinta hijau untuk pemasukan, tinta merah untuk pengeluaran (konvensi akuntansi tua, bukan sekadar "hijau = bagus"). Di atas fondasi itu, ada satu elemen tanda tangan yang mengikat produk ke konteks Bayu sendiri (administrasi pemerintahan, dokumen resmi, cap/stempel): **badge "cap" berbentuk stempel** yang muncul saat AI berhasil mencatat transaksi — nod halus ke budaya administrasi Indonesia (dokumen resmi selalu ber-cap), sekaligus jadi penanda visual "ini sudah diverifikasi AI dan tersimpan."

Ini bukan palet cream+terracotta atau dark+neon-hijau yang generik — dasarnya **kertas ledger hijau-abu pucat**, bukan krem hangat, dan aksennya adalah **hijau tinta akuntan** dan **merah bata tinta koreksi**, bukan neon.

---

## 2. Palet Warna

Base mode: **light (kertas ledger)** — dashboard finansial lebih enak dibaca di terang. Dark mode disediakan sebagai varian (lihat 2.2).

| Token | Hex | Peran |
|---|---|---|
| `--paper` | `#EAEDE3` | Latar utama — kertas ledger, sage pucat (bukan krem) |
| `--paper-elevated` | `#F5F7F0` | Kartu, panel, permukaan terangkat |
| `--ink` | `#1C2620` | Teks utama — hijau-hitam gelap, bukan hitam pekat |
| `--ink-muted` | `#5B6358` | Teks sekunder, caption, label |
| `--border` | `#D6D9CC` | Garis pemisah tipis (garis ledger) |
| `--ledger-green` | `#2F6F5E` | Aksen utama/brand, tombol primer, pemasukan (income) |
| `--ledger-green-soft` | `#4F9C82` | Hover/varian terang dari ledger-green |
| `--ledger-red` | `#A63D2F` | Pengeluaran (expense), state peringatan/hapus |
| `--stamp-gold` | `#9C7A2E` | **Khusus elemen "cap/stempel"** — jangan dipakai untuk hal lain, biar tetap terasa spesial |

**Aturan pemakaian warna fungsional:**
- Hijau dan merah **hanya** dipakai untuk arah nominal (income/expense), tidak untuk elemen dekoratif lain — supaya user tetap bisa scan cepat "ini pemasukan atau pengeluaran" tanpa mikir dua kali.
- `--stamp-gold` eksklusif untuk badge cap/stempel dan indikator "terverifikasi AI". Kalau dipakai di tempat lain, elemen itu kehilangan maknanya.
- Kategori transaksi (Makanan, Transportasi, dll.) pakai palet terpisah yang lebih muted/desaturated — lihat 5.3 — supaya tidak bentrok secara visual dengan hijau/merah income-expense.

### 2.2 Dark Mode (varian)

| Token | Hex |
|---|---|
| `--ink-bg-dark` | `#14201B` |
| `--paper-elevated-dark` | `#1C2A23` |
| `--text-on-dark` | `#EAEDE3` |
| `--ledger-green-dark` | `#5FA98C` |
| `--ledger-red-dark` | `#D97A64` |
| `--stamp-gold-dark` | `#C9A24E` |

---

## 3. Tipografi

Tiga peran font, masing-masing dengan alasan fungsional — bukan sekadar gaya:

| Peran | Font | Kapan dipakai |
|---|---|---|
| **Display** | **Fraunces** (serif berkarakter, ada "ink trap" — kesan dicetak/ditulis tangan di buku besar) | Judul halaman, nama section, angka ringkasan besar yang sifatnya naratif (bukan tabel) |
| **Body / UI** | **Inter** (humanist sans, netral & sangat terbaca) | Paragraf, label form, komentar AI, isi chat advisory, tombol |
| **Data / Angka** | **IBM Plex Mono** (tabular figures) | **Semua nominal uang, tanggal, waktu, dan isi tabel transaksi** — wajib, supaya digit selalu rata dan mudah dibandingkan, seperti kolom di buku kas asli |

**Skala tipe** (rem, basis 16px):

| Level | Ukuran | Weight | Font |
|---|---|---|---|
| Display XL | 2.5rem / 40px | 600 | Fraunces |
| Display | 1.75rem / 28px | 600 | Fraunces |
| Heading | 1.25rem / 20px | 600 | Inter |
| Body | 1rem / 16px | 400 | Inter |
| Caption | 0.8125rem / 13px | 400 | Inter |
| Angka besar (hero stat) | 2.25rem / 36px | 500 | IBM Plex Mono |
| Angka tabel | 0.9375rem / 15px | 500 | IBM Plex Mono |

**Aturan tegas:** nominal uang **tidak pernah** ditulis dengan Inter/Fraunces — selalu IBM Plex Mono, di mana pun ia muncul (dashboard, chat, notifikasi).

---

## 4. Layout & Spacing

- **Skala spacing** (px, basis 4): `4, 8, 12, 16, 24, 32, 48, 64` — dipakai konsisten untuk padding, gap, dan margin. Tidak ada nilai spacing di luar skala ini.
- **Container**: max-width `1200px`, padding horizontal `24px` di mobile, `48px` di desktop.
- **Grid ringkasan (dashboard home)**: kartu ringkasan (saldo, pemasukan, pengeluaran) dalam grid 3 kolom di desktop, stack vertikal di mobile.
- **Tabel transaksi**: bukan kartu per-baris (terlalu "app generik") — pakai baris dengan **garis bawah tipis** (`--border`, 1px) seperti buku kas fisik, bukan zebra-stripe warna-warni. Kolom nominal selalu rata kanan.

```
Tanggal      Deskripsi              Kategori     Nominal
────────────────────────────────────────────────────────
30 Ags       Makan malam            Makanan      -Rp30.000
30 Ags       Gaji freelance         Pemasukan   +Rp500.000
────────────────────────────────────────────────────────
```

---

## 5. Komponen

### 5.1 Tombol
- **Primary**: latar `--ledger-green`, teks `--paper`, radius kecil (`6px` — bukan pill/rounded-full, biar tetap terasa "dokumen", bukan app konsumen kasual)
- **Secondary**: outline `--border`, teks `--ink`
- **Destructive** (hapus transaksi): outline `--ledger-red`, teks `--ledger-red`, isi solid hanya saat hover/konfirmasi

### 5.2 Badge "Cap" (Signature Element)
Elemen tanda tangan produk ini. Muncul di dua momen saja (supaya tetap terasa spesial, tidak dipakai berlebihan):
1. Saat transaksi baru masuk dari Telegram dan muncul live di dashboard — badge cap kecil "TERCATAT" muncul sesaat di sebelah baris transaksi, dengan animasi stempel singkat (lihat 6. Motion), lalu memudar jadi elemen statis kecil.
2. Di halaman ringkasan bulanan yang sudah "ditutup" (bulan berjalan selesai) — cap besar bertuliskan bulan tsb, seperti stempel pengesahan laporan.

Spesifikasi visual: bentuk oval, border ganda tipis warna `--stamp-gold`, teks uppercase kecil dengan letter-spacing lebar, dirotasi sekitar `-8deg`, tanpa fill (transparan/outline saja) — supaya terasa seperti cap tinta asli, bukan sticker flat.

### 5.3 Palet Kategori (terpisah dari income/expense)
Untuk tag kategori (Makanan, Transportasi, Hiburan, dst.), pakai palet muted 6-warna yang desaturated, ditampilkan sebagai border kiri tipis pada baris tabel atau dot kecil — bukan latar penuh berwarna (biar tidak ramai):

`#7A8B6F` (Makanan) · `#6F8B9A` (Transportasi) · `#9A7F6F` (Belanja) · `#8B6F8A` (Hiburan) · `#9A8A5F` (Tagihan) · `#6F9A8B` (Kesehatan)

### 5.4 Panel Chat Advisory
Bubble AI: latar `--paper-elevated`, teks `--ink`, font Inter (percakapan, bukan data). Bubble user: latar `--ledger-green` dengan teks `--paper`. Kalau AI menyertakan angka dalam jawabannya (mis. "saldo Rp2,1 juta"), angka itu tetap di-render dengan IBM Plex Mono inline, walau kalimatnya pakai Inter — konsistensi aturan angka tidak boleh dilanggar bahkan di dalam kalimat.

### 5.5 Grafik (Recharts)
- Bar/line chart tren: garis/bar pakai `--ledger-green` untuk pemasukan, `--ledger-red` untuk pengeluaran
- Pie chart kategori: pakai palet kategori di 5.3, bukan warna random dari library
- Grid line chart tipis, warna `--border`, tanpa drop shadow di elemen chart

---

## 6. Motion

Prinsip: **motion hanya untuk satu momen** — stempel transaksi masuk (5.2). Di luar itu, transisi UI standar dan cepat (150–200ms ease-out untuk hover/focus), tanpa animasi dekoratif tambahan.

Animasi cap: scale dari `1.15 → 1.0` + rotate dari `-14deg → -8deg` dalam ~150ms, sedikit "overshoot" di akhir supaya terasa seperti hentakan stempel fisik. Untuk pengguna dengan `prefers-reduced-motion`, ganti jadi fade-in polos tanpa scale/rotate.

---

## 7. Gaya Penulisan (Copy/Voice)

- **Bahasa Indonesia santai-jelas**, bukan formal-kaku dan bukan bahasa gaul berlebihan — sama seperti nada balasan bot di PRD ("✅ Tercatat: Makan Malam — Rp30.000")
- **Kata kerja aktif, sebutkan hasil nyata**: tombol "Simpan Transaksi" bukan "Submit"; setelah disimpan, konfirmasi bilang "Tersimpan" — nama aksi konsisten dari tombol sampai notifikasi
- **Sebut yang dikontrol pengguna, bukan istilah sistem**: "Atur pengingat harian" bukan "Konfigurasi webhook reminder"
- **Empty state = ajakan bertindak**, bukan sekadar "tidak ada data":
  - Belum ada transaksi hari ini → *"Belum ada catatan hari ini — ketik pengeluaranmu di Telegram, langsung muncul di sini."*
- **Error = jelas apa yang salah + contoh perbaikan**, bukan pesan generik:
  - Parsing gagal → *"Nominalnya belum kebaca. Coba format seperti 'makan siang 20rb' atau 'bensin 15.000'."*
- **Komentar AI setelah transaksi**: 1 kalimat, bervariasi, proporsional — santai untuk pengeluaran wajar, menyorot halus (bukan menghakimi) untuk pengeluaran di luar kebiasaan

---

## 8. Aksesibilitas

- Kontras teks `--ink` di atas `--paper` memenuhi WCAG AA (rasio > 7:1)
- Semua elemen interaktif punya **visible focus ring** (`2px solid --ledger-green`, offset 2px) — jangan dihilangkan demi estetika
- Badge cap (5.2) bersifat dekoratif tambahan, **bukan satu-satunya penanda status** — status "tersimpan" tetap ada sebagai teks untuk screen reader
- Warna income/expense (hijau/merah) selalu disertai tanda `+`/`-` di depan nominal — tidak mengandalkan warna saja untuk menyampaikan arah transaksi

---

## 9. Ringkasan Do & Don't

| Do | Don't |
|---|---|
| Nominal selalu IBM Plex Mono, rata kanan | Nominal pakai font body/serif |
| Hijau/merah hanya untuk income/expense | Hijau/merah dipakai untuk elemen dekoratif lain |
| Cap/stempel muncul di 2 momen spesifik saja | Cap dipakai sebagai dekorasi berulang di banyak tempat |
| Garis tipis ala ledger untuk pemisah baris | Kartu bayangan tebal (heavy drop shadow) per baris transaksi |
| Copy aktif, spesifik, sebut aksi nyata | Copy generik ("Terjadi kesalahan", "Submit") |
