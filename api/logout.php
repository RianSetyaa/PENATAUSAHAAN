<?php
/**
 * SIM-TKD - Logout
 * ============================================
 * Menghapus sesi dan mengalihkan kembali ke halaman login.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Nonaktifkan token API (keamanan): token lama langsung tidak berlaku saat logout
if (!empty($_SESSION['user_id'])) {
    try {
        $stmt = db()->prepare("UPDATE users SET api_token = NULL WHERE id = ?");
        $stmt->execute([(int) $_SESSION['user_id']]);
    } catch (Throwable $e) {
        // abaikan; sesi tetap dihapus
    }
}

destroySession();

// Dukung dua mode: AJAX (JSON) atau navigasi langsung
if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
    || isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'message' => 'Logout berhasil.', 'redirect' => 'login.html']);
    exit;
}

header('Location: ../login.html');
exit;
