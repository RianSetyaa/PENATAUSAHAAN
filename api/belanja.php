<?php
/**
 * SIM-TKD - API Modul Belanja (Penatausahaan Pengeluaran/Pembiayaan)
 * ============================================
 * Mengikuti manual book SIPD: Rekanan -> SPD -> SPP -> SPM -> SP2D -> BKU.
 * Multi-tenant: data dibatasi berdasarkan $_SESSION['instansi'].
 *
 *   GET  ?action=summary|rekanan_list|spd_list|spp_list|spm_list|sp2d_list
 *   POST {action, ...}  -> create / otorisasi / verifikasi / persetujuan / pencairan
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    jsonResponse(false, 'Tidak terautentikasi.', [], 401);
}

$pdo  = db();
$skpd = (string) ($_SESSION['instansi'] ?? '');
$method = $_SERVER['REQUEST_METHOD'];

// Helper nomor dokumen otomatis
function genNomor(string $prefix, PDO $pdo, string $table): string {
    $suffix = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
    return $prefix . '-' . date('Ymd') . '-' . $suffix;
}
function skpdCond(string $alias, string $skpd): array {
    if ($skpd === '') return ['', []];
    return [" AND {$alias}.skpd = ?", [$skpd]];
}

// ============================================
// GET - daftar / ringkasan
// ============================================
if ($method === 'GET') {
    $action = (string) ($_GET['action'] ?? 'summary');

    if ($action === 'summary') {
        $q = function (string $sql, array $p = []) use ($pdo): int {
            try { $s = $pdo->prepare($sql); $s->execute($p); return (int) $s->fetchColumn(); }
            catch (Throwable $e) { return 0; }
        };
        $sum = function (string $sql, array $p = []) use ($pdo): float {
            try { $s = $pdo->prepare($sql); $s->execute($p); return (float) $s->fetchColumn(); }
            catch (Throwable $e) { return 0; }
        };
        $cr = skpdCond('r', $skpd); // rekanan
        $cs = skpdCond('s', $skpd); // spd/spp/spm/sp2d
        jsonResponse(true, 'OK', [
            'rekanan'   => $q("SELECT COUNT(*) FROM rekanan r WHERE 1=1{$cr[0]}", $cr[1]),
            'spd'       => $q("SELECT COUNT(*) FROM spd s WHERE 1=1{$cs[0]}", $cs[1]),
            'spd_otor'  => $q("SELECT COUNT(*) FROM spd s WHERE s.status='sudah_otorisasi'{$cs[0]}", $cs[1]),
            'spp'       => $q("SELECT COUNT(*) FROM spp s WHERE 1=1{$cs[0]}", $cs[1]),
            'spp_ver'   => $q("SELECT COUNT(*) FROM spp s WHERE s.status='sudah_diverifikasi'{$cs[0]}", $cs[1]),
            'spm'       => $q("SELECT COUNT(*) FROM spm s WHERE 1=1{$cs[0]}", $cs[1]),
            'sp2d'      => $q("SELECT COUNT(*) FROM sp2d s WHERE 1=1{$cs[0]}", $cs[1]),
            'sp2d_cair' => $q("SELECT COUNT(*) FROM sp2d s WHERE s.status='sudah_dicairkan'{$cs[0]}", $cs[1]),
            'total_spd' => $sum("SELECT COALESCE(SUM(s.jumlah),0) FROM spd s WHERE 1=1{$cs[0]}", $cs[1]),
            'total_spp' => $sum("SELECT COALESCE(SUM(s.jumlah),0) FROM spp s WHERE 1=1{$cs[0]}", $cs[1]),
        ]);
    }

    if ($action === 'rekanan_list') {
        $c = skpdCond('r', $skpd);
        $stmt = $pdo->prepare("SELECT r.* FROM rekanan r WHERE 1=1{$c[0]} ORDER BY r.id DESC");
        $stmt->execute($c[1]);
        jsonResponse(true, 'OK', ['data' => $stmt->fetchAll()]);
    }

    if ($action === 'spd_list') {
        $status = (string) ($_GET['status'] ?? '');
        $c = skpdCond('s', $skpd);
        $sql = "SELECT s.* FROM spd s WHERE 1=1{$c[0]}";
        if ($status !== '' && $status !== 'semua') { $sql .= " AND s.status = ?"; $c[1][] = $status; }
        $sql .= " ORDER BY s.tanggal DESC, s.id DESC";
        $stmt = $pdo->prepare($sql); $stmt->execute($c[1]);
        jsonResponse(true, 'OK', ['data' => $stmt->fetchAll()]);
    }

    if ($action === 'spp_list') {
        $status = (string) ($_GET['status'] ?? '');
        $c = skpdCond('s', $skpd);
        $sql = "SELECT s.*, d.nomor_spd, r.nama_rekanan FROM spp s
                LEFT JOIN spd d ON d.id = s.spd_id
                LEFT JOIN rekanan r ON r.id = s.rekanan_id
                WHERE 1=1{$c[0]}";
        if ($status !== '' && $status !== 'semua') { $sql .= " AND s.status = ?"; $c[1][] = $status; }
        $sql .= " ORDER BY s.tanggal DESC, s.id DESC";
        $stmt = $pdo->prepare($sql); $stmt->execute($c[1]);
        jsonResponse(true, 'OK', ['data' => $stmt->fetchAll()]);
    }

    if ($action === 'spm_list') {
        $status = (string) ($_GET['status'] ?? '');
        $c = skpdCond('s', $skpd);
        $sql = "SELECT s.*, p.nomor_spp FROM spm s
                LEFT JOIN spp p ON p.id = s.spp_id
                WHERE 1=1{$c[0]}";
        if ($status !== '' && $status !== 'semua') { $sql .= " AND s.status = ?"; $c[1][] = $status; }
        $sql .= " ORDER BY s.tanggal DESC, s.id DESC";
        $stmt = $pdo->prepare($sql); $stmt->execute($c[1]);
        jsonResponse(true, 'OK', ['data' => $stmt->fetchAll()]);
    }

    if ($action === 'sp2d_list') {
        $status = (string) ($_GET['status'] ?? '');
        $c = skpdCond('s', $skpd);
        $sql = "SELECT s.*, m.nomor_spm FROM sp2d s
                LEFT JOIN spm m ON m.id = s.spm_id
                WHERE 1=1{$c[0]}";
        if ($status !== '' && $status !== 'semua') { $sql .= " AND s.status = ?"; $c[1][] = $status; }
        $sql .= " ORDER BY s.tanggal DESC, s.id DESC";
        $stmt = $pdo->prepare($sql); $stmt->execute($c[1]);
        jsonResponse(true, 'OK', ['data' => $stmt->fetchAll()]);
    }

    // Referensi SPD terotorisasi utk SPP & SPM terverifikasi utk SP2D
    if ($action === 'spd_otor_list') {
        $c = skpdCond('s', $skpd);
        $stmt = $pdo->prepare("SELECT s.* FROM spd s WHERE s.status='sudah_otorisasi'{$c[0]} ORDER BY s.tanggal DESC, s.id DESC");
        $stmt->execute($c[1]);
        jsonResponse(true, 'OK', ['data' => $stmt->fetchAll()]);
    }
    if ($action === 'spm_ver_list') {
        $c = skpdCond('s', $skpd);
        $stmt = $pdo->prepare("SELECT s.* FROM spm s WHERE s.status='sudah_diverifikasi'{$c[0]} ORDER BY s.tanggal DESC, s.id DESC");
        $stmt->execute($c[1]);
        jsonResponse(true, 'OK', ['data' => $stmt->fetchAll()]);
    }

    // ---------- BKU Belanja (SP2D yang sudah dicairkan, sesuai periode) ----------
    if ($action === 'bku_belanja') {
        $dari   = trim((string) ($_GET['dari'] ?? ''));
        $sampai = trim((string) ($_GET['sampai'] ?? ''));
        $c = skpdCond('s', $skpd);
        $sql = "SELECT s.*, m.nomor_spm FROM sp2d s
                LEFT JOIN spm m ON m.id = s.spm_id
                WHERE s.status='sudah_dicairkan'{$c[0]}";
        if ($dari !== '') { $sql .= " AND s.tanggal >= ?"; $c[1][] = $dari; }
        if ($sampai !== '') { $sql .= " AND s.tanggal <= ?"; $c[1][] = $sampai; }
        $sql .= " ORDER BY s.tanggal ASC, s.id ASC";
        $stmt = $pdo->prepare($sql); $stmt->execute($c[1]);
        $rows = $stmt->fetchAll();
        $total = 0.0;
        foreach ($rows as $r) $total += (float) $r['jumlah'];
        jsonResponse(true, 'OK', ['data' => $rows, 'total' => $total]);
    }

    jsonResponse(false, 'Aksi tidak dikenali.', [], 422);
}

// ============================================
// POST - mutasi (create / workflow)
// ============================================
if ($method === 'POST') {
    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) $body = $_POST;
    $action = (string) ($body['action'] ?? '');

    // ---------- Rekanan ----------
    if ($action === 'rekanan_create') {
        $nama   = trim((string) ($body['nama_rekanan'] ?? ''));
        $npwp   = trim((string) ($body['npwp'] ?? ''));
        $alamat = trim((string) ($body['alamat'] ?? ''));
        $bank   = trim((string) ($body['bank'] ?? ''));
        $noRek  = trim((string) ($body['nomor_rekening'] ?? ''));
        $nmRek  = trim((string) ($body['nama_rekening'] ?? ''));
        $jenis  = ($body['jenis'] === 'perseorangan') ? 'perseorangan' : 'perusahaan';
        if ($nama === '') jsonResponse(false, 'Nama rekanan wajib diisi.', ['field' => 'nama_rekanan'], 422);
        $stmt = $pdo->prepare("INSERT INTO rekanan (user_id, skpd, nama_rekanan, npwp, alamat, bank, nomor_rekening, nama_rekening, jenis) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$_SESSION['user_id'] ?? null, $skpd, $nama, $npwp, $alamat, $bank, $noRek, $nmRek, $jenis]);
        jsonResponse(true, 'Rekanan berhasil disimpan.', ['id' => (int) $pdo->lastInsertId()], 201);
    }

    // ---------- SPD ----------
    if ($action === 'spd_create') {
        $tanggal  = trim((string) ($body['tanggal'] ?? ''));
        $jenis    = trim((string) ($body['jenis'] ?? ''));
        $periode  = trim((string) ($body['periode'] ?? ''));
        $jumlah   = (float) ($body['jumlah'] ?? 0);
        if ($tanggal === '') jsonResponse(false, 'Tanggal SPD wajib diisi.', ['field' => 'tanggal'], 422);
        if ($jumlah <= 0) jsonResponse(false, 'Jumlah harus lebih dari 0.', ['field' => 'jumlah'], 422);
        $nomor = genNomor('SPD', $pdo, 'spd');
        $stmt = $pdo->prepare("INSERT INTO spd (user_id, skpd, nomor_spd, tanggal, jenis, periode, jumlah, status) VALUES (?,?,?,?,?,?,?, 'belum_otorisasi')");
        $stmt->execute([$_SESSION['user_id'] ?? null, $skpd, $nomor, $tanggal, $jenis, $periode, $jumlah]);
        jsonResponse(true, 'SPD berhasil dibuat.', ['id' => (int) $pdo->lastInsertId(), 'nomor_spd' => $nomor], 201);
    }
    if ($action === 'spd_otorisasi') {
        $id = (int) ($body['id'] ?? 0);
        if ($id <= 0) jsonResponse(false, 'ID tidak valid.', [], 422);
        $c = skpdCond('s', $skpd);
        $stmt = $pdo->prepare("UPDATE spd s SET s.status='sudah_otorisasi' WHERE s.id=? AND s.status='belum_otorisasi'{$c[0]}");
        $stmt->execute(array_merge([$id], $c[1]));
        if ($stmt->rowCount() === 0) jsonResponse(false, 'SPD tidak ditemukan / sudah diotorisasi / bukan milik instansi Anda.', [], 404);
        jsonResponse(true, 'SPD berhasil diotorisasi.');
    }

    // ---------- SPP ----------
    if ($action === 'spp_create') {
        $tanggal = trim((string) ($body['tanggal'] ?? ''));
        $jenis   = trim((string) ($body['jenis_spp'] ?? ''));
        $spdId   = (int) ($body['spd_id'] ?? 0);
        $rekId   = (int) ($body['rekanan_id'] ?? 0);
        $keperluan = trim((string) ($body['keperluan'] ?? ''));
        $jumlah  = (float) ($body['jumlah'] ?? 0);
        if ($tanggal === '') jsonResponse(false, 'Tanggal SPP wajib diisi.', ['field' => 'tanggal'], 422);
        if ($spdId <= 0) jsonResponse(false, 'Pilih SPD.', ['field' => 'spd_id'], 422);
        if ($jumlah <= 0) jsonResponse(false, 'Jumlah harus lebih dari 0.', ['field' => 'jumlah'], 422);
        // Validasi SPD terotorisasi milik instansi
        $c = skpdCond('s', $skpd);
        $chk = $pdo->prepare("SELECT id FROM spd s WHERE s.id=? AND s.status='sudah_otorisasi'{$c[0]}");
        $chk->execute(array_merge([$spdId], $c[1]));
        if (!$chk->fetch()) jsonResponse(false, 'SPD tidak valid / belum diotorisasi / bukan milik instansi Anda.', [], 422);
        $nomor = genNomor('SPP', $pdo, 'spp');
        $stmt = $pdo->prepare("INSERT INTO spp (user_id, skpd, nomor_spp, tanggal, jenis_spp, spd_id, rekanan_id, keperluan, jumlah, status) VALUES (?,?,?,?,?,?,?,?,?, 'belum_diverifikasi')");
        $stmt->execute([$_SESSION['user_id'] ?? null, $skpd, $nomor, $tanggal, $jenis, $spdId, $rekId ?: null, $keperluan, $jumlah]);
        jsonResponse(true, 'SPP berhasil dibuat.', ['id' => (int) $pdo->lastInsertId(), 'nomor_spp' => $nomor], 201);
    }
    if ($action === 'spp_verifikasi') {
        $id = (int) ($body['id'] ?? 0);
        $setuju = !empty($body['setuju']);
        if ($id <= 0) jsonResponse(false, 'ID tidak valid.', [], 422);
        $c = skpdCond('s', $skpd);
        $new = $setuju ? 'sudah_diverifikasi' : 'ditolak';
        // verifikasi setuju sekaligus membuat SPM
        $stmt = $pdo->prepare("UPDATE spp s SET s.status=? WHERE s.id=? AND s.status='belum_diverifikasi'{$c[0]}");
        $stmt->execute(array_merge([$new, $id], $c[1]));
        if ($stmt->rowCount() === 0) jsonResponse(false, 'SPP tidak ditemukan / bukan milik instansi Anda.', [], 404);
        if ($setuju) {
            $sp = $pdo->prepare("SELECT * FROM spp WHERE id=?"); $sp->execute([$id]); $spp = $sp->fetch();
            $nomor = genNomor('SPM', $pdo, 'spm');
            $in = $pdo->prepare("INSERT INTO spm (user_id, skpd, nomor_spm, tanggal, spp_id, jumlah, status) VALUES (?,?,?,?,?,?, 'belum_disetujui')");
            $in->execute([$_SESSION['user_id'] ?? null, $spp['skpd'], $nomor, $spp['tanggal'], $spp['id'], $spp['jumlah']]);
            jsonResponse(true, 'SPP diverifikasi & SPM berhasil dibuat.', ['id' => $id, 'nomor_spm' => $nomor]);
        }
        jsonResponse(true, 'SPP ditolak.');
    }

    // ---------- SPM ----------
    if ($action === 'spm_persetujuan') {
        $id = (int) ($body['id'] ?? 0);
        if ($id <= 0) jsonResponse(false, 'ID tidak valid.', [], 422);
        $c = skpdCond('s', $skpd);
        $stmt = $pdo->prepare("UPDATE spm s SET s.status='belum_diverifikasi' WHERE s.id=? AND s.status='belum_disetujui'{$c[0]}");
        $stmt->execute(array_merge([$id], $c[1]));
        if ($stmt->rowCount() === 0) jsonResponse(false, 'SPM tidak ditemukan / bukan milik instansi Anda.', [], 404);
        jsonResponse(true, 'SPM disetujui (SPTJM SPM dibuat).');
    }
    if ($action === 'spm_verifikasi') {
        $id = (int) ($body['id'] ?? 0);
        $setuju = !empty($body['setuju']);
        if ($id <= 0) jsonResponse(false, 'ID tidak valid.', [], 422);
        $c = skpdCond('s', $skpd);
        $new = $setuju ? 'sudah_diverifikasi' : 'ditolak';
        $stmt = $pdo->prepare("UPDATE spm s SET s.status=? WHERE s.id=? AND s.status='belum_diverifikasi'{$c[0]}");
        $stmt->execute(array_merge([$new, $id], $c[1]));
        if ($stmt->rowCount() === 0) jsonResponse(false, 'SPM tidak ditemukan / belum diverifikasi / bukan milik instansi Anda.', [], 404);
        jsonResponse(true, $setuju ? 'SPM berhasil diverifikasi.' : 'SPM ditolak.');
    }

    // ---------- SP2D ----------
    if ($action === 'sp2d_create') {
        $tanggal = trim((string) ($body['tanggal'] ?? ''));
        $spmId   = (int) ($body['spm_id'] ?? 0);
        $rekening = trim((string) ($body['rekening'] ?? ''));
        $jumlah  = (float) ($body['jumlah'] ?? 0);
        if ($tanggal === '') jsonResponse(false, 'Tanggal SP2D wajib diisi.', ['field' => 'tanggal'], 422);
        if ($spmId <= 0) jsonResponse(false, 'Pilih SPM.', ['field' => 'spm_id'], 422);
        if ($jumlah <= 0) jsonResponse(false, 'Jumlah harus lebih dari 0.', ['field' => 'jumlah'], 422);
        $c = skpdCond('s', $skpd);
        $chk = $pdo->prepare("SELECT id FROM spm s WHERE s.id=? AND s.status='sudah_diverifikasi'{$c[0]}");
        $chk->execute(array_merge([$spmId], $c[1]));
        if (!$chk->fetch()) jsonResponse(false, 'SPM tidak valid / belum diverifikasi / bukan milik instansi Anda.', [], 422);
        $nomor = genNomor('SP2D', $pdo, 'sp2d');
        $stmt = $pdo->prepare("INSERT INTO sp2d (user_id, skpd, nomor_sp2d, tanggal, spm_id, rekening, jumlah, status) VALUES (?,?,?,?,?,?,?, 'belum_diverifikasi')");
        $stmt->execute([$_SESSION['user_id'] ?? null, $skpd, $nomor, $tanggal, $spmId, $rekening, $jumlah]);
        jsonResponse(true, 'SP2D berhasil dibuat.', ['id' => (int) $pdo->lastInsertId(), 'nomor_sp2d' => $nomor], 201);
    }
    if ($action === 'sp2d_verifikasi') {
        $id = (int) ($body['id'] ?? 0);
        $setuju = !empty($body['setuju']);
        if ($id <= 0) jsonResponse(false, 'ID tidak valid.', [], 422);
        $c = skpdCond('s', $skpd);
        $new = $setuju ? 'sudah_diverifikasi' : 'ditolak';
        $stmt = $pdo->prepare("UPDATE sp2d s SET s.status=? WHERE s.id=? AND s.status='belum_diverifikasi'{$c[0]}");
        $stmt->execute(array_merge([$new, $id], $c[1]));
        if ($stmt->rowCount() === 0) jsonResponse(false, 'SP2D tidak ditemukan / bukan milik instansi Anda.', [], 404);
        jsonResponse(true, $setuju ? 'SP2D berhasil diverifikasi.' : 'SP2D ditolak.');
    }
    if ($action === 'sp2d_pencairan') {
        $id = (int) ($body['id'] ?? 0);
        $tanggal = trim((string) ($body['tanggal'] ?? ''));
        if ($id <= 0) jsonResponse(false, 'ID tidak valid.', [], 422);
        $c = skpdCond('s', $skpd);
        $stmt = $pdo->prepare("UPDATE sp2d s SET s.status='sudah_dicairkan', s.tanggal=COALESCE(?, s.tanggal) WHERE s.id=? AND s.status='sudah_diverifikasi'{$c[0]}");
        $stmt->execute(array_merge([$tanggal !== '' ? $tanggal : null, $id], $c[1]));
        if ($stmt->rowCount() === 0) jsonResponse(false, 'SP2D tidak ditemukan / belum diverifikasi / bukan milik instansi Anda.', [], 404);
        jsonResponse(true, 'Dana SP2D berhasil dicairkan (transfer).');
    }

    jsonResponse(false, 'Aksi tidak dikenali.', [], 422);
}

jsonResponse(false, 'Metode request tidak diizinkan.', [], 405);
