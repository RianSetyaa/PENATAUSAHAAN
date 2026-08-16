-- ============================================================
-- SIM-TKD - TAMBAH KOLOM SKPD PADA TABEL KEGIATAN (multi-tenant)
-- ============================================================
-- Tabel `kegiatan` sebelumnya GLOBAL (dibagi semua instansi).
-- Skrip ini menambahkan kolom skpd agar data kegiatan per instansi,
-- lalu dashboard (simtkd.com) & AKLAP (peta.simtkd.com) memfilter-nya.
--
-- Cara pakai (MariaDB/MySQL):
--   mysql -u USER -p simtkdco_sipd < kegiatan_skpd.sql
-- atau tempel di phpMyAdmin (tab SQL) pada database simtkdco_sipd.
-- ============================================================

-- 1) Tambahkan kolom skpd bila belum ada
ALTER TABLE kegiatan
    ADD COLUMN IF NOT EXISTS skpd VARCHAR(150) DEFAULT NULL AFTER id;

-- 2) (Opsional) Tetapkan kepemilikan data kegiatan yang sudah ada.
--    GANTI 'NAMA_INSTANSI_ANDA' dengan instansi pemilik data kegiatan tsb.
--    Jalankan per baris untuk setiap instansi, atau biarkan NULL
--    (data dengan skpd NULL tidak akan tampil untuk user mana pun).
-- UPDATE kegiatan SET skpd = 'Dinas Kepemudaan dan Olahraga' WHERE skpd IS NULL;

-- 3) Index untuk mempercepat filter per instansi
SET @s = IF(
    (SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = 'kegiatan' AND index_name = 'idx_kegiatan_skpd') = 0,
    'ALTER TABLE kegiatan ADD INDEX idx_kegiatan_skpd (skpd)',
    'SELECT 1'
);
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
