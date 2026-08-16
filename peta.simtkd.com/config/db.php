<?php
/**
 * SIM-TKD (peta.simtkd.com) - Database Connection (PDO)
 * ============================================
 * Koneksi ke database yang sama dengan aplikasi utama SIM-TKD.
 * Sesuaikan kredensial dengan server produksi bila berbeda.
 */

declare(strict_types=1);

// Konfigurasi database
define('DB_HOST', 'localhost');
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
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Koneksi ke database gagal. Pastikan MySQL aktif.'
            ]);
            exit;
        }
    }

    return $pdo;
}
