/**
 * SIM-TKD (peta.simtkd.com) - Konfigurasi API Frontend
 * ============================================
 * Endpoint & token untuk mengambil data asli dari database.
 * Pastikan token di sini sama dengan config/api.php.
 */
var SIMTKD_API = {
    endpoint: 'api/aklap.php',
    token: 'ce82dba3fa012a233bb69e325acc9593',
    // Token efektif: prioritas dari URL (?token=) -> fallback token default
    getToken: function () {
        try {
            var t = new URLSearchParams(window.location.search).get('token');
            if (t) return t;
        } catch (e) {}
        return this.token;
    }
};
