<?php
/**
 * SIM-TKD - API STS (Surat Tanda Setoran)
 * ============================================
 * Endpoint untuk pengelolaan STS (Penerimaan).
 *
 *   GET ?status=aktif&q=   : daftar STS + jumlah per status
 *   GET ?id=X              : detail STS (termasuk rincian STBP)
 *   POST action=create     : buat STS baru (berisi pilihan STBP)
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
$skpd = (string) ($_SESSION['instansi'] ?? ''); // pemisahan data multi-dinas

$STATUS_LIST = ['aktif', 'dihapus'];

// ============================================
// GET - Daftar / Detail STS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // --- Detail satu STS ---
    $detailId = (int) input('id', '0');
    if ($detailId > 0) {
        $stmt = $pdo->prepare("SELECT s.*, u.nama_lengkap AS dibuat_oleh
                               FROM sts s
                               LEFT JOIN users u ON u.id = s.user_id
                               WHERE s.id = ?" . ($skpd !== '' ? " AND s.skpd = ?" : ""));
        $stmt->execute($skpd !== '' ? [$detailId, $skpd] : [$detailId]);
        $row = $stmt->fetch();
        if (!$row) {
            jsonResponse(false, 'STS tidak ditemukan.', [], 404);
        }

        $stmtD = $pdo->prepare("SELECT * FROM sts_detail WHERE sts_id = ? ORDER BY id ASC");
        $stmtD->execute([$detailId]);
        $details = $stmtD->fetchAll();

        jsonResponse(true, 'OK', [
            'sts' => [
                'id'                 => (int) $row['id'],
                'skpd'               => (string) $row['skpd'],
                'nomor_sts'          => (string) $row['nomor_sts'],
                'nama_penyetor'      => (string) $row['nama_penyetor'],
                'tanggal_sts'        => (string) $row['tanggal_sts'],
                'tanggal_acuan_dari' => (string) $row['tanggal_acuan_dari'],
                'tanggal_acuan_akhir'=> (string) $row['tanggal_acuan_akhir'],
                'mengetahui'         => (string) $row['mengetahui'],
                'nama_bank'          => (string) $row['nama_bank'],
                'nomor_rekening'     => (string) $row['nomor_rekening'],
                'nama_rekening'      => (string) $row['nama_rekening'],
                'keterangan'         => (string) $row['keterangan'],
                'total'              => (float) $row['total'],
                'status'             => (string) $row['status'],
                'dibuat_oleh'        => (string) ($row['dibuat_oleh'] ?? ''),
                'created_at'         => (string) $row['created_at'],
            ],
            'detail' => array_map(function ($d) {
                return [
                    'id'         => (int) $d['id'],
                    'stbp_id'    => (int) $d['stbp_id'],
                    'nomor_stbp' => (string) $d['nomor_stbp'],
                    'tanggal'    => (string) $d['tanggal'],
                    'akun_kode'  => (string) $d['akun_kode'],
                    'akun_nama'  => (string) $d['akun_nama'],
                    'jumlah'     => (float) $d['jumlah'],
                    'uraian'     => (string) $d['uraian'],
                ];
            }, $details),
        ]);
    }

    // --- Daftar STS ---
    $q      = input('q');
    $status = input('status', 'aktif');

    if (!in_array($status, $STATUS_LIST, true)) {
        $status = 'aktif';
    }

    $counts = [];
    foreach ($STATUS_LIST as $st) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sts WHERE status = ?" . ($skpd !== '' ? " AND skpd = ?" : ""));
        $stmt->execute($skpd !== '' ? [$st, $skpd] : [$st]);
        $counts[$st] = (int) $stmt->fetchColumn();
    }

    $sql = "SELECT s.*, u.nama_lengkap AS dibuat_oleh
            FROM sts s
            LEFT JOIN users u ON u.id = s.user_id
            WHERE s.status = ?" . ($skpd !== '' ? " AND s.skpd = ?" : "");
    $params = $skpd !== '' ? [$status, $skpd] : [$status];

    if ($q !== '') {
        $sql   .= " AND (s.nomor_sts LIKE ? OR s.nama_penyetor LIKE ? OR s.nama_bank LIKE ? OR s.keterangan LIKE ? OR s.skpd LIKE ?)";
        $like   = '%' . $q . '%';
        $params = array_merge($params, [$like, $like, $like, $like, $like]);
    }

    $sql .= " ORDER BY s.tanggal_sts DESC, s.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    jsonResponse(true, 'OK', [
        'status'      => $status,
        'status_list' => $STATUS_LIST,
        'counts'      => $counts,
        'data' => array_map(function ($r) {
            return [
                'id'            => (int) $r['id'],
                'skpd'          => (string) $r['skpd'],
                'nomor_sts'     => (string) $r['nomor_sts'],
                'nama_penyetor' => (string) $r['nama_penyetor'],
                'tanggal_sts'   => (string) $r['tanggal_sts'],
                'nama_bank'     => (string) $r['nama_bank'],
                'nomor_rekening'=> (string) $r['nomor_rekening'],
                'nama_rekening' => (string) $r['nama_rekening'],
                'keterangan'    => (string) $r['keterangan'],
                'total'         => (float) $r['total'],
                'status'        => (string) $r['status'],
                'dibuat_oleh'   => (string) ($r['dibuat_oleh'] ?? ''),
                'created_at'    => (string) $r['created_at'],
            ];
        }, $rows),
    ]);
}

// ============================================
// POST - Buat STS baru
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        $body = $_POST;
    }
    $action = $body['action'] ?? 'create';

    if ($action !== 'create') {
        jsonResponse(false, 'Aksi tidak dikenali.', [], 422);
    }

    $nomor        = trim((string) ($body['nomor_sts'] ?? ''));
    $nama_penyetor = trim((string) ($body['nama_penyetor'] ?? ''));
    $tanggal_sts  = trim((string) ($body['tanggal_sts'] ?? ''));
    $tanggal_dari = trim((string) ($body['tanggal_acuan_dari'] ?? ''));
    $tanggal_akhir= trim((string) ($body['tanggal_acuan_akhir'] ?? ''));
    $mengetahui   = trim((string) ($body['mengetahui'] ?? ''));
    $nama_bank    = trim((string) ($body['nama_bank'] ?? ''));
    $nomor_rek    = trim((string) ($body['nomor_rekening'] ?? ''));
    $nama_rek     = trim((string) ($body['nama_rekening'] ?? ''));
    $keterangan   = trim((string) ($body['keterangan'] ?? ''));
    $skpd         = trim((string) ($body['skpd'] ?? $_SESSION['instansi'] ?? ''));
    $stbp_ids     = is_array($body['stbp_ids'] ?? null) ? array_map('intval', $body['stbp_ids']) : [];

    // Auto-generate nomor STS jika tidak diisi
    if ($nomor === '') {
        $nomor = 'STS-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
    }

    if ($tanggal_sts === '') {
        jsonResponse(false, 'Tanggal STS wajib diisi.', [], 422);
    }
    if (count($stbp_ids) === 0) {
        jsonResponse(false, 'Pilih minimal satu STBP.', [], 422);
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO sts (user_id, skpd, nomor_sts, nama_penyetor, tanggal_sts,
                             tanggal_acuan_dari, tanggal_acuan_akhir, mengetahui,
                             nama_bank, nomor_rekening, nama_rekening, keterangan, total, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'aktif')
        ");
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $skpd,
            $nomor,
            $nama_penyetor,
            $tanggal_sts,
            $tanggal_dari !== '' ? $tanggal_dari : null,
            $tanggal_akhir !== '' ? $tanggal_akhir : null,
            $mengetahui,
            $nama_bank,
            $nomor_rek,
            $nama_rek,
            $keterangan,
        ]);
        $stsId = $pdo->lastInsertId();

        // Simpan rincian STBP
        $stmtD = $pdo->prepare("
            INSERT INTO sts_detail (sts_id, stbp_id, nomor_stbp, tanggal, akun_kode, akun_nama, jumlah, uraian)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtS = $pdo->prepare("SELECT id, nomor_stbp, tanggal, akun_kode, akun_nama, jumlah, uraian FROM stbp WHERE id = ?");
        $total = 0.0;
        foreach ($stbp_ids as $sid) {
            $stmtS->execute([$sid]);
            $sb = $stmtS->fetch();
            if (!$sb) continue;
            $stmtD->execute([
                $stsId,
                (int) $sb['id'],
                (string) $sb['nomor_stbp'],
                (string) $sb['tanggal'],
                (string) $sb['akun_kode'],
                (string) $sb['akun_nama'],
                (float) $sb['jumlah'],
                (string) $sb['uraian'],
            ]);
            $total += (float) $sb['jumlah'];
        }

        $pdo->prepare("UPDATE sts SET total = ? WHERE id = ?")->execute([$total, $stsId]);

        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Gagal menyimpan STS.', [], 500);
    }

    jsonResponse(true, 'STS berhasil dibuat.', [
        'id'    => $stsId,
        'nomor' => $nomor,
    ], 201);
}

jsonResponse(false, 'Metode request tidak diizinkan.', [], 405);
