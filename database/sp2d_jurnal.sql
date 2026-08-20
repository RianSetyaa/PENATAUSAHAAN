-- ============================================================
-- SIM-TKD - JURNAL BELANJA DI AKLAP
-- Menambah kolom jurnal_status pada tabel sp2d (pengeluaran)
-- agar SP2D yang sudah dicairkan bisa di-approve/tolak di AKLAP
-- dan hanya yang sudah approve masuk laporan keuangan (LRA).
-- Jalankan setelah database/belanja_v2.sql.
-- ============================================================

ALTER TABLE sp2d
    ADD COLUMN jurnal_status ENUM('belum_approve','sudah_approve','ditolak') DEFAULT 'belum_approve';
