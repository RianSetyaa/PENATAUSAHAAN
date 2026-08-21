/**
 * SIM-TKD - Helper Cetak Dokumen Belanja
 * ============================================
 * Membuka window baru berisi dokumen resmi (format print A4) sesuai
 * contoh "Modul Belanja SKPKD" dan otomatis memicu dialog cetak.
 *
 * Dipakai oleh: spd.html, spp.html, spm.html, sp2d.html, lpj.html,
 * pengajuan-tu.html, npd.html, rekanan.html, rekening.html.
 */
(function (global) {
    'use strict';

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = String(s == null ? '' : s);
        return d.innerHTML;
    }

    function rupiah(v) {
        return 'Rp ' + Number(v || 0).toLocaleString('id-ID');
    }

    function tgl(s) {
        if (!s) return '-';
        var p = String(s).split('-');
        var b = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        return (parseInt(p[2], 10) || p[2]) + ' ' + b[(parseInt(p[1], 10) - 1) || 0] + ' ' + (p[0] || '');
    }

    function tglPanjang(s) {
        if (!s) return '-';
        return tgl(s);
    }

    // Nama instansi/SKPD aktif (dari sidebar yang diisi dashboard.js)
    function instansi() {
        var el = document.getElementById('sidebarUserRole');
        if (el && el.textContent && el.textContent.trim() !== '') return el.textContent.trim();
        el = document.getElementById('sidebarUserRole2') || document.getElementById('topbarUserName');
        return el ? el.textContent.trim() : '';
    }

    var CSS = [
        '* { box-sizing: border-box; }',
        'body { font-family: "Times New Roman", Georgia, serif; color: #000; margin: 0; padding: 26px 30px; }',
        '.kop { text-align: center; margin-bottom: 16px; }',
        '.kop .pem { font-size: 18px; font-weight: 700; letter-spacing: 1px; }',
        '.kop .din { font-size: 16px; font-weight: 700; margin-top: 2px; text-transform: uppercase; }',
        '.kop .alamat { font-size: 10px; margin-top: 3px; }',
        '.kop .garis { border-bottom: 2.5px solid #000; margin-top: 8px; }',
        '.judul { text-align: center; font-size: 16px; font-weight: 700; margin: 20px 0 2px; }',
        '.subjudul { text-align: center; font-size: 13px; font-weight: 700; margin-bottom: 2px; }',
        '.nomor { text-align: center; font-size: 12px; margin-bottom: 18px; }',
        'table.f { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 14px; }',
        'table.f td { border: 1px solid #000; padding: 7px 9px; vertical-align: top; }',
        'table.f td.lbl { width: 34%; font-weight: 700; background: #f7f7f7; }',
        'table.d { width: 100%; border-collapse: collapse; font-size: 12px; margin: 8px 0 16px; }',
        'table.d th, table.d td { border: 1px solid #000; padding: 6px 8px; vertical-align: top; }',
        'table.d th { background: #e8eef7; text-align: center; }',
        'table.d td.num, table.d th.num { text-align: right; }',
        '.catatan { font-size: 10px; font-style: italic; margin: 4px 0 12px; }',
        '.total { font-weight: 700; text-align: right; }',
        '.ttd { display: flex; justify-content: space-between; margin-top: 70px; font-size: 12px; }',
        '.ttd .col { text-align: center; width: 42%; }',
        '.ttd .nama { font-weight: 700; margin-top: 66px; }',
        '.ttd .nip { font-size: 11px; margin-top: 2px; }',
        '.ttd .kosong { height: 66px; }',
        '.btn-nocetak { display:none; }',
        '@media print { body { padding: 10mm 14mm; } .no-print { display:none; } }'
    ].join('\n');

    function fieldRow(label, value, opts) {
        opts = opts || {};
        return '<tr><td class="lbl">' + esc(label) + '</td><td>' + esc(value == null ? '' : value) + '</td></tr>';
    }

    function detailTable(rows, cols) {
        var head = '<tr>' + cols.map(function (c) { return '<th' + (c.num ? ' class="num"' : '') + '>' + esc(c.t) + '</th>'; }).join('') + '</tr>';
        var body = rows.map(function (r, i) {
            return '<tr>' + cols.map(function (c, ci) {
                var v = (ci === 0) ? (i + 1) : (c.key ? r[c.key] : '');
                return '<td' + (c.num ? ' class="num"' : '') + '>' + (c.fmt ? c.fmt(v) : esc(v == null ? '' : v)) + '</td>';
            }).join('') + '</tr>';
        }).join('');
        return '<table class="d"><thead>' + head + '</thead><tbody>' + body + '</tbody></table>';
    }

    function ttd(left, right) {
        return '<div class="ttd">' +
            '<div class="col">' +
                (left && left.jabatan ? '<div>' + esc(left.jabatan) + '</div>' : '<div>&nbsp;</div>') +
                '<div class="kosong"></div>' +
                '<div class="nama">' + esc((left && left.nama) || '(.........................)') + '</div>' +
                '<div class="nip">' + esc((left && left.nip) || '') + '</div>' +
            '</div>' +
            '<div class="col">' +
                (right && right.jabatan ? '<div>' + esc(right.jabatan) + '</div>' : '<div>&nbsp;</div>') +
                '<div class="kosong"></div>' +
                '<div class="nama">' + esc((right && right.nama) || '(.........................)') + '</div>' +
                '<div class="nip">' + esc((right && right.nip) || '') + '</div>' +
            '</div>' +
        '</div>';
    }

    /**
     * bukaCetak({ title, kop, judul, subjudul, nomor, fields, detail, total, ttd, catatan })
     *   kop      : string instansi (default diambil dari sidebar)
     *   judul    : judul form (mis. "SURAT PERMINTAAN PEMBAYARAN (SPP-UP)")
     *   subjudul : opsional
     *   nomor    : opsional string nomor
     *   fields   : [[label, value], ...]  atau [{label, value}]
     *   detail   : { cols: [{t,key,fmt,num}], rows: [...] }  (opsional)
     *   total    : opsional string baris total
     *   catatan  : opsional string
     *   ttd      : { left: {jabatan,nama,nip}, right: {...} } (opsional)
     */
    function bukaCetak(o) {
        var w = window.open('', '_blank', 'width=960,height=760');
        if (!w) { alert('Izinkan pop-up agar dokumen dapat dicetak.'); return; }

        var fieldsHtml = (o.fields || []).map(function (f) {
            if (Array.isArray(f)) return fieldRow(f[0], f[1]);
            return fieldRow(f.label, f.value);
        }).join('');

        var detailHtml = o.detail ? detailTable(o.detail.rows || [], o.detail.cols || []) : '';
        var totalHtml = o.total ? '<div class="total">' + esc(o.total) + '</div>' : '';
        var ttdHtml = o.ttd ? ttd(o.ttd.left, o.ttd.right) : '';
        var catatanHtml = o.catatan ? '<div class="catatan">' + esc(o.catatan) + '</div>' : '';

        var body =
            '<div class="kop">' +
                '<div class="pem">' + esc(o.pem || 'PEMERINTAH KOTA BANDUNG') + '</div>' +
                '<div class="din">' + esc(o.kop || instansi()) + '</div>' +
                (o.alamat ? '<div class="alamat">' + esc(o.alamat) + '</div>' : '') +
                '<div class="garis"></div>' +
            '</div>' +
            '<div class="judul">' + esc(o.judul || '') + '</div>' +
            (o.subjudul ? '<div class="subjudul">' + esc(o.subjudul) + '</div>' : '') +
            (o.nomor ? '<div class="nomor">' + esc(o.nomor) + '</div>' : '') +
            (fieldsHtml ? '<table class="f">' + fieldsHtml + '</table>' : '') +
            detailHtml +
            totalHtml +
            catatanHtml +
            ttdHtml +
            '<div class="no-print" style="margin-top:30px;text-align:center;">' +
                '<button onclick="window.print()" style="padding:10px 28px;font-size:14px;cursor:pointer;">Cetak / Simpan PDF</button> ' +
                '<button onclick="window.close()" style="padding:10px 28px;font-size:14px;cursor:pointer;">Tutup</button>' +
            '</div>';

        var html = '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8">' +
            '<title>' + esc(o.title || o.judul || 'Dokumen') + '</title>' +
            '<style>' + CSS + '</style></head><body>' + body + '</body></html>';

        w.document.write(html);
        w.document.close();
        w.focus();
        setTimeout(function () {
            try { w.print(); } catch (e) {}
        }, 450);
    }

    global.CetakBelanja = {
        bukaCetak: bukaCetak,
        esc: esc,
        rupiah: rupiah,
        tgl: tgl,
        tglPanjang: tglPanjang,
        instansi: instansi,
        fieldRow: fieldRow,
        detailTable: detailTable,
        ttd: ttd
    };
})(window);
