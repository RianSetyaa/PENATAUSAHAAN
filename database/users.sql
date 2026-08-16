-- ============================================================
-- SIM-TKD - Tabel Users (Pendaftaran / Manajemen Pengguna)
-- ============================================================
-- Kolom kota & provinsi: pemetaan wilayah pengguna (daftar.html & AKLAP).
-- Peran default 'Pengguna Umum'; pendaftaran baru otomatis 'Bendahara'
-- (diatur di api/register.php).
--
-- Cara pakai (MySQL):
--   mysql -u root -p NAMA_DATABASE < users.sql
-- atau tempel langsung di phpMyAdmin / panel DB.
-- ============================================================

CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama_lengkap  VARCHAR(100)  NOT NULL,
    username      VARCHAR(50)   NOT NULL UNIQUE,
    email         VARCHAR(100)  NOT NULL UNIQUE,
    password      VARCHAR(255)  NOT NULL,                -- hash (password_hash)
    instansi      VARCHAR(150)  DEFAULT NULL,            -- nama instansi / dinas
    kota          VARCHAR(100)  DEFAULT NULL,            -- kota/kabupaten
    provinsi      VARCHAR(100)  DEFAULT NULL,            -- provinsi (auto dari kota)
    api_token     VARCHAR(64)   DEFAULT NULL,            -- token API per-user (multi-tenant)
    peran         ENUM('Admin Dinas','Operator','Bendahara','Verifikator','Kepala Dinas','Pengguna Umum')
                  DEFAULT 'Pengguna Umum',
    status        ENUM('pending','aktif','nonaktif') DEFAULT 'pending',  -- menunggu verifikasi admin
    created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_peran (peran),
    UNIQUE KEY idx_api_token (api_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Opsional: seed akun admin awal (GANTI password dengan hash password_hash() Anda)
-- INSERT INTO users (nama_lengkap, username, email, password, peran, status)
-- VALUES ('Administrator SIM-TKD', 'admin', 'admin@simtkd.com', '$2y$10$HASH_ANDA', 'Admin Dinas', 'aktif');
