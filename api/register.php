<?php
/**
 * SIM-TKD - API Register
 * ============================================
 * Endpoint pendaftaran akun baru (metode POST, respons JSON).
 *
 * Contoh pemanggilan (fetch):
 *   fetch('api/register.php', {
 *       method: 'POST',
 *       headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
 *       body: new URLSearchParams({ nama_lengkap, username, email, password, instansi, peran })
 *   })
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Hanya terima metode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Metode request tidak diizinkan.', [], 405);
}

// Ambil dan validasi input
$nama       = input('nama_lengkap');
$username   = input('username');
$email      = input('email');
$password   = input('password');
$instansi   = input('instansi');
$peran      = input('peran', 'Pengguna Umum');

// --- Validasi server-side ---
if (!isValidNama($nama)) {
    jsonResponse(false, 'Nama lengkap minimal 3 karakter.', ['field' => 'nama_lengkap'], 422);
}

if (!isValidUsername($username)) {
    jsonResponse(false, 'Username 3-20 karakter (huruf, angka, . _ -).', ['field' => 'username'], 422);
}

if (!isValidEmail($email)) {
    jsonResponse(false, 'Format email tidak valid.', ['field' => 'email'], 422);
}

if (strlen($password) < 8) {
    jsonResponse(false, 'Kata sandi minimal 8 karakter.', ['field' => 'password'], 422);
}

$allowedRoles = ['Admin Dinas', 'Operator', 'Bendahara', 'Verifikator', 'Kepala Dinas', 'Pengguna Umum'];
if (!in_array($peran, $allowedRoles, true)) {
    $peran = 'Pengguna Umum';
}

$pdo = db();

// Cek duplikasi username & email
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
$stmt->execute([$username, $email]);
if ($stmt->fetchColumn() > 0) {
    $stmt = $pdo->prepare("SELECT username, email FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    $existing = $stmt->fetch();

    if ($existing['username'] === $username) {
        jsonResponse(false, 'Username sudah digunakan.', ['field' => 'username'], 409);
    }
    if ($existing['email'] === $email) {
        jsonResponse(false, 'Email sudah terdaftar.', ['field' => 'email'], 409);
    }
}

// Hash password
$hashed = password_hash($password, PASSWORD_DEFAULT);

// Simpan pengguna baru (status default: pending -> menunggu verifikasi admin)
try {
    $stmt = $pdo->prepare("
        INSERT INTO users (nama_lengkap, username, email, password, instansi, peran, status)
        VALUES (?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([$nama, $username, $email, $hashed, $instansi, $peran]);

    $userId = (int) $pdo->lastInsertId();
} catch (PDOException $e) {
    jsonResponse(false, 'Terjadi kesalahan saat menyimpan data. Silakan coba lagi.', [], 500);
}

jsonResponse(true, 'Pendaftaran berhasil! Akun Anda menunggu verifikasi administrator. Silakan login.', [
    'user_id' => $userId,
], 201);
