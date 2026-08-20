# SIM-TKD — Sistem Informasi Manajemen Tata Kelola Daerah (Modul Edukasi)

Aplikasi **modul edukasi** dari **Politeknik Negeri Bandung (Jurusan Akuntansi)**.
Frontend menggunakan **HTML, CSS, dan JavaScript murni (vanilla)**. Backend menggunakan **PHP & MySQL**.

---

## ✨ Fitur

- **Login** dengan validasi captcha & indikator status akun (pending / aktif / nonaktif)
- **Pendaftaran (Daftar)** dengan validasi lengkap + indikator kekuatan kata sandi
- **Dashboard** ringkasan APBD yang datanya diambil langsung dari database
- Perlindungan sesi (halaman dashboard hanya bisa diakses setelah login)
- Password di-hash dengan `password_hash()` (bcrypt)
- Query memakai **PDO prepared statements** (aman dari SQL Injection)

---

## 🖥️ Arsitektur

```
PENATAUSAHAAN/
├── login.html          # Frontend login (vanilla)
├── daftar.html         # Frontend pendaftaran (vanilla)
├── dashboard.html      # Frontend dashboard (vanilla)
├── css/                # Stylesheet
├── js/                 # JavaScript frontend (fetch ke API)
├── img/                # Gambar / logo
│
├── api/                # ⚙ BACKEND (PHP) - endpoint API JSON
│   ├── login.php       #   POST: autentikasi login
│   ├── register.php    #   POST: pendaftaran akun
│   ├── logout.php      #   GET : hapus sesi (logout)
│   ├── session.php     #   GET : cek status login
│   └── summary.php     #   GET : data ringkasan dashboard
├── config/
│   └── db.php          # Koneksi database (PDO)
├── includes/
│   ├── functions.php   # Helper (validasi, respons JSON)
│   └── auth.php        # Sesi & proteksi halaman
├── database/
│   └── simtkd.sql      # Skema database (referensi)
├── setup.php           # Installer otomatis (DB + admin)
└── README.md
```

> **Konsep:** Halaman frontend (`.html`) memanggil API PHP (`.php` di folder `api/`)
> menggunakan `fetch()`. Backend PHP menangani database, validasi, dan sesi.
> Alur: `login.html` → `api/login.php` → `dashboard.html` → `api/summary.php`.

---

## 📋 Kebutuhan

| Software | Keterangan |
|----------|------------|
| **XAMPP** (Apache + PHP 7.4+ / 8.x + MySQL) | Disarankan |
| Browser modern | Chrome / Firefox / Edge |

---

## 🚀 Cara Instalasi (XAMPP)

1. **Salin folder proyek** ke direktori `htdocs` XAMPP:
   ```bash
   # macOS (XAMPP)
   cp -r PENATAUSAHAAN /Applications/XAMPP/htdocs/
   # Windows (XAMPP)
   # salin ke C:\xampp\htdocs\PENATAUSAHAAN
   ```

2. **Nyalakan Apache & MySQL** melalui XAMPP Control Panel.

3. **Buka installer** di browser:
   ```
   http://localhost/PENATAUSAHAAN/setup.php
   ```
   Isi form instalasi (seperti installer pada umumnya):
   - **Database Host** → `localhost`
   - **Database User** → `root`
   - **Database Password** → kosongkan jika tanpa password (isi jika MySQL Anda berpassword)
   - **Nama Database** → `simtkd` (dibuat otomatis)
   - **Username Admin** → `admin`
   - **Password Admin** → `admin123`

   Klik **🚀 Mulai Instalasi**. Installer akan otomatis:
   - Koneksi ke MySQL dengan kredensial yang Anda isi
   - Membuat database `simtkd`
   - Membuat tabel `users` dan `kegiatan`
   - Mengisi data contoh kegiatan
   - Membuat akun admin
   - **Menulis kredensial ke `config/db.php`** (otomatis)

4. **Hapus `setup.php`** setelah instalasi selesai (untuk keamanan).

5. **Login** di:
   ```
   http://localhost/PENATAUSAHAAN/login.html
   ```
   - Username: `admin`
   - Password: `admin123`

6. Setelah login berhasil, Anda diarahkan ke **dashboard**.

---

## ⚙️ Konfigurasi Database

Kredensial database **ditulis otomatis** oleh `setup.php` ke `config/db.php` saat
Anda menjalankan installer. Anda tidak perlu mengedit manual.

Jika ingin mengubah manual, buka `config/db.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'simtkd');
define('DB_USER', 'root');
define('DB_PASS', '');   // isi dengan password MySQL jika ada
```

---

## 🔌 API Endpoint

| Endpoint | Method | Deskripsi |
|----------|--------|-----------|
| `api/login.php` | POST | Login (`username`, `password`, `tahun_anggaran`) |
| `api/register.php` | POST | Daftar (`nama_lengkap`, `username`, `email`, `password`, `instansi`, `peran`) |
| `api/logout.php` | GET | Logout |
| `api/session.php` | GET | Cek sesi login (dipakai dashboard) |
| `api/summary.php` | GET | Data ringkasan APBD (wajib login) |
| `api/permohonan.php` | GET / POST | Daftar & simpan permohonan rekening (`?q=` untuk pencarian) |
| `api/akun_penerimaan.php` | GET / POST | Daftar & simpan akun penerimaan (Pengaturan) (`?q=` untuk pencarian) |
| `api/stbp.php` | GET / POST | Daftar STBP per status (`?status=` & `?q=`) & buat STBP baru |

Semua endpoint mengembalikan JSON:
```json
{ "success": true, "message": "...", ... }
```

---

## 📁 Modul

| Modul | Halaman | API | Tabel |
|-------|---------|-----|-------|
| Dashboard | `dashboard.html` | `api/summary.php` | `kegiatan` |
| Penerimaan → Rekening → Permohonan | `permohonan.html` | `api/permohonan.php` | `permohonan` |
| Pengaturan → Akun Penerimaan | `akun-penerimaan.html` | `api/akun_penerimaan.php` | `akun_penerimaan` |
| Penerimaan → STBP (Pembuatan) | `stbp-pembuatan.html` · `stbp-tambah.html` | `api/stbp.php` | `stbp`, `stbp_pembayaran`, `stbp_pendapatan` |

---

## 🔐 Keamanan

- Password disimpan dengan `password_hash()` (bcrypt)
- Query menggunakan **PDO prepared statements**
- Folder `config/`, `includes/`, `database/` dilindungi dari akses browser (`.htaccess`)
- Sesi menggunakan `session_regenerate_id()` untuk mencegah *session fixation*
- Validasi dilakukan dua lapis: **client-side** (JS) dan **server-side** (PHP)

---

## 📝 Catatan

- Aplikasi harus diakses melalui **server** (`http://localhost/...`), bukan langsung
  dari file (`file://`) — karena backend PHP & sesi membutuhkan server.
- Akun baru yang mendaftar berstatus **`pending`** dan harus diaktifkan admin
  (ubah `status` menjadi `aktif` di database/phpMyAdmin sebelum bisa login).

---

## ⚖️ Lisensi

Hak cipta © 2026 **Politeknik Negeri Bandung — Jurusan Akuntansi**. Seluruh hak cipta dilindungi.

Aplikasi ini dilindungi oleh **lisensi perangkat lunak proprietari** dan **DILARANG untuk disebarluaskan**, disalin, dijual, atau digunakan kembali tanpa izin tertulis dari pemegang hak cipta. Lihat file [`LICENSE`](./LICENSE) untuk ketentuan lengkap.

> ⛔ **TIDAK BOLEH DI-SEBAR.** Dengan menggunakan aplikasi ini, Anda menyetujui seluruh
> ketentuan lisensi yang berlaku. Setiap pelanggaran dapat ditindaklanjuti sesuai
> hukum yang berlaku di Republik Indonesia.
