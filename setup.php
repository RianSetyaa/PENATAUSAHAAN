<?php
/**
 * SIM-TKD - Setup Installer (Form)
 * ============================================
 * Installer berbasis form (TERKUNCI TOKEN).
 *
 * Cara pakai:
 *   1. Buat file config/setup_token.php:  <?php return 'TOKEN_RAHASIA';
 *   2. Buka http://localhost/PENATAUSAHAAN/setup.php?token=TOKEN_RAHASIA
 *   3. Isi kredensial database lalu klik "Mulai Instalasi".
 *
 * Script ini akan:
 *  1. Koneksi ke MySQL menggunakan kredensial yang Anda isi
 *  2. Membuat database (jika belum ada)
 *  3. Membuat tabel `users` dan `kegiatan`
 *  4. Membuat akun admin (default: admin / admin123)
 *  5. Menulis kredensial ke file `config/credentials.php` (tidak menimpa db.php)
 *
 * Setelah sukses, HAPUS file ini dari server Anda!
 */

declare(strict_types=1);

// ============================================
// PENGAMAN: installer hanya bisa dijalankan dengan SETUP_TOKEN
// ============================================
// Cara pakai:
//   1. Buat file config/setup_token.php berisi: <?php return 'TOKEN_RAHASIA';
//   2. Buka: setup.php?token=TOKEN_RAHASIA
// Setelah instalasi selesai: HAPUS file setup.php dari server!
$setupTokenFile = __DIR__ . '/config/setup_token.php';
$setupToken = is_file($setupTokenFile) ? trim((string) require $setupTokenFile) : '';
if ($setupToken === '') {
    http_response_code(403);
    exit('<h2 style="font-family:sans-serif">Setup terkunci</h2><p style="font-family:sans-serif">Installer dinonaktifkan. Buat file <code>config/setup_token.php</code> berisi token rahasia (<code>&lt;?php return "TOKEN";</code>) lalu akses <code>setup.php?token=TOKEN</code>. Setelah selesai, hapus file <code>setup.php</code> dari server.</p>');
}
$givenToken = (string) ($_REQUEST['setup_token'] ?? $_GET['token'] ?? '');
if ($givenToken === '' || !hash_equals($setupToken, $givenToken)) {
    http_response_code(403);
    exit('<h2 style="font-family:sans-serif">Akses ditolak</h2><p style="font-family:sans-serif">Token setup tidak valid. Akses dengan <code>setup.php?token=TOKEN</code>.</p>');
}

error_reporting(E_ALL);
ini_set('display_errors', '1');

// ============================================
// Nilai default (XAMPP: root tanpa password)
// ============================================
$defaults = [
    'db_host'    => 'localhost',
    'db_user'    => 'root',
    'db_pass'    => '',
    'db_name'    => 'simtkd',
    'admin_user' => 'admin',
    'admin_pass' => 'admin123',
];

$submitted = ($_SERVER['REQUEST_METHOD'] === 'POST');

// Ambil input dari form
$input = [
    'db_host'    => trim($_POST['db_host'] ?? $defaults['db_host']),
    'db_user'    => trim($_POST['db_user'] ?? $defaults['db_user']),
    'db_pass'    => (string) ($_POST['db_pass'] ?? $defaults['db_pass']),
    'db_name'    => trim($_POST['db_name'] ?? $defaults['db_name']),
    'admin_user' => trim($_POST['admin_user'] ?? $defaults['admin_user']),
    'admin_pass' => (string) ($_POST['admin_pass'] ?? $defaults['admin_pass']),
];

$output   = [];
$hasError = false;
$done     = false;

// ============================================
// Fungsi menulis config/credentials.php
// ============================================
function writeConfigFile(array $cfg): bool
{
    // Tulis kredensial ke config/credentials.php (file terpisah, di-gitignore).
    // db.php sudah membaca file ini secara otomatis — tidak perlu menimpa db.php.
    $cred = "<?php\n"
        . "/**\n"
        . " * KREDENSIAL DATABASE - dibuat oleh setup.php (" . date('Y-m-d H:i:s') . ")\n"
        . " * File ini TIDAK di-commit ke git. Jaga kerahasiaannya.\n"
        . " */\n"
        . "return [\n"
        . "    'host' => " . var_export($cfg['db_host'], true) . ",\n"
        . "    'name' => " . var_export($cfg['db_name'], true) . ",\n"
        . "    'user' => " . var_export($cfg['db_user'], true) . ",\n"
        . "    'pass' => " . var_export($cfg['db_pass'], true) . ",\n"
        . "];\n";

    return file_put_contents(__DIR__ . '/config/credentials.php', $cred) !== false;
}

// ============================================
// Proses instalasi
// ============================================
if ($submitted) {
    // Validasi input
    if ($input['db_host'] === '' || $input['db_user'] === '' || $input['db_name'] === '') {
        $hasError = true;
        $output[] = '<strong style="color:#e74c3c;">Host, User, dan Nama Database wajib diisi.</strong>';
    } elseif (strlen($input['admin_pass']) < 6) {
        $hasError = true;
        $output[] = '<strong style="color:#e74c3c;">Password admin minimal 6 karakter.</strong>';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $input['db_name'])) {
        $hasError = true;
        $output[] = '<strong style="color:#e74c3c;">Nama database hanya boleh huruf, angka, dan underscore.</strong>';
    } else {
        try {
            // 1. Koneksi tanpa memilih database
            $pdo = new PDO(
                "mysql:host={$input['db_host']};charset=utf8mb4",
                $input['db_user'],
                $input['db_pass'],
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT            => 10,
                ]
            );
            $output[] = '✓ Koneksi ke MySQL berhasil.';

            // 2. Buat database jika belum ada
            $dbName = $input['db_name'];
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $output[] = "✓ Database `{$dbName}` siap (dibuat jika belum ada).";

            $pdo->exec("USE `{$dbName}`");

            // 3. Buat tabel users
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS users (
                    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    nama_lengkap  VARCHAR(100)  NOT NULL,
                    username      VARCHAR(50)   NOT NULL UNIQUE,
                    email         VARCHAR(100)  NOT NULL UNIQUE,
                    password      VARCHAR(255)  NOT NULL,
                    instansi      VARCHAR(150)  DEFAULT NULL,
                    kota          VARCHAR(100)  DEFAULT NULL,
                    provinsi      VARCHAR(100)  DEFAULT NULL,
                    api_token     VARCHAR(64)   DEFAULT NULL,
                    peran         ENUM('Admin Dinas','Operator','Bendahara','Verifikator','Kepala Dinas','Pengguna Umum')
                                  DEFAULT 'Pengguna Umum',
                    status        ENUM('pending','aktif','nonaktif') DEFAULT 'pending',
                    created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
                    updated_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_status (status),
                    INDEX idx_peran (peran),
                    UNIQUE KEY idx_api_token (api_token)
                ) ENGINE=InnoDB
            ");
            $output[] = '✓ Tabel `users` siap.';

            // 4. Buat tabel kegiatan
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS kegiatan (
                    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    skpd          VARCHAR(150) DEFAULT NULL,
                    nama_kegiatan VARCHAR(200) NOT NULL,
                    tahun         SMALLINT UNSIGNED NOT NULL,
                    pagu          DECIMAL(18,2) NOT NULL DEFAULT 0,
                    realisasi     DECIMAL(18,2) NOT NULL DEFAULT 0,
                    status        ENUM('berjalan','selesai','batal') DEFAULT 'berjalan',
                    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_tahun (tahun)
                ) ENGINE=InnoDB
            ");
            $output[] = '✓ Tabel `kegiatan` siap.';

            // 4b. Buat tabel permohonan
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS permohonan (
                    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id           INT UNSIGNED      DEFAULT NULL,
                    skpd              VARCHAR(150)      NOT NULL DEFAULT '',
                    bank              VARCHAR(100)      NOT NULL,
                    nama_rekening     VARCHAR(150)      NOT NULL,
                    nomor_rekening    VARCHAR(50)       NOT NULL DEFAULT '',
                    status_terbit     TINYINT(1)        NOT NULL DEFAULT 0,
                    status_disetujui  TINYINT(1)        NOT NULL DEFAULT 0,
                    status_aktif      TINYINT(1)        NOT NULL DEFAULT 0,
                    created_at        TIMESTAMP         DEFAULT CURRENT_TIMESTAMP,
                    updated_at        TIMESTAMP         DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_bank (bank),
                    INDEX idx_user (user_id),
                    INDEX idx_created (created_at)
                ) ENGINE=InnoDB
            ");
            $output[] = '✓ Tabel `permohonan` siap.';

            // 4c. Buat tabel akun_penerimaan
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS akun_penerimaan (
                    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id       INT UNSIGNED      DEFAULT NULL,
                    skpd          VARCHAR(150)      NOT NULL DEFAULT '',
                    kode_akun     VARCHAR(50)       NOT NULL,
                    nama_akun     VARCHAR(200)      NOT NULL,
                    metode_input  VARCHAR(30)       NOT NULL DEFAULT 'harian',
                    status_aktif  TINYINT(1)        NOT NULL DEFAULT 1,
                    created_at    TIMESTAMP         DEFAULT CURRENT_TIMESTAMP,
                    updated_at    TIMESTAMP         DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_kode (kode_akun),
                    INDEX idx_user (user_id),
                    INDEX idx_created (created_at)
                ) ENGINE=InnoDB
            ");
            $output[] = '✓ Tabel `akun_penerimaan` siap.';

            // 4c-1. (Seed akun_penerimaan DINONAKTIFKAN: daftar akun kini master statis
            // di js/akun-master.js. DB hanya menyimpan akun yang DICENTANG oleh
            // masing-masing dinas, via aksi save_selection pada api/akun_penerimaan.php.)
            if (false) {
                $cntAkun = (int) $pdo->query("SELECT COUNT(*) FROM akun_penerimaan")->fetchColumn();
                $akunSeed = [
                    // 4.1.1 Pajak Daerah
                    ['4.1.1','Pendapatan Pajak Daerah','per_wajib_pajak'],
                    ['4.1.1.01','Pajak Hotel','per_wajib_pajak'],
                    ['4.1.1.01.01','Hotel Bintang Berlian','per_wajib_pajak'],
                    ['4.1.1.01.02','Hotel Bintang Lima','per_wajib_pajak'],
                    ['4.1.1.01.03','Hotel Bintang Empat','per_wajib_pajak'],
                    ['4.1.1.01.04','Hotel Bintang Tiga','per_wajib_pajak'],
                    ['4.1.1.01.05','Hotel Bintang Dua','per_wajib_pajak'],
                    ['4.1.1.01.06','Hotel Bintang Satu','per_wajib_pajak'],
                    ['4.1.1.01.07','Hotel Melati Tiga','per_wajib_pajak'],
                    ['4.1.1.02','Pajak Restoran','per_wajib_pajak'],
                    ['4.1.1.02.01','Restoran','per_wajib_pajak'],
                    ['4.1.1.02.02','Rumah Makan','per_wajib_pajak'],
                    ['4.1.1.02.03','Kafe','per_wajib_pajak'],
                    ['4.1.1.02.04','Kantin','per_wajib_pajak'],
                    ['4.1.1.03','Pajak Hiburan','per_wajib_pajak'],
                    ['4.1.1.03.01','Tontonan Film/Bioskop','per_wajib_pajak'],
                    ['4.1.1.03.02','Pameran','per_wajib_pajak'],
                    ['4.1.1.03.03','Diskotek','per_wajib_pajak'],
                    ['4.1.1.03.04','Pacuan Kuda','per_wajib_pajak'],
                    ['4.1.1.04','Pajak Reklame','per_wajib_pajak'],
                    ['4.1.1.04.01','Reklame Papan/Billboard','per_wajib_pajak'],
                    ['4.1.1.04.02','Reklame Kain','per_wajib_pajak'],
                    ['4.1.1.04.09','Reklame Film/Slide','per_wajib_pajak'],
                    ['4.1.1.05','Penerangan Jalan Umum','per_wajib_pajak'],
                    ['4.1.1.05.01','Pajak Penerangan Jalan PLN','per_wajib_pajak'],
                    ['4.1.1.08','Pajak Air Bawah Tanah','per_wajib_pajak'],
                    ['4.1.1.08.01','Pajak Air Bawah Tanah','per_wajib_pajak'],
                    ['4.1.1.09','Pajak Sarang Burung Walet','per_wajib_pajak'],
                    ['4.1.1.09.01','Pajak Sarang Burung Walet','per_wajib_pajak'],
                    ['4.1.1.10','Pajak Lingkungan','per_wajib_pajak'],
                    ['4.1.1.10.01','Pajak Lingkungan','per_wajib_pajak'],
                    // 4.1.2 Retribusi Daerah
                    ['4.1.2','Pendapatan Retribusi Daerah','per_wajib_retribusi'],
                    ['4.1.2.01','Retribusi Jasa Usaha','per_wajib_retribusi'],
                    ['4.1.2.01.01','Retribusi Pemakaian Kekayaan Daerah','per_wajib_retribusi'],
                    ['4.1.2.01.02','Retribusi Parkir','per_wajib_retribusi'],
                    ['4.1.2.01.03','Retribusi Sewa Pasar Tradisional','per_wajib_retribusi'],
                    // 4.1.3 Hasil Pengelolaan Kekayaan Daerah yang Dipisahkan
                    ['4.1.3','Hasil Pengelolaan Kekayaan Daerah yang Dipisahkan','bulanan'],
                    ['4.1.3.01','Bagian Laba atas Penyertaan Modal Pemerintah Daerah','bulanan'],
                    ['4.1.3.01.01','BUMD - A','bulanan'],
                    ['4.1.3.01.02','PT - B','bulanan'],
                    ['4.1.3.04','Pendapatan Bunga Obligasi pada BUMN','bulanan'],
                    ['4.1.3.04.01','BUMN - A','bulanan'],
                    ['4.1.3.04.02','BUMN - B','bulanan'],
                    // 4.1.4 Lain-Lain PAD yang Sah
                    ['4.1.4','Lain-Lain Pendapatan Asli Daerah yang Sah','harian'],
                    ['4.1.4.01','Hasil Penjualan Aset Daerah yang Tidak Dipisahkan','harian'],
                    ['4.1.4.01.01','Pelepasan Hak atas Tanah','harian'],
                    ['4.1.4.02','Penerimaan Jasa Giro','harian'],
                    ['4.1.4.02.03','Jasa Giro Dana Cadangan','harian'],
                    ['4.1.4.03','Pendapatan Bunga Deposito','harian'],
                    ['4.1.4.03.01','Rekening Deposito pada Bank','harian'],
                    ['4.1.4.06.03','Denda Keterlambatan Pekerjaan Bidang PU','harian'],
                    ['4.1.4.13','Tuntutan Ganti Kerugian Daerah','harian'],
                    ['4.1.4.13.03','Kerugian Uang','harian'],
                    // 4.2.1 Bagi Hasil Pajak / Bukan Pajak
                    ['4.2.1','Bagi Hasil Pajak / Bagi Hasil Bukan Pajak','bulanan'],
                    ['4.2.1.01','Bagi Hasil Pajak','bulanan'],
                    ['4.2.1.01.01','Bagi Hasil PBB','bulanan'],
                    ['4.2.1.01.02','Bagi Hasil BPHTB','bulanan'],
                    ['4.2.1.01.03','Bagi Hasil PPh Psl 25 dan Psl 29 WPOPDN dan PPh Psl 21','bulanan'],
                    ['4.2.1.02','Bagi Hasil Sumber Daya Alam','bulanan'],
                    ['4.2.1.02.01','Bagi Hasil Iuran Hak Penguasaan Hutan','bulanan'],
                    ['4.2.1.02.02','Bagi Hasil Provinsi Sumber Daya Hutan','bulanan'],
                    ['4.2.1.02.03','Bagi Hasil Dana Reboisasi','bulanan'],
                    // 4.2.2 Dana Alokasi Umum
                    ['4.2.2','Dana Alokasi Umum','bulanan'],
                    ['4.2.2.01','Dana Alokasi Umum','bulanan'],
                    ['4.2.2.01.01','Dana Alokasi Umum','bulanan'],
                    // 4.2.3 Dana Alokasi Khusus
                    ['4.2.3','Dana Alokasi Khusus','bulanan'],
                    ['4.2.3.01','Dana Alokasi Khusus','bulanan'],
                    ['4.2.3.01.01','Dana Alokasi Khusus','bulanan'],
                    // 4.3.1 Pendapatan Hibah
                    ['4.3.1','Pendapatan Hibah','harian'],
                    ['4.3.1.01','Pendapatan Hibah dari Pemerintah','harian'],
                    ['4.3.1.01.01','Pendapatan Hibah dari Pemerintah','harian'],
                    ['4.3.1.02','Pendapatan Hibah dari Pemerintah Daerah Lainnya','harian'],
                    ['4.3.1.02.01','Pendapatan Hibah dari Pemerintah Daerah Lainnya','harian'],
                    // 4.3.2 Pendapatan Dana Darurat
                    ['4.3.2','Pendapatan Dana Darurat','bulanan'],
                    ['4.3.2.01','Penanggulangan Korban','bulanan'],
                    ['4.3.2.01.01','Korban/Kerusakan Akibat Bencana Alam','bulanan'],
                    // 4.3.3 Dana Bagi Hasil Pajak dari Provinsi dan Pemda Lainnya
                    ['4.3.3','Dana Bagi Hasil Pajak dari Provinsi dan Pemda Lainnya','bulanan'],
                    ['4.3.3.01','Dana Bagi Hasil Pajak dari Provinsi','bulanan'],
                    ['4.3.3.01.01','Bagi Hasil Pajak Kendaraan Bermotor','bulanan'],
                    ['4.3.3.01.03','Bagi Hasil dari Bea Balik Nama Kendaraan Bermotor','bulanan'],
                    ['4.3.3.02','Dana Bagi Hasil Pajak dari Provinsi Lainnya','bulanan'],
                    ['4.3.3.02.01','Bagi Hasil Pajak dari Provinsi Lainnya','bulanan'],
                    ['4.3.3.03','Dana Bagi Hasil Pajak dari Kabupaten dari Provinsi Lainnya','bulanan'],
                    ['4.3.3.03.01','Dana Bagi Hasil Pajak dari Kabupaten dari Provinsi Lainnya','bulanan'],
                    // 4.3.4 Dana Penyesuaian dan Otonomi Khusus
                    ['4.3.4','Dana Penyesuaian dan Otonomi Khusus','bulanan'],
                    ['4.3.4.02','Dana Otonomi Khusus','bulanan'],
                    ['4.3.4.02.01','Dana Otonomi Khusus','bulanan'],
                    // 4.3.5 Bantuan Keuangan dari Pemerintah Provinsi atau Pemda Lain
                    ['4.3.5','Bantuan Keuangan dari Pemerintah Provinsi atau Pemda Lain','bulanan'],
                    ['4.3.5.01','Bantuan Keuangan Provinsi','bulanan'],
                    ['4.3.5.01.01','Bantuan Keuangan dari Provinsi Setempat','bulanan'],
                ];
                $insAkun = $pdo->prepare("INSERT INTO akun_penerimaan (user_id, skpd, kode_akun, nama_akun, metode_input) VALUES (NULL, ?, ?, ?, ?)");
                foreach ($akunSeed as $a) {
                    $insAkun->execute(['Dinas Kepemudaan dan Olahraga', $a[0], $a[1], $a[2]]);
                }
                $output[] = '✓ Seed ' . count($akunSeed) . ' akun penerimaan (bagan rekening SKPKD) ditambahkan.';
            }

            // 4d. Buat tabel stbp
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS stbp (
                    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id       INT UNSIGNED      DEFAULT NULL,
                    skpd          VARCHAR(150)      NOT NULL DEFAULT '',
                    nomor_stbp    VARCHAR(50)       NOT NULL,
                    tanggal       DATE              NOT NULL,
                    akun_kode     VARCHAR(50)       NOT NULL DEFAULT '',
                    akun_nama     VARCHAR(200)      NOT NULL DEFAULT '',
                    jumlah        DECIMAL(18,2)     NOT NULL DEFAULT 0,
                    uraian        VARCHAR(255)      NOT NULL DEFAULT '',
                    status        ENUM('belum_diverifikasi','sudah_diverifikasi','sudah_diotorisasi','sudah_divalidasi','dihapus')
                                  NOT NULL DEFAULT 'belum_diverifikasi',
                    created_at    TIMESTAMP         DEFAULT CURRENT_TIMESTAMP,
                    updated_at    TIMESTAMP         DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_status (status),
                    INDEX idx_tanggal (tanggal),
                    INDEX idx_user (user_id)
                ) ENGINE=InnoDB
            ");
            $output[] = '✓ Tabel `stbp` siap.';

            // 4e. Buat tabel stbp_pembayaran
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS stbp_pembayaran (
                    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    stbp_id           INT UNSIGNED      NOT NULL,
                    metode_penyetoran VARCHAR(20)       NOT NULL DEFAULT 'non_tunai',
                    nama_penyetor     VARCHAR(150)      NOT NULL DEFAULT '',
                    nama_bank         VARCHAR(100)      NOT NULL DEFAULT '',
                    nomor_rekening    VARCHAR(50)       NOT NULL DEFAULT '',
                    created_at        TIMESTAMP         DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_stbp (stbp_id)
                ) ENGINE=InnoDB
            ");
            $output[] = '✓ Tabel `stbp_pembayaran` siap.';

            // 4f. Buat tabel stbp_pendapatan
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS stbp_pendapatan (
                    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    stbp_id       INT UNSIGNED      NOT NULL,
                    akun_kode     VARCHAR(50)       NOT NULL DEFAULT '',
                    akun_nama     VARCHAR(200)      NOT NULL DEFAULT '',
                    rekening_bank VARCHAR(100)      NOT NULL DEFAULT '',
                    rekening_nama VARCHAR(150)      NOT NULL DEFAULT '',
                    rekening_nomor VARCHAR(50)      NOT NULL DEFAULT '',
                    nominal       DECIMAL(18,2)     NOT NULL DEFAULT 0,
                    created_at    TIMESTAMP         DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_stbp (stbp_id)
                ) ENGINE=InnoDB
            ");
            $output[] = '✓ Tabel `stbp_pendapatan` siap.';

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS sts (
                    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id            INT UNSIGNED      DEFAULT NULL,
                    skpd               VARCHAR(150)      NOT NULL DEFAULT '',
                    nomor_sts          VARCHAR(50)       NOT NULL,
                    nama_penyetor      VARCHAR(150)      NOT NULL DEFAULT '',
                    tanggal_sts        DATE              NOT NULL,
                    tanggal_acuan_dari DATE              DEFAULT NULL,
                    tanggal_acuan_akhir DATE             DEFAULT NULL,
                    mengetahui         VARCHAR(150)      NOT NULL DEFAULT '',
                    nama_bank          VARCHAR(100)      NOT NULL DEFAULT '',
                    nomor_rekening     VARCHAR(50)       NOT NULL DEFAULT '',
                    nama_rekening      VARCHAR(150)      NOT NULL DEFAULT '',
                    keterangan         VARCHAR(255)      NOT NULL DEFAULT '',
                    total              DECIMAL(18,2)     NOT NULL DEFAULT 0,
                    status             ENUM('aktif','dihapus') NOT NULL DEFAULT 'aktif',
                    created_at         TIMESTAMP         DEFAULT CURRENT_TIMESTAMP,
                    updated_at         TIMESTAMP         DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_status (status),
                    INDEX idx_tanggal (tanggal_sts),
                    INDEX idx_user (user_id)
                ) ENGINE=InnoDB
            ");
            $output[] = '✓ Tabel `sts` siap.';

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS sts_detail (
                    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    sts_id     INT UNSIGNED      NOT NULL,
                    stbp_id    INT UNSIGNED      NOT NULL DEFAULT 0,
                    nomor_stbp VARCHAR(50)       NOT NULL DEFAULT '',
                    tanggal    DATE              DEFAULT NULL,
                    akun_kode  VARCHAR(50)       NOT NULL DEFAULT '',
                    akun_nama  VARCHAR(200)      NOT NULL DEFAULT '',
                    jumlah     DECIMAL(18,2)     NOT NULL DEFAULT 0,
                    uraian     VARCHAR(255)      NOT NULL DEFAULT '',
                    created_at TIMESTAMP         DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_sts (sts_id)
                ) ENGINE=InnoDB
            ");
            $output[] = '✓ Tabel `sts_detail` siap.';

            // 5. Buat akun admin (jika belum ada)
            $adminExists = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $adminExists->execute([$input['admin_user']]);
            if ($adminExists->fetchColumn() == 0) {
                $hash = password_hash($input['admin_pass'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (nama_lengkap, username, email, password, instansi, peran, status)
                    VALUES (?, ?, ?, ?, 'Dinas Kepemudaan dan Olahraga', 'Admin Dinas', 'aktif')
                ");
                $stmt->execute([
                    'Administrator SIM-TKD',
                    $input['admin_user'],
                    $input['admin_user'] . '@simtkd.local',
                    $hash
                ]);
                $output[] = '✓ Akun admin dibuat (username: <b>' . htmlspecialchars($input['admin_user']) . '</b>).';
            } else {
                $output[] = 'ℹ Akun admin sudah ada, dilewati.';
            }

            // 6. Tulis config/db.php
            if (writeConfigFile($input)) {
                $output[] = '✓ File <b>config/db.php</b> ditulis dengan kredensial Anda.';
            } else {
                $hasError = true;
                $output[] = '<strong style="color:#e74c3c;">⚠ Tidak dapat menulis config/db.php. ' .
                    'Periksa izin tulis folder <b>config/</b> atau edit manual file tersebut.</strong>';
            }

            $done = !$hasError;
            if ($done) {
                $output[] = '<strong style="color:#27ae60;">Setup selesai! Anda dapat menghapus file setup.php ini.</strong>';
            }
        } catch (PDOException $e) {
            $hasError = true;
            $output[] = '<strong style="color:#e74c3c;">Terjadi kesalahan koneksi/instalasi:</strong><br>'
                . htmlspecialchars($e->getMessage())
                . '<br><em>Pastikan MySQL aktif dan kredensial benar (host, user, password).</em>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup SIM-TKD</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #0d1f3c 0%, #1a3a6b 60%, #1a5276 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .box {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.25);
            padding: 32px 40px;
            max-width: 560px;
            width: 100%;
        }
        .logo-row { display: flex; align-items: center; gap: 14px; margin-bottom: 6px; }
        .logo-row img { width: 52px; height: 52px; object-fit: contain; border-radius: 8px; background: #fff; }
        h1 { font-size: 20px; color: #1a3a6b; }
        p.sub { color: #5a6c7d; margin: 0 0 20px; font-size: 13px; }
        label { display: block; font-size: 13px; font-weight: 600; color: #2c3e50; margin: 14px 0 5px; }
        input[type=text], input[type=password] {
            width: 100%;
            padding: 11px 14px;
            border: 2px solid #dce3ea;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: border .2s;
        }
        input:focus { outline: none; border-color: #1a3a6b; }
        .hint { font-size: 12px; color: #9098a5; margin-top: 4px; }
        .btn {
            width: 100%;
            margin-top: 22px;
            background: linear-gradient(135deg, #1a3a6b, #0f2444);
            color: #fff;
            border: none;
            padding: 14px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            letter-spacing: 1px;
            transition: transform .2s, box-shadow .2s;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(26,58,107,.35); }
        ul { list-style: none; padding: 0; margin: 16px 0; }
        li { padding: 8px 0; border-bottom: 1px solid #eef1f6; font-size: 13.5px; color: #2c3e50; line-height: 1.5; }
        .btns { display: flex; gap: 10px; margin-top: 8px; flex-wrap: wrap; }
        .btns a {
            flex: 1;
            text-align: center;
            display: inline-block;
            padding: 12px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
        }
        .btns .primary { background: #1a3a6b; color: #fff; }
        .btns .secondary { background: #f58220; color: #fff; }
        .error-box { background: #fdeeee; border: 1px solid #f5b7b1; border-radius: 8px; padding: 12px 14px; margin-bottom: 8px; }
        .ok-box { background: #e8f8f0; border: 1px solid #a9dfbf; border-radius: 8px; padding: 12px 14px; margin-bottom: 8px; }
    </style>
</head>
<body>
    <div class="box">
        <div class="logo-row">
            <img src="img/Logo Polban.png" alt="Logo POLBAN">
            <div>
                <h1>⚙ Setup SIM-TKD</h1>
                <p class="sub">Sistem Informasi Manajemen Tata Kelola Daerah (Modul Edukasi) — Politeknik Negeri Bandung</p>
            </div>
        </div>

        <?php if (!$submitted): ?>
            <!-- FORM -->
            <form method="post" autocomplete="off">
                <input type="hidden" name="setup_token" value="<?= htmlspecialchars($givenToken) ?>">
                <label for="db_host">Database Host</label>
                <input type="text" id="db_host" name="db_host" value="<?= htmlspecialchars($input['db_host']) ?>" required>
                <div class="hint">Umumnya <b>localhost</b> untuk XAMPP/MAMP/Laragon.</div>

                <label for="db_user">Database User</label>
                <input type="text" id="db_user" name="db_user" value="<?= htmlspecialchars($input['db_user']) ?>" required>
                <div class="hint">Default XAMPP: <b>root</b>.</div>

                <label for="db_pass">Database Password</label>
                <input type="password" id="db_pass" name="db_pass" value="" placeholder="Kosongkan jika tanpa password">
                <div class="hint">Isi password MySQL Anda jika ada (mis. MAMP/Laragon).</div>

                <label for="db_name">Nama Database</label>
                <input type="text" id="db_name" name="db_name" value="<?= htmlspecialchars($input['db_name']) ?>" required>
                <div class="hint">Akan dibuat otomatis jika belum ada.</div>

                <label for="admin_user">Username Admin</label>
                <input type="text" id="admin_user" name="admin_user" value="<?= htmlspecialchars($input['admin_user']) ?>" required>

                <label for="admin_pass">Password Admin</label>
                <input type="text" id="admin_pass" name="admin_pass" value="<?= htmlspecialchars($input['admin_pass']) ?>" required>
                <div class="hint">Gunakan untuk login ke aplikasi (default: <b>admin123</b>).</div>

                <button type="submit" class="btn">🚀 MULAI INSTALASI</button>
            </form>

        <?php else: ?>
            <!-- HASIL -->
            <?php if ($hasError): ?>
                <div class="error-box">Instalasi <b>gagal</b>. Periksa pesan di bawah lalu coba lagi.</div>
            <?php else: ?>
                <div class="ok-box">Instalasi <b>berhasil</b>! 🎉</div>
            <?php endif; ?>

            <ul>
                <?php foreach ($output as $line): ?>
                    <li><?= $line ?></li>
                <?php endforeach; ?>
            </ul>

            <div class="btns">
                <?php if (!$hasError): ?>
                    <a href="login.html" class="primary">Ke Halaman Login</a>
                    <a href="dashboard.html" class="secondary">Ke Dashboard</a>
                <?php else: ?>
                    <a href="setup.php?token=<?= urlencode($givenToken) ?>" class="primary">Coba Lagi</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
