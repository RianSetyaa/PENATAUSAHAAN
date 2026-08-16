<?php
/**
 * SIM-TKD - API Login
 * ============================================
 * Endpoint autentikasi login (metode POST, respons JSON).
 *
 * Contoh pemanggilan (fetch):
 *   fetch('api/login.php', {
 *       method: 'POST',
 *       headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
 *       body: new URLSearchParams({ username, password, tahun_anggaran })
 *   })
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Hanya terima metode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Metode request tidak diizinkan.', [], 405);
}

$username = input('username');
$password = input('password');

if ($username === '' || $password === '') {
    jsonResponse(false, 'Username dan password wajib diisi.', [], 422);
}

$pdo = db();

// Cari pengguna berdasarkan username ATAU email
$stmt = $pdo->prepare("
    SELECT id, nama_lengkap, username, email, password, instansi, kota, provinsi, api_token, peran, status
    FROM users
    WHERE username = ? OR email = ?
    LIMIT 1
");
$stmt->execute([$username, $username]);
$user = $stmt->fetch();

// Verifikasi password
if (!$user || !password_verify($password, $user['password'])) {
    jsonResponse(false, 'Username atau password tidak valid.', [], 401);
}

// Cek status akun
if ($user['status'] === 'pending') {
    jsonResponse(false, 'Akun Anda belum diverifikasi oleh administrator. Silakan tunggu.', [], 403);
}

if ($user['status'] === 'nonaktif') {
    jsonResponse(false, 'Akun Anda dinonaktifkan. Hubungi administrator.', [], 403);
}

// Set sesi login
setUserSession($user);

jsonResponse(true, 'Login berhasil. Mengalihkan ke dashboard...', [
    'redirect' => 'dashboard.html',
    'user' => [
        'nama'     => $user['nama_lengkap'],
        'username' => $user['username'],
        'peran'    => $user['peran'],
        'instansi' => $user['instansi'],
    ],
]);
