-- ============================================================
-- SIM-TKD - PERSETUJUAN JURNAL AKLAP (persisten, multi-tenant)
-- ============================================================
-- Menambah kolom jurnal_status pada stbp & sts.
-- Entri jurnal yang BELUM di-approve TIDAK masuk laporan keuangan
-- (LRA / total penerimaan). Approve/Reject dilakukan di AKLAP
-- dan tersimpan di database (bukan client-side).
--
-- Cara pakai (MariaDB/MySQL):
--   mysql -u USER -p simtkdco_sipd < jurnal_approve.sql
-- atau tempel di phpMyAdmin (tab SQL).
-- ============================================================

ALTER TABLE stbp
    ADD COLUMN IF NOT EXISTS jurnal_status ENUM('belum_approve','sudah_approve','ditolak') DEFAULT 'belum_approve';

ALTER TABLE sts
    ADD COLUMN IF NOT EXISTS jurnal_status ENUM('belum_approve','sudah_approve','ditolak') DEFAULT 'belum_approve';
