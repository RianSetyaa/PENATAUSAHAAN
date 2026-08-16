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

// ---------- Verifikasi token ----------
$token = (string) ($_GET['token'] ?? '');
if ($token === '') {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $auth, $m)) {
        $token = trim($m[1]);
    }
}
if (!hash_equals(API_TOKEN, $token)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token API tidak valid.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo    = db();
$action = (string) ($_GET['action'] ?? 'jurnal');

// ============================================
// 1. Jurnal Approve (STBP = Penerimaan, STS = Penyetoran)
// ============================================
if ($action === 'jurnal') {
    $rows = [];

    // STBP -> Penerimaan
    $st = $pdo->query(
        "SELECT * FROM stbp WHERE status != 'dihapus' ORDER BY tanggal ASC, id ASC"
    )->fetchAll();
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
    $st2 = $pdo->query(
        "SELECT * FROM sts WHERE status = 'aktif' ORDER BY tanggal_sts ASC, id ASC"
    )->fetchAll();
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
    $st = $pdo->query(
        "SELECT akun_kode, SUM(jumlah) AS total FROM stbp WHERE status != 'dihapus' AND akun_kode <> '' GROUP BY akun_kode"
    )->fetchAll();
    foreach ($st as $r) {
        $realisasiByAkun[$r['akun_kode']] = (float) $r['total'];
    }

    $totalPagu          = (float) $pdo->query("SELECT COALESCE(SUM(pagu),0) FROM kegiatan")->fetchColumn();
    $totalRealisasi     = (float) $pdo->query("SELECT COALESCE(SUM(realisasi),0) FROM kegiatan")->fetchColumn();
    $jumlahKegiatan     = (int)  $pdo->query("SELECT COUNT(*) FROM kegiatan")->fetchColumn();

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
// 3. Rekap ringkasan (untuk dashboard/index)
// ============================================
if ($action === 'rekap') {
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
            'stbp'         => $count('stbp', "status != 'dihapus'"),
            'sts'          => $count('sts', "status = 'aktif'"),
            'permohonan'   => $count('permohonan'),
            'kegiatan'     => $count('kegiatan'),
            'akun'         => $count('akun_penerimaan'),
            'total_pagu'   => (float) $pdo->query("SELECT COALESCE(SUM(pagu),0) FROM kegiatan")->fetchColumn(),
            'total_penerimaan' => (float) $pdo->query("SELECT COALESCE(SUM(jumlah),0) FROM stbp WHERE status != 'dihapus'")->fetchColumn(),
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================
// Fallback
// ============================================
http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenal.'], JSON_UNESCAPED_UNICODE);
