<?php
/**
 * SIM-TKD - Helper Functions
 * ============================================
 * Fungsi bantu untuk validasi, sanitasi, dan respons JSON API.
 */

declare(strict_types=1);

if (basename($_SERVER['PHP_SELF'] ?? '') === 'functions.php') {
    http_response_code(403);
    exit('Akses ditolak.');
}

/**
 * Kirim respons JSON dan hentikan eksekusi.
 */
function jsonResponse(bool $success, string $message, array $extra = [], int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(
        ['success' => $success, 'message' => $message],
        $extra
    ), JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Ambil nilai input (POST/GET) lalu trim.
 */
function input(string $key, string $default = ''): string
{
    if (isset($_POST[$key])) {
        return trim((string) $_POST[$key]);
    }
    if (isset($_GET[$key])) {
        return trim((string) $_GET[$key]);
    }
    return $default;
}

/**
 * Validasi format email.
 */
function isValidEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validasi username: 3-20 karakter, huruf/angka/._-
 */
function isValidUsername(string $username): bool
{
    return (bool) preg_match('/^[a-zA-Z0-9._-]{3,20}$/', $username);
}

/**
 * Validasi nama lengkap: minimal 3 karakter.
 */
function isValidNama(string $nama): bool
{
    return mb_strlen($nama) >= 3;
}

/**
 * Generate captcha sederhana untuk edukasi (opsional, sisi server).
 */
function generateCaptchaCode(int $length = 5): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, $max)];
    }
    return $code;
}
