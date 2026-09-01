-- ============================================================
-- SIM-TKD - Aktivasi Otomatis Akun (tanpa verifikasi admin)
-- ============================================================
-- Pendaftaran akun baru kini langsung berstatus 'aktif'
-- (lihat api/register.php). Script ini untuk database yang SUDAH
-- berjalan: mengubah default kolom status dan mengaktifkan
-- seluruh akun lama yang masih 'pending'.
-- Idempoten -> aman dijalankan berulang.
--
-- Cara pakai (MySQL/MariaDB):
--   mysql -u USER -p NAMA_DATABASE < users_status_aktif.sql
-- atau tempel di phpMyAdmin (tab SQL).
-- ============================================================

-- 1. Ubah default kolom status: pendaftaran baru langsung aktif
ALTER TABLE users ALTER COLUMN status SET DEFAULT 'aktif';

-- 2. Aktifkan seluruh akun lama yang masih menunggu verifikasi
UPDATE users SET status = 'aktif' WHERE status = 'pending';
