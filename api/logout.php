<?php
/**
 * SIM-TKD - Logout
 * ============================================
 * Menghapus sesi dan mengalihkan kembali ke halaman login.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

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
