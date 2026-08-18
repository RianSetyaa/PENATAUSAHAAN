<?php
/**
 * SIM-TKD - Database Connection (PDO)
 * ============================================
 * File ini dibuat otomatis oleh setup.php.
 * Edit hanya jika diperlukan.
 */

declare(strict_types=1);

// Konfigurasi database
// >>> LOKAL (XAMPP) - UJI LOKAL <<<
// >>> PRODUKSI (cPanel) - PASTIKAN FILE INI YANG TER-UPLOAD KE HOSTING <<<
// Password TEPAT: @Admin21345 (sekali). Jangan dobel.
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'simtkd');
define('DB_USER', 'root');
define('DB_PASS', '');

// Cegah akses langsung ke file ini
if (basename($_SERVER['PHP_SELF'] ?? '') === 'db.php') {
    http_response_code(403);
    exit('Akses ditolak.');
}

/**
 * Mengembalikan koneksi PDO (singleton).
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            // Respons JSON jika dipanggil dari API
            if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Koneksi ke database gagal. Pastikan MySQL aktif dan database sudah dibuat (jalankan setup.php).'
                ]);
                exit;
            }
            http_response_code(500);
            exit('Koneksi ke database gagal: ' . htmlspecialchars($e->getMessage()));
        }
    }

    return $pdo;
}