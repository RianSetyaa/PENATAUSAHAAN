<?php
/**
 * SIM-TKD - Redirect ke Modul AKLAP (server-side, dengan token API user)
 * ============================================
 * Dipakai oleh link menu "Akuntansi". Token diambil langsung dari
 * sesi/database (bukan JavaScript), sehingga selalu membawa api_token
 * milik user yang sedang login -> AKLAP menampilkan data instansi tsb.
 *
 * Jika user belum punya api_token (mis. akun lama), dibuatkan otomatis.
 * Jika belum login, dikembalikan ke halaman login.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    header('Location: ../login.html');
    exit;
}

// Ambil token dari sesi; auto-provisi bila kosong (akun lama)
$token = (string) ($_SESSION['api_token'] ?? '');
if ($token === '') {
    $token = bin2hex(random_bytes(16));
    try {
        $stmt = db()->prepare("UPDATE users SET api_token = ? WHERE id = ?");
        $stmt->execute([$token, (int) $_SESSION['user_id']]);
        $_SESSION['api_token'] = $token;
    } catch (Throwable $e) {
        // biarkan kosong; AKLAP akan menolak tanpa token
    }
}

$target = 'https://peta.simtkd.com/?token=' . rawurlencode($token);
header('Location: ' . $target, true, 302);
exit;
