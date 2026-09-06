<?php
/**
 * SIM-TKD (peta.simtkd.com) - API Model Akuntansi Manual (Double-Entry)
 * =====================================================================
 * Jurnal Umum dua sisi (Debet/Kredit) yang diinput MANUAL, dengan saran
 * template jurnal dari dokumen modul Penerimaan (STS) dan modul Belanja
 * (SP2D) yang sudah ada. Mengikuti lembar kerja Modul 3 AKLAP SKPKD.
 *
 *   GET  api/akuntansi.php?token=XXX&action=profil            : profil akun
 *   GET  api/akuntansi.php?token=XXX&action=akun_list&q=      : daftar akun (auto-seed)
 *   POST api/akuntansi.php?token=XXX&action=akun_simpan       : simpan/ubah akun
 *   POST api/akuntansi.php?token=XXX&action=akun_hapus        : hapus akun
 *   GET  api/akuntansi.php?token=XXX&action=jurnal_list       : daftar jurnal + detail
 *   POST api/akuntansi.php?token=XXX&action=jurnal_simpan     : simpan jurnal (D=K)
 *   POST api/akuntansi.php?token=XXX&action=jurnal_hapus      : hapus jurnal
 *   POST api/akuntansi.php?token=XXX&action=jurnal_status     : approve / reject
 *   GET  api/akuntansi.php?token=XXX&action=saran_dokumen     : STS & SP2D siap dijurnal
 *   GET  api/akuntansi.php?token=XXX&action=saran_jurnal      : template D/K dari dokumen
 *   GET  api/akuntansi.php?token=XXX&action=buku_besar        : posting per akun
 *   GET  api/akuntansi.php?token=XXX&action=neraca_saldo      : rekap mutasi per akun
 *
 * Token dapat dikirim via query (?token=) atau header Authorization: Bearer.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/api.php';

header('Content-Type: application/json; charset=utf-8');

$pdo    = db();
$action = (string) ($_GET['action'] ?? '');

// ---------- Verifikasi token (per-user / multi-tenant) ----------
// Pola sama dengan api/aklap.php: token = SHA-256 hash tersimpan di users.
$token = (string) ($_GET['token'] ?? '');
if ($token === '') {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $auth, $m)) {
        $token = trim($m[1]);
    }
}
$tokenHash = hash('sha256', $token);
$stmtUser = $pdo->prepare("SELECT id, nama_lengkap, username, email, instansi, kota, provinsi, peran FROM users WHERE api_token = ? LIMIT 1");
$stmtUser->execute([$tokenHash]);
$user = $stmtUser->fetch();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token API tidak valid.'], JSON_UNESCAPED_UNICODE);
    exit;
}
// SKPD/instansi pemilik token -> pemisahan data antar dinas (multi-tenant).
// Fail-closed: token tanpa instansi ditolak (bukan melihat semua data).
$skpdUser = trim((string) ($user['instansi'] ?? ''));
if ($skpdUser === '') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Instansi akun belum diatur. Hubungi administrator.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Seluruh aksi dibungkus try/catch: kegagalan DB tetap mengembalikan JSON
// yang jelas, bukan error 500 (pola sama dengan api/aklap.php).
try {
// ============================================================
// 0. Pastikan tabel & kolom tersedia (tanpa import SQL manual)
// ============================================================
$pdo->exec("CREATE TABLE IF NOT EXISTS akun_master (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    skpd         VARCHAR(150) NOT NULL DEFAULT '',
    kode_akun    VARCHAR(50)  NOT NULL,
    nama_akun    VARCHAR(200) NOT NULL,
    tipe         ENUM('aset','liabilitas','ekuitas','pendapatan','belanja') NOT NULL DEFAULT 'aset',
    saldo_normal ENUM('debet','kredit') NOT NULL DEFAULT 'debet',
    aktif        TINYINT(1)   NOT NULL DEFAULT 1,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_akun_skpd (skpd, kode_akun),
    INDEX idx_akun_kode (kode_akun)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS jurnal_umum (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    skpd         VARCHAR(150) NOT NULL DEFAULT '',
    user_id      INT UNSIGNED DEFAULT NULL,
    nomor_jurnal VARCHAR(60)  NOT NULL DEFAULT '',
    tanggal      DATE         NULL,
    uraian       VARCHAR(255) NOT NULL DEFAULT '',
    sumber_tipe  ENUM('manual','sts','sp2d') NOT NULL DEFAULT 'manual',
    sumber_id    INT UNSIGNED DEFAULT NULL,
    status       ENUM('draft','sudah_approve','ditolak') NOT NULL DEFAULT 'draft',
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ju_skpd (skpd),
    INDEX idx_ju_tanggal (tanggal),
    INDEX idx_ju_sumber (sumber_tipe, sumber_id),
    INDEX idx_ju_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");


$pdo->exec("CREATE TABLE IF NOT EXISTS jurnal_umum_detail (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    jurnal_id  INT UNSIGNED NOT NULL,
    skpd       VARCHAR(150) NOT NULL DEFAULT '',
    kode_akun  VARCHAR(50)  NOT NULL DEFAULT '',
    nama_akun  VARCHAR(200) NOT NULL DEFAULT '',
    posisi     ENUM('debet','kredit') NOT NULL,
    jumlah     DECIMAL(18,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_jud_jurnal (jurnal_id),
    INDEX idx_jud_akun (skpd, kode_akun),
    INDEX idx_jud_posisi (posisi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Penanda sudah_dijurnal pada dokumen sumber (anti dobel jurnal).
// Kompatibel MySQL & MariaDB: cek information_schema, lalu ALTER biasa.
function pastikan_kolom(PDO $pdo, string $tbl, string $col, string $ddl): void
{
    try {
        $q = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $q->execute([$tbl, $col]);
        if ((int) $q->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE `{$tbl}` ADD COLUMN {$ddl}");
        }
    } catch (Throwable $e) {
        error_log('[AKUNTANSI] pastikan_kolom ' . $tbl . '.' . $col . ': ' . $e->getMessage());
    }
}
pastikan_kolom($pdo, 'sts',  'sudah_dijurnal', "`sudah_dijurnal` TINYINT(1) NOT NULL DEFAULT 0");
pastikan_kolom($pdo, 'sp2d', 'sudah_dijurnal', "`sudah_dijurnal` TINYINT(1) NOT NULL DEFAULT 0");

// ============================================================
// 0b. Helper bersama
// ============================================================

/** Seed default Chart of Accounts (BSA ringkas, Perwali 112/2018) bila kosong. */
function akun_seed_default(PDO $pdo, string $skpd): void
{
    $st = $pdo->prepare("SELECT COUNT(*) FROM akun_master WHERE skpd = ?");
    $st->execute([$skpd]);
    if ((int) $st->fetchColumn() > 0) return;

    $defaults = [
        // kode, nama, tipe, saldo_normal
        ['1.1.1.01', 'Kas di Bendahara Penerimaan',       'aset',       'debet'],
        ['1.1.1.02', 'Kas di Bendahara Pengeluaran',      'aset',       'debet'],
        ['1.1.1.03', 'Uang Persediaan',                   'aset',       'debet'],
        ['1.1.2.01', 'Rekening Bank SKPD',                'aset',       'debet'],
        ['1.1.3.01', 'Kas di Bendahara Umum Daerah',      'aset',       'debet'],
        ['2.1.1.01', 'Utang Pajak',                       'liabilitas', 'kredit'],
        ['2.1.1.02', 'Utang Potongan',                    'liabilitas', 'kredit'],
        ['4.1.1.01', 'Pendapatan Pajak Daerah',           'pendapatan', 'kredit'],
        ['4.1.2.01', 'Pendapatan Retribusi Daerah',       'pendapatan', 'kredit'],
        ['4.1.4.01', 'Pendapatan Lain-lain PAD yang Sah', 'pendapatan', 'kredit'],
        ['5.1.1.01', 'Belanja Gaji dan Tunjangan',        'belanja',    'debet'],
        ['5.1.2.01', 'Belanja Barang dan Jasa',           'belanja',    'debet'],
        ['5.1.3.01', 'Belanja Modal',                     'belanja',    'debet'],
    ];
    $ins = $pdo->prepare("INSERT IGNORE INTO akun_master (skpd, kode_akun, nama_akun, tipe, saldo_normal) VALUES (?, ?, ?, ?, ?)");
    foreach ($defaults as $d) {
        $ins->execute([$skpd, $d[0], $d[1], $d[2], $d[3]]);
    }
}

/** Nama akun dari kode: akun_master (skpd) -> akun_penerimaan -> ''. */
function akun_nama_of(PDO $pdo, string $skpd, string $kode): string
{
    if ($kode === '') return '';
    try {
        $q = $pdo->prepare("SELECT nama_akun FROM akun_master WHERE skpd = ? AND kode_akun = ? LIMIT 1");
        $q->execute([$skpd, $kode]);
        $n = (string) ($q->fetchColumn() ?: '');
        if ($n !== '') return $n;
    } catch (Throwable $e) {}
    try {
        $q = $pdo->prepare("SELECT nama_akun FROM akun_penerimaan WHERE kode_akun = ? ORDER BY id DESC LIMIT 1");
        $q->execute([$kode]);
        return (string) ($q->fetchColumn() ?: '');
    } catch (Throwable $e) {}
    return '';
}

function tanggal_valid(string $t): bool
{
    return $t !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $t) === 1;
}


// ============================================================
// 1. Profil akun (untuk topbar halaman AKLAP)
// ============================================================
if ($action === 'profil') {
    echo json_encode(['success' => true, 'user' => [
        'nama'      => (string) ($user['nama_lengkap'] ?? ''),
        'username'  => (string) ($user['username'] ?? ''),
        'instansi'  => $skpdUser,
        'kota'      => (string) ($user['kota'] ?? ''),
        'provinsi'  => (string) ($user['provinsi'] ?? ''),
        'peran'     => (string) ($user['peran'] ?? ''),
    ]], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// 2. Master Akun
// ============================================================
if ($action === 'akun_list') {
    akun_seed_default($pdo, $skpdUser);
    $q = trim((string) ($_GET['q'] ?? ''));
    $sql  = "SELECT id, kode_akun, nama_akun, tipe, saldo_normal, aktif FROM akun_master WHERE skpd = ?";
    $params = [$skpdUser];
    if ($q !== '') {
        $sql .= " AND (kode_akun LIKE ? OR nama_akun LIKE ?)";
        $params[] = "%{$q}%"; $params[] = "%{$q}%";
    }
    $sql .= " ORDER BY kode_akun ASC LIMIT 200";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = [];
    foreach ($st->fetchAll() as $r) {
        $rows[] = [
            'id'           => (int) $r['id'],
            'kode_akun'    => (string) $r['kode_akun'],
            'nama_akun'    => (string) $r['nama_akun'],
            'tipe'         => (string) $r['tipe'],
            'saldo_normal' => (string) $r['saldo_normal'],
            'aktif'        => (int) $r['aktif'],
        ];
    }
    echo json_encode(['success' => true, 'data' => $rows, 'total' => count($rows)], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'akun_simpan' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $id     = (int) ($_POST['id'] ?? 0);
    $kode   = trim((string) ($_POST['kode_akun'] ?? ''));
    $nama   = trim((string) ($_POST['nama_akun'] ?? ''));
    $tipe   = (string) ($_POST['tipe'] ?? 'aset');
    $normal = (string) ($_POST['saldo_normal'] ?? 'debet');
    if ($kode === '' || $nama === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Kode akun dan nama akun wajib diisi.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!in_array($tipe, ['aset','liabilitas','ekuitas','pendapatan','belanja'], true)) $tipe = 'aset';
    if (!in_array($normal, ['debet','kredit'], true)) $normal = 'debet';

    if ($id > 0) {
        $st = $pdo->prepare("UPDATE akun_master SET kode_akun = ?, nama_akun = ?, tipe = ?, saldo_normal = ? WHERE id = ? AND skpd = ?");
        $st->execute([$kode, $nama, $tipe, $normal, $id, $skpdUser]);
        echo json_encode(['success' => true, 'message' => 'Akun diperbarui.', 'id' => $id], JSON_UNESCAPED_UNICODE);
    } else {
        try {
            $st = $pdo->prepare("INSERT INTO akun_master (skpd, kode_akun, nama_akun, tipe, saldo_normal) VALUES (?, ?, ?, ?, ?)");
            $st->execute([$skpdUser, $kode, $nama, $tipe, $normal]);
            echo json_encode(['success' => true, 'message' => 'Akun tersimpan.', 'id' => (int) $pdo->lastInsertId()], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Kode akun sudah dipakai untuk instansi ini.'], JSON_UNESCAPED_UNICODE);
        }
    }
    exit;
}

if ($action === 'akun_hapus' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'ID akun tidak valid.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $st = $pdo->prepare("DELETE FROM akun_master WHERE id = ? AND skpd = ?");
    $st->execute([$id, $skpdUser]);
    echo json_encode([
        'success' => true,
        'message' => $st->rowCount() > 0 ? 'Akun dihapus.' : 'Akun tidak ditemukan.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}


// ============================================================
// 3. Jurnal Umum - daftar (header + baris detail D/K)
// ============================================================
if ($action === 'jurnal_list') {
    $dari  = tanggal_valid((string) ($_GET['dari'] ?? ''))  ? (string) $_GET['dari']  : '';
    $akhir = tanggal_valid((string) ($_GET['akhir'] ?? '')) ? (string) $_GET['akhir'] : '';
    $q     = trim((string) ($_GET['q'] ?? ''));
    $st    = (string) ($_GET['status'] ?? '');

    $sql = "SELECT * FROM jurnal_umum WHERE skpd = ?";
    $params = [$skpdUser];
    if ($dari !== '')  { $sql .= " AND tanggal >= ?"; $params[] = $dari; }
    if ($akhir !== '') { $sql .= " AND tanggal <= ?"; $params[] = $akhir; }
    if ($st !== '' && in_array($st, ['draft','sudah_approve','ditolak'], true)) { $sql .= " AND status = ?"; $params[] = $st; }
    if ($q !== '') {
        $sql .= " AND (nomor_jurnal LIKE ? OR uraian LIKE ?)";
        $params[] = "%{$q}%"; $params[] = "%{$q}%";
    }
    $sql .= " ORDER BY tanggal ASC, id ASC LIMIT 500";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $det = $pdo->prepare("SELECT id, kode_akun, nama_akun, posisi, jumlah FROM jurnal_umum_detail WHERE jurnal_id = ? ORDER BY posisi DESC, id ASC");
    $rows = [];
    foreach ($stmt->fetchAll() as $r) {
        $det->execute([(int) $r['id']]);
        $baris = [];
        $totalD = 0.0; $totalK = 0.0;
        foreach ($det->fetchAll() as $d) {
            $baris[] = [
                'kode_akun' => (string) $d['kode_akun'],
                'nama_akun' => (string) $d['nama_akun'],
                'posisi'    => (string) $d['posisi'],
                'jumlah'    => (float) $d['jumlah'],
            ];
            if ($d['posisi'] === 'debet') $totalD += (float) $d['jumlah']; else $totalK += (float) $d['jumlah'];
        }
        $rows[] = [
            'id'           => (int) $r['id'],
            'nomor_jurnal' => (string) $r['nomor_jurnal'],
            'tanggal'      => (string) ($r['tanggal'] ?? ''),
            'uraian'       => (string) $r['uraian'],
            'sumber_tipe'  => (string) $r['sumber_tipe'],
            'sumber_id'    => (int) ($r['sumber_id'] ?? 0),
            'status'       => (string) $r['status'],
            'total_debet'  => round($totalD, 2),
            'total_kredit' => round($totalK, 2),
            'detail'       => $baris,
        ];
    }
    echo json_encode(['success' => true, 'data' => $rows, 'total' => count($rows)], JSON_UNESCAPED_UNICODE);
    exit;
}


// ============================================================
// 4. Jurnal Umum - simpan (validasi kaidah D = K)
// ============================================================
if ($action === 'jurnal_simpan' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $id         = (int) ($_POST['id'] ?? 0);
    $tanggal    = trim((string) ($_POST['tanggal'] ?? ''));
    $uraian     = trim((string) ($_POST['uraian'] ?? ''));
    $sumberTipe = (string) ($_POST['sumber_tipe'] ?? 'manual');
    $sumberId   = (int) ($_POST['sumber_id'] ?? 0);
    $rawDetail  = (string) ($_POST['detail'] ?? '[]');

    if (!in_array($sumberTipe, ['manual','sts','sp2d'], true)) $sumberTipe = 'manual';
    if (!tanggal_valid($tanggal)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Tanggal jurnal wajib diisi (format YYYY-MM-DD).'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $detail = json_decode($rawDetail, true);
    if (!is_array($detail) || count($detail) < 2) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Jurnal umum minimal memiliki 2 baris (Debet dan Kredit).'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Normalisasi baris jurnal
    $baris = [];
    $totalD = 0.0; $totalK = 0.0;
    foreach ($detail as $d) {
        if (!is_array($d)) continue;
        $kode   = trim((string) ($d['kode_akun'] ?? ''));
        $posisi = strtolower(trim((string) ($d['posisi'] ?? '')));
        $jumlah = (float) ($d['jumlah'] ?? 0);
        if ($kode === '' || $jumlah <= 0) continue;
        if (!in_array($posisi, ['debet','kredit'], true)) continue;
        $baris[] = [
            'kode_akun' => $kode,
            'nama_akun' => akun_nama_of($pdo, $skpdUser, $kode),
            'posisi'    => $posisi,
            'jumlah'    => round($jumlah, 2),
        ];
        if ($posisi === 'debet') $totalD += $jumlah; else $totalK += $jumlah;
    }
    if (count($baris) < 2) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Setiap baris wajib memiliki kode akun, posisi D/K, dan jumlah > 0.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (abs($totalD - $totalK) > 0.01) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Jurnal TIDAK seimbang: Total Debet Rp ' . number_format($totalD, 2, ',', '.') . ' != Total Kredit Rp ' . number_format($totalK, 2, ',', '.') . '.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Verifikasi dokumen sumber (STS / SP2D) bila memakai template
    if ($sumberTipe !== 'manual' && $sumberId > 0) {
        $tabel = ($sumberTipe === 'sts') ? 'sts' : 'sp2d';
        $kondisi = ($sumberTipe === 'sts') ? "status = 'aktif'" : "status = 'sudah_dicairkan'";
        $st = $pdo->prepare("SELECT id FROM {$tabel} WHERE id = ? AND {$kondisi} AND COALESCE(sudah_dijurnal, 0) = 0 AND skpd = ?");
        $st->execute([$sumberId, $skpdUser]);
        if (!$st->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Dokumen sumber tidak tersedia / sudah pernah dibuatkan jurnal.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } else {
        $sumberTipe = 'manual'; $sumberId = 0;
    }

    $pdo->beginTransaction();
    try {
        if ($id > 0) {
            // Edit: pastikan milik SKPD, bebaskan penanda lama bila sumber berubah
            $old = $pdo->prepare("SELECT id, sumber_tipe, sumber_id FROM jurnal_umum WHERE id = ? AND skpd = ?");
            $old->execute([$id, $skpdUser]);
            $oldRow = $old->fetch();
            if (!$oldRow) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Jurnal tidak ditemukan.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $pdo->prepare("DELETE FROM jurnal_umum_detail WHERE jurnal_id = ?")->execute([$id]);
            $pdo->prepare("UPDATE jurnal_umum SET tanggal = ?, uraian = ?, sumber_tipe = ?, sumber_id = ? WHERE id = ?")
                ->execute([$tanggal, $uraian, $sumberTipe, $sumberId ?: null, $id]);
            if (!empty($oldRow['sumber_tipe']) && $oldRow['sumber_tipe'] !== 'manual'
                && (int) $oldRow['sumber_id'] !== $sumberId) {
                $pdo->prepare("UPDATE {$oldRow['sumber_tipe']} SET sudah_dijurnal = 0 WHERE id = ?")
                    ->execute([(int) $oldRow['sumber_id']]);
            }
            $jurnalId = $id;
            $pesan = 'Jurnal diperbarui.';
        } else {
            $st = $pdo->prepare("INSERT INTO jurnal_umum (skpd, user_id, tanggal, uraian, sumber_tipe, sumber_id) VALUES (?, ?, ?, ?, ?, ?)");
            $st->execute([$skpdUser, (int) ($user['id'] ?? 0), $tanggal, $uraian, $sumberTipe, $sumberId ?: null]);
            $jurnalId = (int) $pdo->lastInsertId();
            $nomor = 'JU-' . date('Ym') . '-' . str_pad((string) $jurnalId, 4, '0', STR_PAD_LEFT);
            $pdo->prepare("UPDATE jurnal_umum SET nomor_jurnal = ? WHERE id = ?")->execute([$nomor, $jurnalId]);
            $pesan = 'Jurnal ' . $nomor . ' tersimpan.';
        }

        $insD = $pdo->prepare("INSERT INTO jurnal_umum_detail (jurnal_id, skpd, kode_akun, nama_akun, posisi, jumlah) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($baris as $b) {
            $insD->execute([$jurnalId, $skpdUser, $b['kode_akun'], $b['nama_akun'], $b['posisi'], $b['jumlah']]);
        }

        if ($sumberTipe !== 'manual' && $sumberId > 0) {
            $pdo->prepare("UPDATE {$sumberTipe} SET sudah_dijurnal = 1 WHERE id = ?")->execute([$sumberId]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => $pesan, 'id' => $jurnalId], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    exit;
}


// ============================================================
// 5. Jurnal Umum - hapus (bebaskan penanda dokumen sumber)
// ============================================================
if ($action === 'jurnal_hapus' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'ID jurnal tidak valid.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $st = $pdo->prepare("SELECT sumber_tipe, sumber_id FROM jurnal_umum WHERE id = ? AND skpd = ?");
    $st->execute([$id, $skpdUser]);
    $row = $st->fetch();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Jurnal tidak ditemukan.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM jurnal_umum_detail WHERE jurnal_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM jurnal_umum WHERE id = ?")->execute([$id]);
        if (!empty($row['sumber_tipe']) && $row['sumber_tipe'] !== 'manual' && (int) $row['sumber_id'] > 0) {
            $pdo->prepare("UPDATE {$row['sumber_tipe']} SET sudah_dijurnal = 0 WHERE id = ?")->execute([(int) $row['sumber_id']]);
        }
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Jurnal dihapus.'], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    exit;
}

// ============================================================
// 6. Jurnal Umum - approve / reject (pembukuan: hanya yang
//    sudah_approve masuk Buku Besar, Neraca Saldo, LRA, Neraca)
// ============================================================
if ($action === 'jurnal_status' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $id     = (int) ($_POST['id'] ?? 0);
    $status = (string) ($_POST['status'] ?? '');
    if ($id <= 0 || !in_array($status, ['sudah_approve','ditolak','draft'], true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Parameter status tidak valid.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $st = $pdo->prepare("UPDATE jurnal_umum SET status = ? WHERE id = ? AND skpd = ?");
    $st->execute([$status, $id, $skpdUser]);
    $label = ['sudah_approve' => 'di-approve', 'ditolak' => 'ditolak', 'draft' => 'dikembalikan ke draft'][$status];
    echo json_encode([
        'success' => true,
        'message' => $st->rowCount() > 0 ? ('Jurnal ' . $label . '.') : 'Jurnal tidak ditemukan.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}


// ============================================================
// 7. Saran Dokumen - daftar STS & SP2D yang siap dibuatkan jurnal
//    (status valid + belum pernah dijurnal)
// ============================================================
if ($action === 'saran_dokumen') {
    $sts = [];
    $sp2d = [];
    try {
        $st = $pdo->prepare("SELECT id, nomor_sts, tanggal_sts, total FROM sts
                             WHERE skpd = ? AND status = 'aktif' AND COALESCE(sudah_dijurnal, 0) = 0
                             ORDER BY tanggal_sts DESC, id DESC LIMIT 100");
        $st->execute([$skpdUser]);
        foreach ($st->fetchAll() as $r) {
            $sts[] = [
                'id'      => (int) $r['id'],
                'nomor'   => (string) $r['nomor_sts'],
                'tanggal' => (string) $r['tanggal_sts'],
                'jumlah'  => (float) $r['total'],
            ];
        }
    } catch (Throwable $e) {}

    try {
        $st = $pdo->prepare("SELECT sp2d.id, sp2d.nomor_sp2d, sp2d.tanggal, sp2d.jumlah, spp.jenis_spp
                             FROM sp2d
                             LEFT JOIN spm ON spm.id = sp2d.spm_id
                             LEFT JOIN spp ON spp.id = spm.spp_id
                             WHERE sp2d.skpd = ? AND sp2d.status = 'sudah_dicairkan' AND COALESCE(sp2d.sudah_dijurnal, 0) = 0
                             ORDER BY sp2d.tanggal DESC, sp2d.id DESC LIMIT 100");
        $st->execute([$skpdUser]);
        foreach ($st->fetchAll() as $r) {
            $sp2d[] = [
                'id'        => (int) $r['id'],
                'nomor'     => (string) $r['nomor_sp2d'],
                'tanggal'   => (string) ($r['tanggal'] ?? ''),
                'jumlah'    => (float) $r['jumlah'],
                'jenis_spp' => (string) ($r['jenis_spp'] ?? ''),
            ];
        }
    } catch (Throwable $e) {}

    echo json_encode(['success' => true, 'sts' => $sts, 'sp2d' => $sp2d], JSON_UNESCAPED_UNICODE);
    exit;
}


// ============================================================
// 8. Saran Jurnal - template baris Debet/Kredit dari dokumen
//    STS (Penerimaan) atau SP2D (Belanja), dapat diedit manual
//    sebelum disimpan di jurnal_simpan.
// ============================================================
if ($action === 'saran_jurnal') {
    $sumber = strtolower((string) ($_GET['sumber'] ?? ''));
    $id     = (int) ($_GET['id'] ?? 0);
    if ($id <= 0 || !in_array($sumber, ['sts','sp2d'], true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Sumber dan ID dokumen wajib dipilih.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Kode akun bawaan (chart of accounts ringkas)
    $KAS_PENERIMAAN  = '1.1.1.01'; // Kas di Bendahara Penerimaan
    $KAS_PENGELUARAN = '1.1.1.02'; // Kas di Bendahara Pengeluaran
    $KAS_BUD         = '1.1.3.01'; // Kas di Bendahara Umum Daerah
    $BLJ_GAJI        = '5.1.1.01'; // Belanja Gaji dan Tunjangan
    $BLJ_BJ          = '5.1.2.01'; // Belanja Barang dan Jasa
    $BLJ_MODAL       = '5.1.3.01'; // Belanja Modal
    $UTANG_PAJAK     = '2.1.1.01'; // Utang Pajak
    $UTANG_POTONGAN  = '2.1.1.02'; // Utang Potongan

    $rows = [];      // ['kode_akun','posisi','jumlah','saran_nama']
    $tanggal = ''; $uraian = ''; $nomor = '';


    if ($sumber === 'sts') {
        // ---------- STS (Penerimaan) ----------
        // D Kas di Bendahara Penerimaan (total)
        // K Pendapatan per kode rekening pada sts_detail
        $st = $pdo->prepare("SELECT * FROM sts WHERE id = ? AND skpd = ? AND status = 'aktif' LIMIT 1");
        $st->execute([$id, $skpdUser]);
        $sts = $st->fetch();
        if (!$sts) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'STS tidak ditemukan / tidak aktif.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $total   = (float) $sts['total'];
        $tanggal = (string) $sts['tanggal_sts'];
        $nomor   = (string) $sts['nomor_sts'];
        $uraian  = 'Penerimaan disetor ke Kas Daerah - STS No. ' . $nomor;
        $rows[] = ['kode_akun' => $KAS_PENERIMAAN, 'posisi' => 'debet', 'jumlah' => $total, 'saran_nama' => akun_nama_of($pdo, $skpdUser, $KAS_PENERIMAAN)];

        $det = $pdo->prepare("SELECT akun_kode, akun_nama, jumlah FROM sts_detail WHERE sts_id = ? AND jumlah > 0 ORDER BY id ASC");
        $det->execute([$id]);
        $sumK = 0.0;
        foreach ($det->fetchAll() as $d) {
            $kode = trim((string) $d['akun_kode']);
            if ($kode === '') $kode = '4.1.4.01';
            $rows[] = ['kode_akun' => $kode, 'posisi' => 'kredit', 'jumlah' => (float) $d['jumlah'], 'saran_nama' => ((string) $d['akun_nama'] !== '' ? (string) $d['akun_nama'] : akun_nama_of($pdo, $skpdUser, $kode))];
            $sumK += (float) $d['jumlah'];
        }
        if (count($rows) === 1) {
            // Tidak ada detail: kredit pendapatan lain-lain senilai total
            $rows[] = ['kode_akun' => '4.1.4.01', 'posisi' => 'kredit', 'jumlah' => $total, 'saran_nama' => akun_nama_of($pdo, $skpdUser, '4.1.4.01')];
        } elseif (abs($sumK - $total) > 0.01) {
            $rows[] = ['kode_akun' => '4.1.4.01', 'posisi' => 'kredit', 'jumlah' => round($total - $sumK, 2), 'saran_nama' => akun_nama_of($pdo, $skpdUser, '4.1.4.01')];
        }
    } else {
        // ---------- SP2D (Belanja) ----------
        // UP/GU/TU : D Kas di Bendahara Pengeluaran, K Kas di BUD
        // LS       : D Belanja (bruto) + K Utang Pajak/Potongan, K Kas di BUD (neto)
        $st = $pdo->prepare("SELECT sp2d.*, spp.jenis_spp, spp.id AS spp_id
                             FROM sp2d
                             LEFT JOIN spm ON spm.id = sp2d.spm_id
                             LEFT JOIN spp ON spp.id = spm.spp_id
                             WHERE sp2d.id = ? AND sp2d.skpd = ? AND sp2d.status = 'sudah_dicairkan' LIMIT 1");
        $st->execute([$id, $skpdUser]);
        $sp2d = $st->fetch();
        if (!$sp2d) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'SP2D tidak ditemukan / belum dicairkan.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $jumlah  = (float) $sp2d['jumlah'];
        $jenis   = (string) ($sp2d['jenis_spp'] ?? '');
        $tanggal = (string) ($sp2d['tanggal'] ?? '');
        $nomor   = (string) $sp2d['nomor_sp2d'];

        $j = strtolower($jenis);
        if (strpos($j, 'ls') !== false && strpos($j, 'gaji') !== false) {
            $kodeBlj = $BLJ_GAJI;
        } elseif (strpos($j, 'modal') !== false) {
            $kodeBlj = $BLJ_MODAL;
        } else {
            $kodeBlj = $BLJ_BJ;
        }
        $isLS = strpos($j, 'ls') !== false;
        $uraian = 'Pencairan ' . ($jenis !== '' ? $jenis : 'Belanja') . ' - SP2D No. ' . $nomor;


        if ($isLS) {
            // D Belanja (bruto)
            $rows[] = ['kode_akun' => $kodeBlj, 'posisi' => 'debet', 'jumlah' => $jumlah, 'saran_nama' => akun_nama_of($pdo, $skpdUser, $kodeBlj)];
            // K Utang Pajak / Utang Potongan (dari spp_potongan_pajak)
            $neto = $jumlah;
            if (!empty($sp2d['spp_id'])) {
                $pot = $pdo->prepare("SELECT jenis, nama, nilai FROM spp_potongan_pajak WHERE spp_id = ? AND nilai > 0 ORDER BY id ASC");
                $pot->execute([(int) $sp2d['spp_id']]);
                foreach ($pot->fetchAll() as $p) {
                    $kode = ($p['jenis'] === 'pajak') ? $UTANG_PAJAK : $UTANG_POTONGAN;
                    $rows[] = ['kode_akun' => $kode, 'posisi' => 'kredit', 'jumlah' => (float) $p['nilai'], 'saran_nama' => akun_nama_of($pdo, $skpdUser, $kode)];
                    $neto -= (float) $p['nilai'];
                }
            }
            // K Kas di BUD (neto)
            $rows[] = ['kode_akun' => $KAS_BUD, 'posisi' => 'kredit', 'jumlah' => round($neto, 2), 'saran_nama' => akun_nama_of($pdo, $skpdUser, $KAS_BUD)];
        } else {
            // UP / GU / TU: kas masuk ke Bendahara Pengeluaran
            $rows[] = ['kode_akun' => $KAS_PENGELUARAN, 'posisi' => 'debet', 'jumlah' => $jumlah, 'saran_nama' => akun_nama_of($pdo, $skpdUser, $KAS_PENGELUARAN)];
            $rows[] = ['kode_akun' => $KAS_BUD, 'posisi' => 'kredit', 'jumlah' => $jumlah, 'saran_nama' => akun_nama_of($pdo, $skpdUser, $KAS_BUD)];
        }
    }

    echo json_encode([
        'success'   => true,
        'sumber'    => $sumber,
        'sumber_id' => $id,
        'nomor'     => $nomor,
        'tanggal'   => $tanggal,
        'uraian'    => $uraian,
        'detail'    => $rows,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}


// ============================================================
// 9. Buku Besar - posting per akun (hanya jurnal sudah_approve)
//    Kolom mengikuti lembar kerja Modul 3: Tanggal, Keterangan,
//    Ref, Debet, Kredit, Saldo.
// ============================================================
if ($action === 'buku_besar') {
    $kode = trim((string) ($_GET['kode_akun'] ?? ''));
    $dari = tanggal_valid((string) ($_GET['dari'] ?? ''))  ? (string) $_GET['dari']  : '';
    $akhir = tanggal_valid((string) ($_GET['akhir'] ?? '')) ? (string) $_GET['akhir'] : '';
    if ($kode === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Pilih kode akun terlebih dahulu.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    akun_seed_default($pdo, $skpdUser);

    $nama = akun_nama_of($pdo, $skpdUser, $kode);
    $normal = 'debet';
    try {
        $q = $pdo->prepare("SELECT saldo_normal FROM akun_master WHERE skpd = ? AND kode_akun = ? LIMIT 1");
        $q->execute([$skpdUser, $kode]);
        $n = (string) ($q->fetchColumn() ?: '');
        if ($n !== '') $normal = $n;
    } catch (Throwable $e) {}

    // Saldo awal dari neraca_awal (bila tabel ada) - tahun diambil dari periode
    $saldoAwal = 0.0;
    try {
        $tahun = ($dari !== '') ? (int) substr($dari, 0, 4) : (int) date('Y');
        $q = $pdo->prepare("SELECT COALESCE(SUM(saldo), 0) FROM neraca_awal WHERE skpd = ? AND kode_akun = ? AND tahun = ?");
        $q->execute([$skpdUser, $kode, $tahun]);
        $saldoAwal = (float) $q->fetchColumn();
    } catch (Throwable $e) {}

    $sql = "SELECT j.nomor_jurnal, j.tanggal, j.uraian, j.sumber_tipe, d.posisi, d.jumlah
            FROM jurnal_umum_detail d
            INNER JOIN jurnal_umum j ON j.id = d.jurnal_id
            WHERE d.skpd = ? AND d.kode_akun = ? AND j.status = 'sudah_approve'";
    $params = [$skpdUser, $kode];
    if ($dari !== '')  { $sql .= " AND j.tanggal >= ?"; $params[] = $dari; }
    if ($akhir !== '') { $sql .= " AND j.tanggal <= ?"; $params[] = $akhir; }
    $sql .= " ORDER BY j.tanggal ASC, j.id ASC, d.id ASC LIMIT 1000";
    $st = $pdo->prepare($sql);
    $st->execute($params);

    $rows = []; $saldo = $saldoAwal; $totD = 0.0; $totK = 0.0;
    foreach ($st->fetchAll() as $r) {
        $d = ($r['posisi'] === 'debet') ? (float) $r['jumlah'] : 0.0;
        $k = ($r['posisi'] === 'kredit') ? (float) $r['jumlah'] : 0.0;
        $totD += $d; $totK += $k;
        $saldo += ($normal === 'debet') ? ($d - $k) : ($k - $d);
        $rows[] = [
            'nomor_jurnal' => (string) $r['nomor_jurnal'],
            'tanggal'      => (string) ($r['tanggal'] ?? ''),
            'uraian'       => (string) $r['uraian'],
            'ref'          => strtoupper((string) $r['sumber_tipe']),
            'debet'        => $d,
            'kredit'       => $k,
            'saldo'        => round($saldo, 2),
            'saldo_posisi' => $normal,
        ];
    }
    echo json_encode([
        'success'      => true,
        'kode_akun'    => $kode,
        'nama_akun'    => $nama,
        'saldo_normal' => $normal,
        'saldo_awal'   => $saldoAwal,
        'total_debet'  => round($totD, 2),
        'total_kredit' => round($totK, 2),
        'saldo_akhir'  => round($saldo, 2),
        'data'         => $rows,
        'total'        => count($rows),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}


// ============================================================
// 10. Neraca Saldo - mutasi per akun (hanya jurnal sudah_approve)
// ============================================================
if ($action === 'neraca_saldo') {
    akun_seed_default($pdo, $skpdUser);
    $dari  = tanggal_valid((string) ($_GET['dari'] ?? ''))  ? (string) $_GET['dari']  : '';
    $akhir = tanggal_valid((string) ($_GET['akhir'] ?? '')) ? (string) $_GET['akhir'] : '';

    // Mutasi dari jurnal approved
    $sql = "SELECT d.kode_akun,
                   SUM(CASE WHEN d.posisi = 'debet'  THEN d.jumlah ELSE 0 END) AS mutasi_debet,
                   SUM(CASE WHEN d.posisi = 'kredit' THEN d.jumlah ELSE 0 END) AS mutasi_kredit
            FROM jurnal_umum_detail d
            INNER JOIN jurnal_umum j ON j.id = d.jurnal_id
            WHERE d.skpd = ? AND j.status = 'sudah_approve'";
    $params = [$skpdUser];
    if ($dari !== '')  { $sql .= " AND j.tanggal >= ?"; $params[] = $dari; }
    if ($akhir !== '') { $sql .= " AND j.tanggal <= ?"; $params[] = $akhir; }
    $sql .= " GROUP BY d.kode_akun";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $mutasi = [];
    foreach ($st->fetchAll() as $r) {
        $mutasi[(string) $r['kode_akun']] = [
            'debet'  => (float) $r['mutasi_debet'],
            'kredit' => (float) $r['mutasi_kredit'],
        ];
    }


    // Saldo awal dari neraca_awal (bila tabel ada)
    $awal = [];
    try {
        $tahun = ($dari !== '') ? (int) substr($dari, 0, 4) : (int) date('Y');
        $q = $pdo->prepare("SELECT kode_akun, COALESCE(SUM(saldo), 0) AS saldo FROM neraca_awal WHERE skpd = ? AND tahun = ? GROUP BY kode_akun");
        $q->execute([$skpdUser, $tahun]);
        foreach ($q->fetchAll() as $r) {
            $awal[(string) $r['kode_akun']] = (float) $r['saldo'];
        }
    } catch (Throwable $e) {}

    $rows = [];
    $st = $pdo->prepare("SELECT kode_akun, nama_akun, tipe, saldo_normal FROM akun_master WHERE skpd = ? AND aktif = 1 ORDER BY kode_akun ASC");
    $st->execute([$skpdUser]);
    foreach ($st->fetchAll() as $a) {
        $kode = (string) $a['kode_akun'];
        $m = $mutasi[$kode] ?? ['debet' => 0.0, 'kredit' => 0.0];
        $sa = $awal[$kode] ?? 0.0;
        $akhirSaldo = ($a['saldo_normal'] === 'debet') ? ($sa + $m['debet'] - $m['kredit']) : ($sa + $m['kredit'] - $m['debet']);
        $rows[] = [
            'kode_akun'     => $kode,
            'nama_akun'     => (string) $a['nama_akun'],
            'tipe'          => (string) $a['tipe'],
            'saldo_normal'  => (string) $a['saldo_normal'],
            'saldo_awal'    => $sa,
            'mutasi_debet'  => round($m['debet'], 2),
            'mutasi_kredit' => round($m['kredit'], 2),
            'saldo_akhir'   => round($akhirSaldo, 2),
        ];
    }

    // Akun ber-mutasi tapi belum terdaftar di akun_master (mis. pendapatan dari akun_penerimaan)
    foreach ($mutasi as $kode => $m) {
        $exists = false;
        foreach ($rows as $r) { if ($r['kode_akun'] === $kode) { $exists = true; break; } }
        if (!$exists) {
            $rows[] = [
                'kode_akun'     => $kode,
                'nama_akun'     => akun_nama_of($pdo, $skpdUser, $kode),
                'tipe'          => 'pendapatan',
                'saldo_normal'  => ($m['kredit'] >= $m['debet'] ? 'kredit' : 'debet'),
                'saldo_awal'    => 0.0,
                'mutasi_debet'  => round($m['debet'], 2),
                'mutasi_kredit' => round($m['kredit'], 2),
                'saldo_akhir'   => round(abs($m['debet'] - $m['kredit']), 2),
            ];
        }
    }
    usort($rows, function ($x, $z) { return strcmp((string) $x['kode_akun'], (string) $z['kode_akun']); });

    echo json_encode(['success' => true, 'data' => $rows, 'total' => count($rows)], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// Fallback
// ============================================================
http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenal.'], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    // Jangan biarkan error database mematikan seluruh API.
    // Detail error HANYA ke log server - tidak dikirim ke client (keamanan).
    error_log('[AKUNTANSI] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Kesalahan pada server. Silakan coba beberapa saat lagi atau hubungi administrator.',
    ], JSON_UNESCAPED_UNICODE);
}

