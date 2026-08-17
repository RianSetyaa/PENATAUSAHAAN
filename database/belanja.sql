-- ============================================================
-- SIM-TKD - MODUL BELANJA (Penatausahaan Pengeluaran/Pembiayaan)
-- Mengikuti manual book SIPD: Rekanan -> SPD -> SPP -> SPM -> SP2D -> BKU
-- Multi-tenant: setiap baris memakai kolom skpd = instansi user.
-- ============================================================

-- 1) Daftar Rekanan (vendor/pihak ketiga)
CREATE TABLE IF NOT EXISTS rekanan (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED DEFAULT NULL,
    skpd            VARCHAR(150) DEFAULT NULL,
    nama_rekanan    VARCHAR(200) NOT NULL,
    npwp            VARCHAR(30)  DEFAULT NULL,
    alamat          VARCHAR(255) DEFAULT NULL,
    bank            VARCHAR(100) DEFAULT NULL,
    nomor_rekening  VARCHAR(50)  DEFAULT NULL,
    nama_rekening   VARCHAR(150) DEFAULT NULL,
    jenis           ENUM('perusahaan','perseorangan') DEFAULT 'perusahaan',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_rekanan_skpd (skpd)
) ENGINE=InnoDB;

-- 2) SPD - Surat Penyediaan Dana
CREATE TABLE IF NOT EXISTS spd (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED DEFAULT NULL,
    skpd            VARCHAR(150) DEFAULT NULL,
    nomor_spd       VARCHAR(50)  NOT NULL,
    tanggal         DATE         DEFAULT NULL,
    jenis           VARCHAR(50)  DEFAULT NULL,   -- Gaji, Barang & Jasa, Modal, dll
    periode         VARCHAR(50)  DEFAULT NULL,
    jumlah          DECIMAL(18,2) DEFAULT 0,
    status          ENUM('belum_otorisasi','sudah_otorisasi') DEFAULT 'belum_otorisasi',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_spd_skpd (skpd)
) ENGINE=InnoDB;

-- 3) SPP - Surat Permintaan Pembayaran
CREATE TABLE IF NOT EXISTS spp (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED DEFAULT NULL,
    skpd            VARCHAR(150) DEFAULT NULL,
    nomor_spp       VARCHAR(50)  NOT NULL,
    tanggal         DATE         DEFAULT NULL,
    jenis_spp       VARCHAR(50)  DEFAULT NULL,   -- LS Barang & Jasa, UP, GU, TU
    spd_id          INT UNSIGNED DEFAULT NULL,
    rekanan_id      INT UNSIGNED DEFAULT NULL,
    keperluan       VARCHAR(255) DEFAULT NULL,
    jumlah          DECIMAL(18,2) DEFAULT 0,
    status          ENUM('belum_diverifikasi','sudah_diverifikasi') DEFAULT 'belum_diverifikasi',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_spp_skpd (skpd)
) ENGINE=InnoDB;

-- 4) SPM - Surat Perintah Membayar
CREATE TABLE IF NOT EXISTS spm (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED DEFAULT NULL,
    skpd            VARCHAR(150) DEFAULT NULL,
    nomor_spm       VARCHAR(50)  NOT NULL,
    tanggal         DATE         DEFAULT NULL,
    spp_id          INT UNSIGNED DEFAULT NULL,
    jumlah          DECIMAL(18,2) DEFAULT 0,
    status          ENUM('belum_disetujui','belum_diverifikasi','sudah_diverifikasi') DEFAULT 'belum_disetujui',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_spm_skpd (skpd)
) ENGINE=InnoDB;

-- 5) SP2D - Surat Perintah Pencairan Dana
CREATE TABLE IF NOT EXISTS sp2d (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED DEFAULT NULL,
    skpd            VARCHAR(150) DEFAULT NULL,
    nomor_sp2d      VARCHAR(50)  NOT NULL,
    tanggal         DATE         DEFAULT NULL,
    spm_id          INT UNSIGNED DEFAULT NULL,
    rekening        VARCHAR(100) DEFAULT NULL,
    jumlah          DECIMAL(18,2) DEFAULT 0,
    status          ENUM('belum_diverifikasi','sudah_diverifikasi','sudah_dicairkan') DEFAULT 'belum_diverifikasi',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sp2d_skpd (skpd)
) ENGINE=InnoDB;
