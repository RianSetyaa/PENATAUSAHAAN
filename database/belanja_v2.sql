-- ============================================================
-- SIM-TKD - MODUL BELANJA v2
-- Penyesuaian Manual Book SIPD v1.1 (Penatausahaan Pengeluaran):
--   * SPP per jenis (LS Gaji, LS Barang & Jasa, UP, GU, TU)
--   * Potongan & Pajak di SPP LS
--   * LPJ (utk SPP GU)
--   * Pengajuan TU (utk SPP TU)
--   * Rekening Bank SKPD
--   * Besaran UP
--   * Kebijakan SPD
--   * NPD (Nota Pencairan Dana) Panjar & Non Panjar
-- Multi-tenant: setiap baris memakai kolom skpd = instansi user.
-- Jalankan setelah database/belanja.sql (tabel dasar).
-- ============================================================

-- 1) SPP: kolom tambahan utk GU, TU, dan total potongan/pajak
ALTER TABLE spp
    ADD COLUMN lpj_id INT UNSIGNED DEFAULT NULL AFTER rekanan_id,
    ADD COLUMN pengajuan_tu_id INT UNSIGNED DEFAULT NULL AFTER lpj_id,
    ADD COLUMN total_potongan DECIMAL(18,2) DEFAULT 0 AFTER jumlah,
    ADD COLUMN total_pajak DECIMAL(18,2) DEFAULT 0 AFTER total_potongan;

-- 2) LPJ - Laporan Pertanggungjawaban (referensi SPP GU)
CREATE TABLE IF NOT EXISTS lpj (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    skpd        VARCHAR(150) DEFAULT NULL,
    nomor_lpj   VARCHAR(50)  NOT NULL,
    tanggal     DATE         DEFAULT NULL,
    uraian      VARCHAR(255) DEFAULT NULL,
    jumlah      DECIMAL(18,2) DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_lpj_skpd (skpd)
) ENGINE=InnoDB;

-- 3) Pengajuan TU - Tambah Uang (referensi SPP TU)
CREATE TABLE IF NOT EXISTS pengajuan_tu (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    skpd            VARCHAR(150) DEFAULT NULL,
    nomor_pengajuan VARCHAR(50)  NOT NULL,
    tanggal         DATE         DEFAULT NULL,
    keterangan      VARCHAR(255) DEFAULT NULL,
    jumlah          DECIMAL(18,2) DEFAULT 0,
    status          ENUM('belum_otorisasi','sudah_otorisasi','sudah_divalidasi') DEFAULT 'belum_otorisasi',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ptu_skpd (skpd)
) ENGINE=InnoDB;

-- 4) Potongan & Pajak detail per SPP (LS Barang & Jasa)
CREATE TABLE IF NOT EXISTS spp_potongan_pajak (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    skpd        VARCHAR(150) DEFAULT NULL,
    spp_id      INT UNSIGNED DEFAULT NULL,
    jenis       ENUM('potongan','pajak') DEFAULT 'potongan',
    nama        VARCHAR(150) DEFAULT NULL,
    nilai_persen DECIMAL(5,2) DEFAULT 0,
    nilai       DECIMAL(18,2) DEFAULT 0,
    id_billing  VARCHAR(100) DEFAULT NULL,
    tgl_billing DATE         DEFAULT NULL,
    ntpn        VARCHAR(100) DEFAULT NULL,
    tgl_ntpn    DATE         DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pp_spp (spp_id)
) ENGINE=InnoDB;

-- 5) Rekening Bank SKPD (Permohonan -> Pengajuan -> Pembuatan -> Validasi -> aktif)
CREATE TABLE IF NOT EXISTS rekening_skpd (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    skpd           VARCHAR(150) DEFAULT NULL,
    user_id        INT UNSIGNED DEFAULT NULL,
    bank           VARCHAR(100) DEFAULT NULL,
    nama_pemilik   VARCHAR(150) DEFAULT NULL,
    nomor_rekening VARCHAR(50)  DEFAULT NULL,
    status         ENUM('permohonan','pengajuan','pembuatan','aktif','ditolak') DEFAULT 'permohonan',
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_rek_skpd (skpd)
) ENGINE=InnoDB;

-- 6) Besaran UP (Uang Persediaan)
CREATE TABLE IF NOT EXISTS besaran_up (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    skpd           VARCHAR(150) DEFAULT NULL,
    tahun          VARCHAR(10)  DEFAULT NULL,
    besaran_up     DECIMAL(18,2) DEFAULT 0,
    besaran_up_kkpd DECIMAL(18,2) DEFAULT 0,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_bu_skpd (skpd)
) ENGINE=InnoDB;

-- 7) Kebijakan SPD (jenis penerbitan & periode)
CREATE TABLE IF NOT EXISTS kebijakan_spd (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    skpd            VARCHAR(150) DEFAULT NULL,
    jenis_penerbitan VARCHAR(100) DEFAULT NULL,
    jenis_periode   VARCHAR(100) DEFAULT NULL,
    tanggal         DATE         DEFAULT NULL,
    status          ENUM('aktif','nonaktif') DEFAULT 'aktif',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_kspd_skpd (skpd)
) ENGINE=InnoDB;

-- 8) NPD - Nota Pencairan Dana (Panjar & Non Panjar)
CREATE TABLE IF NOT EXISTS npd (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    skpd         VARCHAR(150) DEFAULT NULL,
    user_id      INT UNSIGNED DEFAULT NULL,
    nomor_npd    VARCHAR(50)  NOT NULL,
    tanggal      DATE         DEFAULT NULL,
    metode       ENUM('panjar','non_panjar') DEFAULT 'panjar',
    keterangan   VARCHAR(255) DEFAULT NULL,
    kegiatan     VARCHAR(200) DEFAULT NULL,
    sub_kegiatan VARCHAR(200) DEFAULT NULL,
    jumlah       DECIMAL(18,2) DEFAULT 0,
    status       ENUM('diajukan','divalidasi_pa','divalidasi_bp','ditolak') DEFAULT 'diajukan',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_npd_skpd (skpd)
) ENGINE=InnoDB;
