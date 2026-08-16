<?php
/**
 * SIM-TKD (peta.simtkd.com) - Konfigurasi API
 * ============================================
 * Token API untuk melindungi akses data modul AKLAP.
 * Nilai di file ini HARUS sama dengan token di js/config.js.
 * Ganti bila diperlukan (mis. bocor atau rotasi keamanan).
 */

declare(strict_types=1);

define('API_TOKEN', 'ce82dba3fa012a233bb69e325acc9593');

// Token API DEFAULT (milik akun admin) - dipakai js/config.js sebagai fallback
// saat AKLAP dibuka langsung tanpa ?token=. Setiap pengguna memiliki
// api_token sendiri di tabel users (multi-tenant); token tsb yang dikirim
// saat masuk ke AKLAP dari menu Akuntansi aplikasi utama.
