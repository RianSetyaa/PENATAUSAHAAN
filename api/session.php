<?php
/**
 * SIM-TKD - API Session Check
 * ============================================
 * Endpoint untuk mengecek status login dari frontend (GET).
 * Digunakan halaman .html (vanilla JS) untuk melindungi dashboard.
 *
 * Contoh:
 *   fetch('api/session.php')
 *     .then(r => r.json())
 *     .then(d => d.success ? d.user : redirect ke login.html)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    jsonResponse(false, 'Tidak terautentikasi.', [], 401);
}

jsonResponse(true, 'Autentikasi valid.', [
    'user' => [
        'id'       => $_SESSION['user_id'] ?? null,
        'nama'     => $_SESSION['nama'] ?? '',
        'username' => $_SESSION['username'] ?? '',
        'instansi' => $_SESSION['instansi'] ?? '',
        'peran'    => $_SESSION['peran'] ?? '',
    ],
    'tahun_anggaran' => $_SESSION['tahun_anggaran'] ?? date('Y'),
]);
