<?php
/**
 * SIM-TKD (peta.simtkd.com) - Konfigurasi API
 * ============================================
 * Token AKLAP kini DINAMIS: setiap login aplikasi utama menghasilkan
 * api_token baru untuk user tsb (disimpan di users.api_token) dan token
 * lama otomatis tidak berlaku (rotasi keamanan). Saat logout token di-NULL-kan.
 *
 * API aklap.php memvalidasi token dengan mencocokkan users.api_token
 * (BUKAN konstanta di bawah). Konstanta API_TOKEN di sini hanya nilai
 * legacy/token admin awal, tidak lagi dipakai untuk validasi.
 */

declare(strict_types=1);

define('API_TOKEN', 'ce82dba3fa012a233bb69e325acc9593');
