<?php
/**
 * SIM-TKD - API STBP (Surat Tanda Bukti Penerimaan)
 * ============================================
 * Endpoint untuk pembuatan STBP (Penerimaan).
 *
 *   GET ?status=belum_diverifikasi&q=   : daftar STBP + jumlah per status
 *   POST action=create                  : buat STBP baru
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

$STATUS_LIST = ['belum_diverifikasi', 'sudah_diverifikasi', 'sudah_diotorisasi', 'sudah_divalidasi', 'dihapus'];

// ============================================
// GET - Daftar STBP
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // --- Detail satu STBP (untuk halaman cetak) ---
    $detailId = (int) input('id', '0');
    if ($detailId > 0) {
        $stmt = $pdo->prepare("SELECT s.*, u.nama_lengkap AS dibuat_oleh
                               FROM stbp s
                               LEFT JOIN users u ON u.id = s.user_id
                               WHERE s.id = ?" . ($skpd !== '' ? " AND s.skpd = ?" : ""));
        $stmt->execute($skpd !== '' ? [$detailId, $skpd] : [$detailId]);
        $row = $stmt->fetch();
        if (!$row) {
            jsonResponse(false, 'STBP tidak ditemukan.', [], 404);
        }

        $stmtPay = $pdo->prepare("SELECT * FROM stbp_pembayaran WHERE stbp_id = ? ORDER BY id ASC LIMIT 1");
        $stmtPay->execute([$detailId]);
        $pay = $stmtPay->fetch() ?: [];

        $stmtPend = $pdo->prepare("SELECT * FROM stbp_pendapatan WHERE stbp_id = ? ORDER BY id ASC");
        $stmtPend->execute([$detailId]);
        $pends = $stmtPend->fetchAll();

        jsonResponse(true, 'OK', [
            'stbp' => [
                'id'          => (int) $row['id'],
                'skpd'        => (string) $row['skpd'],
                'nomor_stbp'  => (string) $row['nomor_stbp'],
                'tanggal'     => (string) $row['tanggal'],
                'akun_kode'   => (string) $row['akun_kode'],
                'akun_nama'   => (string) $row['akun_nama'],
                'jumlah'      => (float) $row['jumlah'],
                'uraian'      => (string) $row['uraian'],
                'status'      => (string) $row['status'],
                'dibuat_oleh' => (string) ($row['dibuat_oleh'] ?? ''),
                'created_at'  => (string) $row['created_at'],
            ],
            'pembayaran' => [
                'metode'         => (string) ($pay['metode_penyetoran'] ?? ''),
                'nama_penyetor'  => (string) ($pay['nama_penyetor'] ?? ''),
                'bank'           => (string) ($pay['nama_bank'] ?? ''),
                'nomor_rekening' => (string) ($pay['nomor_rekening'] ?? ''),
            ],
            'pendapatan' => array_map(function ($p) {
                return [
                    'akun_kode'     => (string) $p['akun_kode'],
                    'akun_nama'     => (string) $p['akun_nama'],
                    'rekening_kode' => (string) $p['rekening_bank'],
                    'rekening_nama' => (string) $p['rekening_nama'],
                    'nominal'       => (float) $p['nominal'],
                ];
            }, $pends),
        ]);
    }

    $q      = input('q');
    $status = input('status', 'belum_diverifikasi');

    // Status khusus: STBP yang SIAP dimasukkan ke STS & jurnal (SUDAH melalui 3 tahap: verifikasi, otorisasi, validasi)
    $STS_ELIGIBLE = ['sudah_divalidasi'];
    $isStsReady = ($status === 'siap_sts');

    if (!$isStsReady && !in_array($status, $STATUS_LIST, true)) {
        $status = 'belum_diverifikasi';
    }

    // Jumlah per status (untuk tab)
    $counts = [];
    foreach ($STATUS_LIST as $st) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM stbp WHERE status = ?" . ($skpd !== '' ? " AND skpd = ?" : ""));
        $stmt->execute($skpd !== '' ? [$st, $skpd] : [$st]);
        $counts[$st] = (int) $stmt->fetchColumn();
    }

    $statusCond = $isStsReady
        ? "s.status IN ('sudah_diotorisasi','sudah_divalidasi')"
        : "s.status = ?";
    $sql = "SELECT s.*, u.nama_lengkap AS dibuat_oleh,
                   sp.metode_penyetoran AS metode_penyetoran,
                   sp.nama_penyetor     AS nama_penyetor,
                   sp.nama_bank         AS nama_bank,
                   sp.nomor_rekening    AS nomor_rekening
            FROM stbp s
            LEFT JOIN users u ON u.id = s.user_id
            LEFT JOIN stbp_pembayaran sp ON sp.stbp_id = s.id
            WHERE $statusCond" . ($skpd !== '' ? " AND s.skpd = ?" : "");
    $params = [];
    if (!$isStsReady) $params[] = $status;
    if ($skpd !== '') $params[] = $skpd;

    if ($q !== '') {
        $sql   .= " AND (s.nomor_stbp LIKE ? OR s.akun_nama LIKE ? OR s.uraian LIKE ? OR s.skpd LIKE ?)";
        $like   = '%' . $q . '%';
        $params = array_merge($params, [$like, $like, $like, $like]);
    }

    $sql .= " ORDER BY s.tanggal DESC, s.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    jsonResponse(true, 'OK', [
        'status'      => $status,
        'status_list' => $STATUS_LIST,
        'counts'      => $counts,
        'data' => array_map(function ($r) {
            return [
                'id'         => (int) $r['id'],
                'skpd'       => (string) $r['skpd'],
                'nomor_stbp' => (string) $r['nomor_stbp'],
                'tanggal'    => (string) $r['tanggal'],
                'akun_kode'  => (string) $r['akun_kode'],
                'akun_nama'  => (string) $r['akun_nama'],
                'jumlah'     => (float) $r['jumlah'],
                'uraian'     => (string) $r['uraian'],
                'status'     => (string) $r['status'],
                'metode_penyetoran' => (string) ($r['metode_penyetoran'] ?? ''),
                'nama_penyetor'     => (string) ($r['nama_penyetor'] ?? ''),
                'bank'              => (string) ($r['nama_bank'] ?? ''),
                'nomor_rekening'    => (string) ($r['nomor_rekening'] ?? ''),
                'dibuat_oleh'=> (string) ($r['dibuat_oleh'] ?? ''),
                'created_at' => (string) $r['created_at'],
            ];
        }, $rows),
    ]);
}

// ============================================
// POST - Buat STBP baru
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        $body = $_POST;
    }
    $action = $body['action'] ?? 'create';

    // ---------- 0. Majukan tahap status STBP (verifikasi -> otorisasi -> validasi) ----------
    if ($action === 'update_status') {
        $id     = (int) ($body['id'] ?? 0);
        $target = (string) ($body['status'] ?? '');
        $skpdS  = $skpd; // fail-closed di atas

        if ($id <= 0) {
            jsonResponse(false, 'ID tidak valid.', [], 422);
        }

        $STAGES = ['belum_diverifikasi', 'sudah_diverifikasi', 'sudah_diotorisasi', 'sudah_divalidasi'];
        if (!in_array($target, $STAGES, true)) {
            jsonResponse(false, 'Status tahap tidak valid.', [], 422);
        }

        // Ambil status saat ini (hanya milik instansi yang sama)
        $sel = $pdo->prepare("SELECT status FROM stbp WHERE id = ?" . ($skpdS !== '' ? " AND skpd = ?" : ""));
        $sel->execute($skpdS !== '' ? [$id, $skpdS] : [$id]);
        $cur = $sel->fetchColumn();
        if ($cur === false) {
            jsonResponse(false, 'STBP tidak ditemukan atau bukan milik instansi Anda.', [], 404);
        }

        // Hanya boleh maju SATU tahap secara berurutan
        $curIdx = array_search((string) $cur, $STAGES, true);
        $tgtIdx = array_search($target, $STAGES, true);
        if ($tgtIdx !== $curIdx + 1) {
            jsonResponse(false, 'Tahap tidak boleh melompat. Majukan status secara berurutan.', [], 422);
        }

        $upd = $pdo->prepare("UPDATE stbp SET status = ? WHERE id = ?" . ($skpdS !== '' ? " AND skpd = ?" : ""));
        $upd->execute($skpdS !== '' ? [$target, $id, $skpdS] : [$target, $id]);
        jsonResponse(true, 'Status STBP berhasil dimajukan ke tahap berikutnya.', ['id' => $id, 'status' => $target]);
    }

    if ($action !== 'create') {
        jsonResponse(false, 'Aksi tidak dikenali.', [], 422);
    }

    $nomor  = trim((string) ($body['nomor_stbp'] ?? ''));
    $tanggal = trim((string) ($body['tanggal'] ?? ''));
    $kode   = trim((string) ($body['akun_kode'] ?? ''));
    $nama   = trim((string) ($body['akun_nama'] ?? ''));
    $jumlah = (float) ($body['jumlah'] ?? 0);
    $uraian = trim((string) ($body['uraian'] ?? ''));
    // skpd selalu dari sesi (jangan percaya input klien)
    $skpd   = requireInstansi();
    $payments  = is_array($body['data_pembayaran'] ?? null) ? $body['data_pembayaran'] : [];
    $pendapatan = is_array($body['data_pendapatan'] ?? null) ? $body['data_pendapatan'] : [];

    // Auto-generate nomor STBP jika tidak diisi
    if ($nomor === '') {
        $nomor = 'STBP-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
    }

    if ($tanggal === '') {
        jsonResponse(false, 'Tanggal wajib diisi.', ['field' => 'tanggal'], 422);
    }
    if ($jumlah <= 0) {
        jsonResponse(false, 'Jumlah penerimaan harus lebih dari 0.', ['field' => 'jumlah'], 422);
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO stbp (user_id, skpd, nomor_stbp, tanggal, akun_kode, akun_nama, jumlah, uraian, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'belum_diverifikasi')
        ");
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $skpd,
            $nomor,
            $tanggal,
            $kode,
            $nama,
            $jumlah,
            $uraian,
        ]);
        $stbpId = (int) $pdo->lastInsertId();

        // Simpan data pembayaran (jika ada)
        if (count($payments) > 0) {
            $stmtPay = $pdo->prepare("
                INSERT INTO stbp_pembayaran (stbp_id, metode_penyetoran, nama_penyetor, nama_bank, nomor_rekening)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($payments as $p) {
                $stmtPay->execute([
                    $stbpId,
                    trim((string) ($p['metode'] ?? 'non_tunai')),
                    trim((string) ($p['nama_penyetor'] ?? '')),
                    trim((string) ($p['bank'] ?? '')),
                    trim((string) ($p['nomor_rekening'] ?? '')),
                ]);
            }
        }

        // Simpan data pendapatan (baris dari modal "Tambahkan Data")
        if (count($pendapatan) > 0) {
            $stmtPend = $pdo->prepare("
                INSERT INTO stbp_pendapatan (stbp_id, akun_kode, akun_nama, rekening_bank, rekening_nama, rekening_nomor, nominal)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($pendapatan as $p) {
                $stmtPend->execute([
                    $stbpId,
                    trim((string) ($p['akun_kode'] ?? '')),
                    trim((string) ($p['akun_nama'] ?? '')),
                    trim((string) ($p['rekening_bank'] ?? '')),
                    trim((string) ($p['rekening_nama'] ?? '')),
                    trim((string) ($p['rekening_nomor'] ?? '')),
                    (float) ($p['nominal'] ?? 0),
                ]);
            }
        }

        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Gagal menyimpan STBP.', [], 500);
    }

    jsonResponse(true, 'STBP berhasil dibuat.', [
        'id'    => $stbpId,
        'nomor' => $nomor,
    ], 201);
}

jsonResponse(false, 'Metode request tidak diizinkan.', [], 405);
