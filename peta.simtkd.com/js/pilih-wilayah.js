/**
 * SIM-TKD - Pilih Wilayah (Provinsi -> Kota/Kabupaten)
 * ============================================
 * Komponen cascading: pilih Provinsi dulu, lalu Kota/Kabupaten mengerucut
 * (sesuai provinsi) dan dapat dicari (searchable).
 *
 * Bergantung pada DAFTAR_KOTA (js/daftar-kota.js).
 *
 * Cara pakai:
 *   initPilihWilayah({
 *     provinsiId: 'provinsi',        // <select> provinsi (akan diisi otomatis)
 *     kotaId: 'kota',                // <input type=hidden> menyimpan kota terpilih
 *     kotaInputId: 'kotaInput',      // <input type=text> kotak pencarian/tampilan
 *     kotaListId: 'kotaList',        // <div> daftar dropdown
 *     defaultProvinsi: '',           // opsional: provinsi awal
 *     defaultKota: '',               // opsional: kota awal
 *     onKotaChange: function(kota, provinsi) {}  // opsional
 *   });
 */
function initPilihWilayah(opts) {
    'use strict';

    var provSel = document.getElementById(opts.provinsiId);
    var kotaHidden = document.getElementById(opts.kotaId);
    var kotaInput = document.getElementById(opts.kotaInputId);
    var kotaList = document.getElementById(opts.kotaListId);

    if (!provSel || !kotaHidden || !kotaInput || !kotaList || !window.DAFTAR_KOTA) {
        return;
    }

    // ---------- Isi dropdown provinsi (unik, urut) ----------
    var provs = [];
    DAFTAR_KOTA.forEach(function (k) {
        if (provs.indexOf(k.provinsi) === -1) provs.push(k.provinsi);
    });
    provs.sort();
    provs.forEach(function (p) {
        var o = document.createElement('option');
        o.value = p;
        o.textContent = p;
        provSel.appendChild(o);
    });

    function kotaByProv(prov) {
        return DAFTAR_KOTA.filter(function (k) { return k.provinsi === prov; });
    }

    function clearKota(notify) {
        kotaHidden.value = '';
        kotaInput.value = '';
        if (notify !== false && opts.onKotaChange) opts.onKotaChange('', '');
    }

    function renderList() {
        var prov = provSel.value;
        var q = kotaInput.value.trim().toLowerCase();

        if (!prov) {
            kotaList.innerHTML = '<div class="pw-empty">Pilih Provinsi terlebih dahulu</div>';
            return;
        }
        var items = kotaByProv(prov).filter(function (k) {
            return !q || k.kota.toLowerCase().indexOf(q) !== -1;
        });
        if (!items.length) {
            kotaList.innerHTML = '<div class="pw-empty">Kota/Kabupaten tidak ditemukan</div>';
            return;
        }
        // Batasi tampilan agar tidak terlalu panjang
        var shown = items.slice(0, 80);
        var html = '';
        shown.forEach(function (k) {
            html += '<div class="pw-item" data-kota="' + k.kota.replace(/"/g, '&quot;') + '">' +
                    k.kota.replace(/&/g, '&amp;').replace(/</g, '&lt;') + '</div>';
        });
        kotaList.innerHTML = html;
        if (items.length > shown.length) {
            kotaList.innerHTML += '<div class="pw-empty">' + items.length + ' hasil — ketik untuk mempersempit</div>';
        }
        // Event item
        Array.prototype.forEach.call(kotaList.querySelectorAll('.pw-item'), function (el) {
            el.addEventListener('mousedown', function (e) {
                e.preventDefault();
                var name = el.getAttribute('data-kota');
                var prov = provSel.value;
                kotaHidden.value = name;
                kotaInput.value = name;
                kotaList.classList.remove('open');
                if (opts.onKotaChange) opts.onKotaChange(name, prov);
            });
        });
    }

    function openList() {
        kotaList.classList.add('open');
        renderList();
    }

    // ---------- Events ----------
    provSel.addEventListener('change', function () {
        clearKota();
        if (opts.defaultKota && provSel.value === opts.defaultProvinsi) {
            // diterapkan saat init
        }
        if (opts.onKotaChange) opts.onKotaChange('', provSel.value);
    });

    kotaInput.addEventListener('focus', openList);
    kotaInput.addEventListener('input', function () {
        // Jika teks berubah dan tidak sama dengan pilihan tersimpan, kosongkan pilihan
        if (kotaHidden.value && kotaInput.value !== kotaHidden.value) {
            clearKota();
        }
        openList();
    });
    kotaInput.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') kotaList.classList.remove('open');
    });
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.pw-wrap')) kotaList.classList.remove('open');
    });

    // ---------- Default (jika diset) ----------
    if (opts.defaultProvinsi) {
        var found = false;
        for (var i = 0; i < provSel.options.length; i++) {
            if (provSel.options[i].value === opts.defaultProvinsi) {
                provSel.value = opts.defaultProvinsi;
                found = true;
                break;
            }
        }
        if (found && opts.defaultKota) {
            var def = null;
            DAFTAR_KOTA.forEach(function (k) {
                if (k.provinsi === opts.defaultProvinsi && k.kota === opts.defaultKota) def = k;
            });
            if (def) {
                kotaHidden.value = def.kota;
                kotaInput.value = def.kota;
            }
        }
    }
}
