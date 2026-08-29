<?php
/**
 * SIM-TKD - API Dokumen & Tanda Tangan Elektronik (app utama)
 * ============================================================
 * Menyimpan dokumen hasil laman cetak ke antrean tanda tangan
 * yang dikelola di doc.simtkd.com (memakai database yang sama).
 *
 *   POST api/dokumen.php   (wajib login / sesi PHP)
 *     action=kirim : simpan dokumen
 *       - jenis       : SPP / SPD / SPM / SP2D / LPJ / NPD / dll
 *       - ref_id      : id baris sumber (spp.id, spd.id, ...)
 *       - nomor       : nomor dokumen
 *       - judul       : judul dokumen
 *       - tanggal     : YYYY-MM-DD
 *       - konten_html : HTML mandiri (CSS tertanam) dari laman cetak
 *
 * Kirim ulang dokumen yang masih menunggu TTD akan memperbarui
 * kontennya; dokumen yang sudah ditandatangani tidak dapat diubah.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Metode request tidak diizinkan.', [], 405);
}
if (!isLoggedIn()) {
    jsonResponse(false, 'Sesi berakhir. Silakan login kembali.', [], 401);
}

$action = (string) ($_POST['action'] ?? '');
if ($action !== 'kirim') {
    jsonResponse(false, 'Aksi tidak dikenal.', [], 422);
}

$skpd   = requireInstansi();
$userId = (int) ($_SESSION['user_id'] ?? 0);

$jenis   = trim((string) ($_POST['jenis'] ?? ''));
$refId   = (int) ($_POST['ref_id'] ?? 0);
$nomor   = trim((string) ($_POST['nomor'] ?? ''));
$judul   = trim((string) ($_POST['judul'] ?? ''));
$tanggal = trim((string) ($_POST['tanggal'] ?? ''));
$konten  = (string) ($_POST['konten_html'] ?? '');

if ($jenis === '') { $jenis = 'Dokumen'; }
if ($judul === '') { $judul = $jenis . ($nomor !== '' ? ' Nomor ' . $nomor : ''); }
if (mb_strlen($konten) < 200) {
    jsonResponse(false, 'Konten dokumen kosong / tidak valid.', [], 422);
}
if (strlen($konten) > 4 * 1024 * 1024) {
    jsonResponse(false, 'Konten dokumen terlalu besar (maks ±4 MB).', [], 413);
}
if ($tanggal !== '' && !isValidTanggal($tanggal)) {
    jsonResponse(false, 'Format tanggal tidak valid.', [], 422);
}

$hash = hash('sha256', $konten);
$pdo  = db();

try {
    // Kirim ulang: perbarui dokumen milik user yang masih menunggu TTD
    if ($refId > 0) {
        $stmt = $pdo->prepare(
            "SELECT id, status FROM dokumen
             WHERE user_id = ? AND jenis = ? AND ref_id = ?
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$userId, $jenis, $refId]);
        $lama = $stmt->fetch();
        if ($lama) {
            if ($lama['status'] === 'ditandatangani') {
                jsonResponse(true, 'Dokumen ini sudah ditandatangani dan tidak dapat dikirim ulang.', [
                    'id'     => (int) $lama['id'],
                    'status' => 'ditandatangani',
                ]);
            }
            $up = $pdo->prepare(
                "UPDATE dokumen
                 SET nomor = ?, judul = ?, tanggal = ?, konten_html = ?, hash_original = ?, skpd = ?
                 WHERE id = ?"
            );
            $up->execute([
                $nomor, $judul, ($tanggal !== '' ? $tanggal : null),
                $konten, $hash, $skpd, (int) $lama['id'],
            ]);
            jsonResponse(true, 'Dokumen diperbarui di antrean tanda tangan.', [
                'id'     => (int) $lama['id'],
                'status' => 'menunggu_ttd',
            ]);
        }
    }

    // Kode verifikasi unik (coba beberapa kali bila bertabrakan)
    $kode = '';
    for ($i = 0; $i < 5; $i++) {
        $c   = 'TTD-' . strtoupper(bin2hex(random_bytes(4)));
        $cek = $pdo->prepare("SELECT id FROM dokumen WHERE kode_verifikasi = ? LIMIT 1");
        $cek->execute([$c]);
        if (!$cek->fetch()) { $kode = $c; break; }
    }
    if ($kode === '') {
        jsonResponse(false, 'Gagal membuat kode verifikasi. Silakan coba lagi.', [], 500);
    }

    $pdo->beginTransaction();
    $ins = $pdo->prepare(
        "INSERT INTO dokumen
            (user_id, skpd, jenis, ref_id, nomor, judul, tanggal, konten_html, hash_original, kode_verifikasi, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'menunggu_ttd')"
    );
    $ins->execute([
        $userId, $skpd, $jenis, ($refId > 0 ? $refId : null), $nomor, $judul,
        ($tanggal !== '' ? $tanggal : null), $konten, $hash, $kode,
    ]);
    $dokId = (int) $pdo->lastInsertId();

    // Penandatangan tunggal: pembuat dokumen (skema siap multi-pihak via urutan)
    $ins2 = $pdo->prepare(
        "INSERT INTO dokumen_ttd (dokumen_id, user_id, urutan, jabatan, nama, status)
         VALUES (?, ?, 1, ?, ?, 'menunggu')"
    );
    $ins2->execute([
        $dokId, $userId,
        (string) ($_SESSION['peran'] ?? ''),
        (string) ($_SESSION['nama'] ?? ''),
    ]);

    $pdo->commit();
    jsonResponse(true, 'Dokumen terkirim ke antrean tanda tangan (doc.simtkd.com).', [
        'id'              => $dokId,
        'kode_verifikasi' => $kode,
        'status'          => 'menunggu_ttd',
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('[SIM-TKD] dokumen kirim gagal: ' . $e->getMessage());
    jsonResponse(false, 'Gagal menyimpan dokumen. Coba beberapa saat lagi.', [], 500);
}
