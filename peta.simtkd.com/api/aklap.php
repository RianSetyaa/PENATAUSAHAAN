<?php
/**
 * SIM-TKD (peta.simtkd.com) - API Modul AKLAP
 * ============================================
 * Menyediakan data asli dari database untuk modul AKLAP.
 * Dilindungi token API (tanpa form login).
 *
 *   GET api/aklap.php?token=XXX&action=jurnal      : daftar jurnal (STBP + STS)
 *   GET api/aklap.php?token=XXX&action=lra_rekap   : realisasi per akun (untuk LRA)
 *   GET api/aklap.php?token=XXX&action=rekap       : ringkasan jumlah data
 *
 * Token dapat dikirim via query (?token=) atau header Authorization: Bearer.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/api.php';

header('Content-Type: application/json; charset=utf-8');

$pdo    = db();
$action = (string) ($_GET['action'] ?? 'jurnal');

// ---------- Verifikasi token (per-user / multi-tenant) ----------
// Token = api_token milik pengguna di tabel users.
$token = (string) ($_GET['token'] ?? '');
if ($token === '') {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $auth, $m)) {
        $token = trim($m[1]);
    }
}
$stmtUser = $pdo->prepare("SELECT id, nama_lengkap, username, email, instansi, kota, provinsi, peran FROM users WHERE api_token = ? LIMIT 1");
$stmtUser->execute([$token]);
$user = $stmtUser->fetch();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token API tidak valid.'], JSON_UNESCAPED_UNICODE);
    exit;
}
// SKPD/instansi pemilik token -> pemisahan data antar dinas (multi-tenant)
$skpdUser = (string) ($user['instansi'] ?? '');

// ============================================
// 1. Jurnal Approve (STBP = Penerimaan, STS = Penyetoran)
// ============================================
if ($action === 'jurnal') {
    $rows = [];

    // STBP -> Penerimaan (hanya yang sudah divalidasi / tahap 3)
    $stSql    = "SELECT * FROM stbp WHERE status = 'sudah_divalidasi'";
    $stParams = [];
    if ($skpdUser !== '') { $stSql .= " AND skpd = ?"; $stParams[] = $skpdUser; }
    $stSql .= " ORDER BY tanggal ASC, id ASC";
    $st = $pdo->prepare($stSql);
    $st->execute($stParams);
    $st = $st->fetchAll();
    foreach ($st as $r) {
        $status = 'Belum Approve';
        if (in_array($r['status'], ['sudah_diverifikasi', 'sudah_diotorisasi', 'sudah_divalidasi'], true)) {
            $status = 'Sudah Approve';
        }
        $rows[] = [
            'no'        => 'JRN-' . ($r['nomor_stbp'] ?: 'STBP-' . $r['id']),
            'dok'       => $r['nomor_stbp'] ?: '',
            'tgl'       => $r['tanggal'] ?: '',
            'tglAkhir'  => $r['tanggal'] ?: '',
            'nilai'     => (float) ($r['jumlah'] ?? 0),
            'ket'       => $r['uraian'] ?: '',
            'status'    => $status,
            'transaksi' => 'Penerimaan',
            'sumber'    => 'stbp',
            'id'        => (int) $r['id'],
            'akun_kode' => $r['akun_kode'] ?: '',
            'akun_nama' => $r['akun_nama'] ?: '',
            'skpd'      => $r['skpd'] ?: '',
        ];
    }

    // STS -> Penyetoran
    $st2Sql    = "SELECT * FROM sts WHERE status = 'aktif'";
    $st2Params = [];
    if ($skpdUser !== '') { $st2Sql .= " AND skpd = ?"; $st2Params[] = $skpdUser; }
    $st2Sql .= " ORDER BY tanggal_sts ASC, id ASC";
    $st2 = $pdo->prepare($st2Sql);
    $st2->execute($st2Params);
    $st2 = $st2->fetchAll();
    foreach ($st2 as $r) {
        $rows[] = [
            'no'        => 'JRN-' . ($r['nomor_sts'] ?: 'STS-' . $r['id']),
            'dok'       => $r['nomor_sts'] ?: '',
            'tgl'       => $r['tanggal_sts'] ?: '',
            'tglAkhir'  => $r['tanggal_acuan_akhir'] ?: ($r['tanggal_sts'] ?: ''),
            'nilai'     => (float) ($r['total'] ?? 0),
            'ket'       => $r['keterangan'] ?: '',
            'status'    => 'Belum Approve',
            'transaksi' => 'Penyetoran',
            'sumber'    => 'sts',
            'id'        => (int) $r['id'],
            'akun_kode' => '',
            'akun_nama' => '',
            'skpd'      => $r['skpd'] ?: '',
        ];
    }

    // Urutkan berdasarkan tanggal lalu id
    usort($rows, function ($a, $b) {
        return strcmp($a['tgl'], $b['tgl']) ?: ($a['id'] <=> $b['id']);
    });

    echo json_encode([
        'success' => true,
        'data'    => $rows,
        'total'   => count($rows),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================
// 2. LRA - realisasi per akun (dari STBP) + pagu (dari kegiatan)
// ============================================
if ($action === 'lra_rekap') {
    $realisasiByAkun = [];
    $stSql    = "SELECT akun_kode, SUM(jumlah) AS total FROM stbp WHERE status != 'dihapus' AND akun_kode <> ''";
    $stParams = [];
    if ($skpdUser !== '') { $stSql .= " AND skpd = ?"; $stParams[] = $skpdUser; }
    $stSql .= " GROUP BY akun_kode";
    $st = $pdo->prepare($stSql);
    $st->execute($stParams);
    $st = $st->fetchAll();
    foreach ($st as $r) {
        $realisasiByAkun[$r['akun_kode']] = (float) $r['total'];
    }

    $totalPagu          = (float) $pdo->query("SELECT COALESCE(SUM(pagu),0) FROM kegiatan WHERE " . (($skpdUser !== '') ? ("skpd = " . $pdo->quote($skpdUser)) : '1=1'))->fetchColumn();
    $totalRealisasi     = (float) $pdo->query("SELECT COALESCE(SUM(realisasi),0) FROM kegiatan WHERE " . (($skpdUser !== '') ? ("skpd = " . $pdo->quote($skpdUser)) : '1=1'))->fetchColumn();
    $jumlahKegiatan     = (int)  $pdo->query("SELECT COUNT(*) FROM kegiatan WHERE " . (($skpdUser !== '') ? ("skpd = " . $pdo->quote($skpdUser)) : '1=1'))->fetchColumn();

    echo json_encode([
        'success'           => true,
        'realisasi_by_akun' => $realisasiByAkun,
        'total_pagu'        => $totalPagu,
        'total_realisasi'   => $totalRealisasi,
        'jumlah_kegiatan'   => $jumlahKegiatan,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================
// 2b. Profil akun AKLAP (dari data pendaftaran)
// ============================================
if ($action === 'profil') {
    echo json_encode(['success' => true, 'user' => [
        'nama'      => (string) ($user['nama_lengkap'] ?? ''),
        'username'  => (string) ($user['username'] ?? ''),
        'instansi'  => (string) ($user['instansi'] ?? ''),
        'kota'      => (string) ($user['kota'] ?? ''),
        'provinsi'  => (string) ($user['provinsi'] ?? ''),
        'peran'     => (string) ($user['peran'] ?? ''),
    ]], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================
// 3. Rekap ringkasan (untuk dashboard/index)
// ============================================
if ($action === 'rekap') {
    $skpdCond = ($skpdUser !== '') ? ("skpd = " . $pdo->quote($skpdUser)) : '1=1';
    $count = function (string $table, string $where = '1=1') use ($pdo): int {
        try {
            return (int) $pdo->query("SELECT COUNT(*) FROM {$table} WHERE {$where}")->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    };

    echo json_encode([
        'success' => true,
        'data'    => [
            'stbp'         => $count('stbp', "status != 'dihapus' AND {$skpdCond}"),
            'sts'          => $count('sts', "status = 'aktif' AND {$skpdCond}"),
            'permohonan'   => $count('permohonan', $skpdCond),
            'kegiatan'     => $count('kegiatan', $skpdCond),
            'akun'         => $count('akun_penerimaan', $skpdCond),
            'total_pagu'   => (float) $pdo->query("SELECT COALESCE(SUM(pagu),0) FROM kegiatan WHERE {$skpdCond}")->fetchColumn(),
            'total_penerimaan' => (float) $pdo->query("SELECT COALESCE(SUM(jumlah),0) FROM stbp WHERE status != 'dihapus' AND {$skpdCond}")->fetchColumn(),
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================
// Fallback
// ============================================
http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenal.'], JSON_UNESCAPED_UNICODE);
