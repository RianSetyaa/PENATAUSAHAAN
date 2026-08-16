<?php
/**
 * SIM-TKD - API Summary Dashboard
 * ============================================
 * Mengembalikan data ringkasan APBD dari database untuk dashboard frontend.
 * Wajib login (GET, respons JSON).
 *
 * Data yang dikembalikan:
 *   - user          : info pengguna dari sesi
 *   - tahun         : tahun anggaran aktif
 *   - summary       : total pagu, realisasi, persen, jumlah kegiatan
 *   - chart         : pagu & realisasi per tahun
 *   - kegiatan      : daftar kegiatan tahun berjalan
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    jsonResponse(false, 'Tidak terautentikasi.', [], 401);
}

$pdo    = db();
$tahun  = (int) date('Y');

// Ringkasan tahun berjalan
$stmt = $pdo->prepare("SELECT SUM(pagu) AS pagu, SUM(realisasi) AS realisasi, COUNT(*) AS jumlah FROM kegiatan WHERE tahun = ?");
$stmt->execute([$tahun]);
$row = $stmt->fetch();

$totalPagu      = (float) ($row['pagu'] ?? 0);
$totalRealisasi = (float) ($row['realisasi'] ?? 0);
$jumlahKegiatan = (int) ($row['jumlah'] ?? 0);
$persen         = $totalPagu > 0 ? round(($totalRealisasi / $totalPagu) * 100, 2) : 0;

// Tahun tersedia untuk grafik
$years = $pdo->query("SELECT DISTINCT tahun FROM kegiatan ORDER BY tahun DESC")->fetchAll(PDO::FETCH_COLUMN);
if (empty($years)) {
    $years = [$tahun];
}

$chart = [];
foreach ($years as $y) {
    $stmt = $pdo->prepare("SELECT SUM(pagu) AS pagu, SUM(realisasi) AS realisasi FROM kegiatan WHERE tahun = ?");
    $stmt->execute([$y]);
    $r = $stmt->fetch();
    $chart[$y] = [
        'pagu'      => (float) ($r['pagu'] ?? 0),
        'realisasi' => (float) ($r['realisasi'] ?? 0),
    ];
}

// Daftar kegiatan tahun berjalan
$stmt = $pdo->prepare("SELECT id, nama_kegiatan, tahun, pagu, realisasi, status FROM kegiatan WHERE tahun = ? ORDER BY pagu DESC LIMIT 10");
$stmt->execute([$tahun]);
$kegiatan = $stmt->fetchAll();

jsonResponse(true, 'OK', [
    'user' => [
        'nama'     => $_SESSION['nama'] ?? '',
        'username' => $_SESSION['username'] ?? '',
        'instansi' => $_SESSION['instansi'] ?? '',
        'peran'    => $_SESSION['peran'] ?? '',
    ],
    'tahun' => $tahun,
    'summary' => [
        'total_pagu'      => $totalPagu,
        'total_realisasi' => $totalRealisasi,
        'persen'          => $persen,
        'jumlah_kegiatan' => $jumlahKegiatan,
    ],
    'chart' => $chart,
    'kegiatan' => array_map(function ($k) {
        return [
            'id'          => (int) $k['id'],
            'nama_kegiatan'=> $k['nama_kegiatan'],
            'tahun'       => (int) $k['tahun'],
            'pagu'        => (float) $k['pagu'],
            'realisasi'   => (float) $k['realisasi'],
            'status'      => $k['status'],
        ];
    }, $kegiatan),
]);
