-- ============================================================
-- SIM-TKD - ALTER Tabel Users (tambahkan kolom yang belum ada)
-- ============================================================
-- Untuk database yang SUDAH punya tabel users (mis. produksi simtkdco_sipd)
-- tapi belum memiliki kolom kota & provinsi.
-- Aman: tidak menghapus/mengubah kolom atau data yang sudah ada.
--
-- Cara pakai (MySQL):
--   mysql -u USER -p simtkdco_sipd < users_alter.sql
-- atau tempel di phpMyAdmin (tab SQL) pada database simtkdco_sipd.
-- ============================================================

-- Tambahkan kolom kota & provinsi (jika belum ada)
ALTER TABLE users
    ADD COLUMN kota     VARCHAR(100) DEFAULT NULL AFTER instansi,
    ADD COLUMN provinsi VARCHAR(100) DEFAULT NULL AFTER kota;

-- ============================================================
-- Bila dijalankan ulang dan kolom sudah ada, akan muncul error:
--   "Duplicate column name 'kota'" / "'provinsi'"
-- Gunakan blok di bawah ini (MySQL 8.0.29+/MariaDB) agar idempoten:
-- ============================================================
-- ALTER TABLE users
--     ADD COLUMN IF NOT EXISTS kota     VARCHAR(100) DEFAULT NULL AFTER instansi,
--     ADD COLUMN IF NOT EXISTS provinsi VARCHAR(100) DEFAULT NULL AFTER kota;
