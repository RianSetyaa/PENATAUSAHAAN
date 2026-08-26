<?php
/**
 * SIM-TKD - API Laporan
 * ============================================
 * Endpoint laporan (Penerimaan).
 *
 *   GET ?action=bku&dari=YYYY-MM-DD&sampai=YYYY-MM-DD
 *       : data Buku Kas Umum (BKU) Penerimaan Daerah (gabungan STBP + STS)
 *
 * Wajib login. Respons JSON.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    jsonResponse(false, 'Tidak terautentikasi.', [], 401);
}

$pdo  = db();
$skpd = requireInstansi(); // pemisahan data multi-dinas (fail-closed)

$action = input('action', 'bku');

if ($action !== 'bku') {
    jsonResponse(false, 'Aksi tidak dikenali.', [], 422);
}

$dari   = input('dari', '');
$sampai = input('sampai', '');

if ($dari === '' || $sampai === '') {
    jsonResponse(false, 'Periode (tanggal awal dan akhir) wajib diisi.', [], 422);
}

$entries = [];

// --- Penerimaan dari STBP ---
$stmt = $pdo->prepare("
    SELECT s.id, s.nomor_stbp AS no_bukti, s.tanggal, s.akun_kode, s.akun_nama,
           s.jumlah, s.uraian
    FROM stbp s
    WHERE s.status <> 'dihapus' AND s.tanggal BETWEEN ? AND ?" . ($skpd !== '' ? " AND s.skpd = ?" : "") . "
    ORDER BY s.tanggal ASC, s.id ASC
");
$stmt->execute($skpd !== '' ? [$dari, $sampai, $skpd] : [$dari, $sampai]);
foreach ($stmt->fetchAll() as $r) {
    $entries[] = [
        'tanggal'       => (string) $r['tanggal'],
        'no_bukti'      => (string) $r['no_bukti'],
        'kode_rekening' => trim((string) $r['akun_kode'] . ' - ' . (string) $r['akun_nama'], ' -'),
        'uraian'        => (string) $r['uraian'],
        'penerimaan'    => (float) $r['jumlah'],
        'pengeluaran'   => 0.0,
        'jenis'         => 'STBP',
    ];
}

// --- Setoran ke Kas Daerah dari STS (keluar dari kas SKPD -> Pengeluaran) ---
$stmt = $pdo->prepare("
    SELECT t.id, t.nomor_sts AS no_bukti, t.tanggal_sts AS tanggal,
           (SELECT d.akun_kode FROM sts_detail d WHERE d.sts_id = t.id ORDER BY d.id ASC LIMIT 1) AS akun_kode,
           (SELECT d.akun_nama FROM sts_detail d WHERE d.sts_id = t.id ORDER BY d.id ASC LIMIT 1) AS akun_nama,
           t.total AS jumlah, t.keterangan AS uraian
    FROM sts t
    WHERE t.status = 'aktif' AND t.tanggal_sts BETWEEN ? AND ?" . ($skpd !== '' ? " AND t.skpd = ?" : "") . "
    ORDER BY t.tanggal_sts ASC, t.id ASC
");
$stmt->execute($skpd !== '' ? [$dari, $sampai, $skpd] : [$dari, $sampai]);
foreach ($stmt->fetchAll() as $r) {
    $entries[] = [
        'tanggal'       => (string) $r['tanggal'],
        'no_bukti'      => (string) $r['no_bukti'],
        'kode_rekening' => trim((string) $r['akun_kode'] . ' - ' . (string) $r['akun_nama'], ' -'),
        'uraian'        => (string) $r['uraian'],
        'penerimaan'    => 0.0,
        'pengeluaran'   => (float) $r['jumlah'],
        'jenis'         => 'STS',
    ];
}

// Urutkan berdasarkan tanggal, lalu hitung saldo berjalan
usort($entries, function ($a, $b) {
    return strcmp($a['tanggal'], $b['tanggal']);
});

$saldo = 0.0;
$totalPenerimaan = 0.0;
$totalPengeluaran = 0.0;
foreach ($entries as &$e) {
    $saldo += $e['penerimaan'] - $e['pengeluaran'];
    $totalPenerimaan += $e['penerimaan'];
    $totalPengeluaran += $e['pengeluaran'];
    $e['saldo'] = round($saldo, 2);
}
unset($e);

jsonResponse(true, 'OK', [
    'periode' => ['dari' => $dari, 'sampai' => $sampai],
    'entries' => array_values($entries),
    'total_penerimaan' => round($totalPenerimaan, 2),
    'total_pengeluaran' => round($totalPengeluaran, 2),
    'saldo_akhir' => round($saldo, 2),
]);
