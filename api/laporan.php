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

if (!in_array($action, ['bku', 'rekap'], true)) {
    jsonResponse(false, 'Aksi tidak dikenali.', [], 422);
}

$dari   = input('dari', '');
$sampai = input('sampai', '');

if ($dari === '' || $sampai === '') {
    jsonResponse(false, 'Periode (tanggal awal dan akhir) wajib diisi.', [], 422);
}

// ============================================
// REKAPITULASI PENERIMAAN (Harian / Bulanan)
//   GET ?action=rekap&mode=harian|bulanan&dari=YYYY-MM-DD&sampai=YYYY-MM-DD
//   Baris = periode (tanggal/bulan); kolom = 3 kelompok PAD
//   (Pajak Daerah 4.1.1.x, Retribusi Daerah 4.1.2.x, Lain-Lain PAD 4.1.x lain).
//   Sumber: STBP (setoran penerimaan) — STS adalah penyetoran, bukan penerimaan.
// ============================================
if ($action === 'rekap') {
    $mode = input('mode', 'harian') === 'bulanan' ? 'bulanan' : 'harian';
    // Kunci periode: tanggal utk harian, tanggal 1 utk bulanan
    $keySql = $mode === 'bulanan' ? "DATE_FORMAT(t.tanggal, '%Y-%m-01')" : 'DATE(t.tanggal)';

    $sql = "SELECT {$keySql} AS kunci, t.akun_kode, t.akun_nama, SUM(t.jumlah) AS jumlah
            FROM stbp t
            WHERE t.status <> 'dihapus' AND t.tanggal BETWEEN ? AND ?" . ($skpd !== '' ? " AND t.skpd = ?" : "") . "
            GROUP BY kunci, t.akun_kode, t.akun_nama
            ORDER BY kunci ASC, t.akun_kode ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($skpd !== '' ? [$dari, $sampai, $skpd] : [$dari, $sampai]);

    $perKunci = []; // kunci periode => [kelompok => [[kode, nama, jumlah]], total]
    foreach ($stmt->fetchAll() as $r) {
        $kunci = (string) $r['kunci'];
        $kode  = (string) $r['akun_kode'];
        $jml   = (float) $r['jumlah'];

        if (preg_match('/^4\.1\.1\./', $kode))      $g = 0; // Pajak Daerah
        elseif (preg_match('/^4\.1\.2\./', $kode))  $g = 1; // Retribusi Daerah
        else                                        $g = 2; // Lain-Lain PAD

        if (!isset($perKunci[$kunci])) {
            $perKunci[$kunci] = ['periode' => $kunci, 'kelompok' => [[], [], []], 'total' => 0.0];
        }
        $perKunci[$kunci]['kelompok'][$g][] = [
            'kode'   => $kode,
            'nama'   => (string) $r['akun_nama'],
            'jumlah' => round($jml, 2),
        ];
        $perKunci[$kunci]['total'] += $jml;
    }
    ksort($perKunci);

    // Total per kelompok + grand total
    $totG = [0.0, 0.0, 0.0];
    $grand = 0.0;
    foreach ($perKunci as $k) {
        for ($i = 0; $i < 3; $i++) {
            foreach ($k['kelompok'][$i] as $b) $totG[$i] += $b['jumlah'];
        }
        $grand += $k['total'];
    }

    // Nama Kuasa/Pengguna Anggaran: dari STS terbaru dalam periode (blok identitas)
    $sqlK = "SELECT st.kuasa_pengguna_anggaran FROM sts st
             WHERE st.status = 'aktif' AND st.kuasa_pengguna_anggaran <> ''"
          . ($skpd !== '' ? " AND st.skpd = ?" : "")
          . " AND st.tanggal_sts BETWEEN ? AND ?
             ORDER BY st.tanggal_sts DESC, st.id DESC LIMIT 1";
    $stmtK = $pdo->prepare($sqlK);
    $stmtK->execute($skpd !== '' ? [$skpd, $dari, $sampai] : [$dari, $sampai]);
    $kuasa = (string) ($stmtK->fetchColumn() ?: '');

    jsonResponse(true, 'OK', [
        'periode'   => ['dari' => $dari, 'sampai' => $sampai],
        'mode'      => $mode,
        'skpd'      => $skpd,
        'rows'      => array_values($perKunci),
        'total_kelompok' => [round($totG[0], 2), round($totG[1], 2), round($totG[2], 2)],
        'total'     => round($grand, 2),
        'bendahara' => (string) ($_SESSION['nama'] ?? ''),
        'pengguna_anggaran' => $kuasa,
    ]);
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
