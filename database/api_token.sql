-- ============================================================
-- SIM-TKD - TAMBAH KOLOM API TOKEN (multi-tenant AKLAP)
-- ============================================================
-- File khusus untuk menambahkan kolom api_token pada tabel users.
-- Aman dijalankan berulang (idempoten).
--
-- Cara pakai (MariaDB/MySQL):
--   mysql -u USER -p simtkdco_sipd < api_token.sql
-- atau tempel di phpMyAdmin (tab SQL) pada database simtkdco_sipd.
-- ============================================================

-- 1) Tambahkan kolom api_token bila belum ada (mendukung MariaDB 10.0.2+)
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS api_token VARCHAR(64) DEFAULT NULL;

-- 2) Isi token unik untuk semua user yang masih kosong (UUID tanpa strip)
UPDATE users SET api_token = REPLACE(UUID(), '-', '') WHERE api_token IS NULL OR api_token = '';

-- 3) (Opsional) Akun admin memakai token default agar AKLAP dibuka admin langsung
UPDATE users SET api_token = 'ce82dba3fa012a233bb69e325acc9593' WHERE username = 'admin';

-- 4) Index unik agar token tidak kembar (abaikan bila sudah ada)
SET @s = IF(
    (SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE() AND table_name = 'users' AND index_name = 'idx_api_token') = 0,
    'ALTER TABLE users ADD UNIQUE KEY idx_api_token (api_token)',
    'SELECT 1'
);
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
