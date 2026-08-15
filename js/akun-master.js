/**
 * SIM-TKD - Master Akun Penerimaan (statis)
 * ============================================
 * Daftar lengkap akun/rekening penerimaan sesuai bagan rekening
 * Modul SKPKD (Permendagri). TIDAK disimpan di database per dinas;
 * dinas hanya mencentang (menyimpan) akun yang dipakainya.
 * Struktur: jenis pendapatan -> daftar rekening.
 */
var AKUN_MASTER = [
    {
        jenis: '4.1.1', nama: 'Pendapatan Pajak Daerah', metode: 'per_wajib_pajak',
        rekening: [
            { kode: '4.1.1.01', nama: 'Pajak Hotel', metode: 'per_wajib_pajak' },
            { kode: '4.1.1.01.01', nama: 'Hotel Bintang Berlian', metode: 'per_wajib_pajak' },
            { kode: '4.1.1.01.02', nama: 'Hotel Bintang Lima', metode: 'per_wajib_pajak' },
            { kode: '4.1.1.01.03', nama: 'Hotel Bintang Empat', metode: 'per_wajib_pajak' },
            { kode: '4.1.1.01.04', nama: 'Hotel Bintang Tiga', metode: 'per_wajib_pajak' },
            { kode: '4.1.1.01.05', nama: 'Hotel Bintang Dua', metode: 'per_wajib_pajak' },
            { kode: '4.1.1.01.06', nama: 'Hotel Bintang Satu', metode: 'per_wajib_pajak' },
            { kode: '4.1.1.01.07', nama: 'Hotel Melati Tiga', metode: 'per_wajib_pajak' },
            { kode: '4.1.1.02', nama: 'Pajak Restoran', metode: 'per_wajib_pajak' },
            { kode: '4.1.1.02.01', nama: 'Restoran', metode: 'per_wajib_pajak' },
            { kode: '4.1.1.02.02', nama: 'Rumah Makan', metode: 'per_wajib_pajak' },
            { kode: '4.1.1.02.03', nama: 'Kafe', metode: 'per_wajib_pajak' },
            { kode: '4.1.1.02.04', nama: 'Kantin', metode: 'per_wajib_pajak' },
            { kode: '4.1.1.03', nama: 'Pajak Hiburan', metode: 'per_wajib_pajak' },
            { kode: '4.1.1.03.01', nama: 'Tontonan Film/Bioskop', metode: 'per_wajib_pajak' },
            { kode: '4.1.1.03.02', nama: 'Pameran', metode: 'per_wajib_pajak' },
            { kode: '4.1.1.03.03', nama: 'Diskotek', metode: 'per_wajib_pajak' },
            { kode: '4.1.1.03.04', nama: 'Pacuan Kuda', metode: 'per_wajib_pajak' },
            { kode: '4.1.1.04', nama: 'Pajak Reklame', metode: 'per_wajib_pajak' },
            { kode: '4.1.1.04.01', nama: 'Reklame Papan/Billboard', metode: 'per_wajib_pajak' },
            { kode: '4.1.1.04.02', nama: 'Reklame Kain', metode: 'per_wajib_pajak' },
            { kode: '4.1.1.04.09', nama: 'Reklame Film/Slide', metode: 'per_wajib_pajak' },
            { kode: '4.1.1.05', nama: 'Penerangan Jalan Umum', metode: 'per_wajib_pajak' },
            { kode: '4.1.1.05.01', nama: 'Pajak Penerangan Jalan PLN', metode: 'per_wajib_pajak' },
            { kode: '4.1.1.08', nama: 'Pajak Air Bawah Tanah', metode: 'per_wajib_pajak' },
            { kode: '4.1.1.08.01', nama: 'Pajak Air Bawah Tanah', metode: 'per_wajib_pajak' },
            { kode: '4.1.1.09', nama: 'Pajak Sarang Burung Walet', metode: 'per_wajib_pajak' },
            { kode: '4.1.1.09.01', nama: 'Pajak Sarang Burung Walet', metode: 'per_wajib_pajak' },
            { kode: '4.1.1.10', nama: 'Pajak Lingkungan', metode: 'per_wajib_pajak' },
            { kode: '4.1.1.10.01', nama: 'Pajak Lingkungan', metode: 'per_wajib_pajak' }
        ]
    },
    {
        jenis: '4.1.2', nama: 'Pendapatan Retribusi Daerah', metode: 'per_wajib_retribusi',
        rekening: [
            { kode: '4.1.2.01', nama: 'Retribusi Jasa Usaha', metode: 'per_wajib_retribusi' },
            { kode: '4.1.2.01.01', nama: 'Retribusi Pemakaian Kekayaan Daerah', metode: 'per_wajib_retribusi' },
            { kode: '4.1.2.01.02', nama: 'Retribusi Parkir', metode: 'per_wajib_retribusi' },
            { kode: '4.1.2.01.03', nama: 'Retribusi Sewa Pasar Tradisional', metode: 'per_wajib_retribusi' }
        ]
    },
    {
        jenis: '4.1.3', nama: 'Hasil Pengelolaan Kekayaan Daerah yang Dipisahkan', metode: 'bulanan',
        rekening: [
            { kode: '4.1.3.01', nama: 'Bagian Laba atas Penyertaan Modal Pemerintah Daerah', metode: 'bulanan' },
            { kode: '4.1.3.01.01', nama: 'BUMD - A', metode: 'bulanan' },
            { kode: '4.1.3.01.02', nama: 'PT - B', metode: 'bulanan' },
            { kode: '4.1.3.04', nama: 'Pendapatan Bunga Obligasi pada BUMN', metode: 'bulanan' },
            { kode: '4.1.3.04.01', nama: 'BUMN - A', metode: 'bulanan' },
            { kode: '4.1.3.04.02', nama: 'BUMN - B', metode: 'bulanan' }
        ]
    },
    {
        jenis: '4.1.4', nama: 'Lain-Lain Pendapatan Asli Daerah yang Sah', metode: 'harian',
        rekening: [
            { kode: '4.1.4.01', nama: 'Hasil Penjualan Aset Daerah yang Tidak Dipisahkan', metode: 'harian' },
            { kode: '4.1.4.01.01', nama: 'Pelepasan Hak atas Tanah', metode: 'harian' },
            { kode: '4.1.4.02', nama: 'Penerimaan Jasa Giro', metode: 'harian' },
            { kode: '4.1.4.02.03', nama: 'Jasa Giro Dana Cadangan', metode: 'harian' },
            { kode: '4.1.4.03', nama: 'Pendapatan Bunga Deposito', metode: 'harian' },
            { kode: '4.1.4.03.01', nama: 'Rekening Deposito pada Bank', metode: 'harian' },
            { kode: '4.1.4.06.03', nama: 'Denda Keterlambatan Pekerjaan Bidang PU', metode: 'harian' },
            { kode: '4.1.4.13', nama: 'Tuntutan Ganti Kerugian Daerah', metode: 'harian' },
            { kode: '4.1.4.13.03', nama: 'Kerugian Uang', metode: 'harian' }
        ]
    },
    {
        jenis: '4.2.1', nama: 'Bagi Hasil Pajak / Bagi Hasil Bukan Pajak', metode: 'bulanan',
        rekening: [
            { kode: '4.2.1.01', nama: 'Bagi Hasil Pajak', metode: 'bulanan' },
            { kode: '4.2.1.01.01', nama: 'Bagi Hasil PBB', metode: 'bulanan' },
            { kode: '4.2.1.01.02', nama: 'Bagi Hasil BPHTB', metode: 'bulanan' },
            { kode: '4.2.1.01.03', nama: 'Bagi Hasil PPh Psl 25 dan Psl 29 WPOPDN dan PPh Psl 21', metode: 'bulanan' },
            { kode: '4.2.1.02', nama: 'Bagi Hasil Sumber Daya Alam', metode: 'bulanan' },
            { kode: '4.2.1.02.01', nama: 'Bagi Hasil Iuran Hak Penguasaan Hutan', metode: 'bulanan' },
            { kode: '4.2.1.02.02', nama: 'Bagi Hasil Provinsi Sumber Daya Hutan', metode: 'bulanan' },
            { kode: '4.2.1.02.03', nama: 'Bagi Hasil Dana Reboisasi', metode: 'bulanan' }
        ]
    },
    {
        jenis: '4.2.2', nama: 'Dana Alokasi Umum', metode: 'bulanan',
        rekening: [
            { kode: '4.2.2.01', nama: 'Dana Alokasi Umum', metode: 'bulanan' },
            { kode: '4.2.2.01.01', nama: 'Dana Alokasi Umum', metode: 'bulanan' }
        ]
    },
    {
        jenis: '4.2.3', nama: 'Dana Alokasi Khusus', metode: 'bulanan',
        rekening: [
            { kode: '4.2.3.01', nama: 'Dana Alokasi Khusus', metode: 'bulanan' },
            { kode: '4.2.3.01.01', nama: 'Dana Alokasi Khusus', metode: 'bulanan' }
        ]
    },
    {
        jenis: '4.3.1', nama: 'Pendapatan Hibah', metode: 'harian',
        rekening: [
            { kode: '4.3.1.01', nama: 'Pendapatan Hibah dari Pemerintah', metode: 'harian' },
            { kode: '4.3.1.01.01', nama: 'Pendapatan Hibah dari Pemerintah', metode: 'harian' },
            { kode: '4.3.1.02', nama: 'Pendapatan Hibah dari Pemerintah Daerah Lainnya', metode: 'harian' },
            { kode: '4.3.1.02.01', nama: 'Pendapatan Hibah dari Pemerintah Daerah Lainnya', metode: 'harian' }
        ]
    },
    {
        jenis: '4.3.2', nama: 'Pendapatan Dana Darurat', metode: 'bulanan',
        rekening: [
            { kode: '4.3.2.01', nama: 'Penanggulangan Korban', metode: 'bulanan' },
            { kode: '4.3.2.01.01', nama: 'Korban/Kerusakan Akibat Bencana Alam', metode: 'bulanan' }
        ]
    },
    {
        jenis: '4.3.3', nama: 'Dana Bagi Hasil Pajak dari Provinsi dan Pemda Lainnya', metode: 'bulanan',
        rekening: [
            { kode: '4.3.3.01', nama: 'Dana Bagi Hasil Pajak dari Provinsi', metode: 'bulanan' },
            { kode: '4.3.3.01.01', nama: 'Bagi Hasil Pajak Kendaraan Bermotor', metode: 'bulanan' },
            { kode: '4.3.3.01.03', nama: 'Bagi Hasil dari Bea Balik Nama Kendaraan Bermotor', metode: 'bulanan' },
            { kode: '4.3.3.02', nama: 'Dana Bagi Hasil Pajak dari Provinsi Lainnya', metode: 'bulanan' },
            { kode: '4.3.3.02.01', nama: 'Bagi Hasil Pajak dari Provinsi Lainnya', metode: 'bulanan' },
            { kode: '4.3.3.03', nama: 'Dana Bagi Hasil Pajak dari Kabupaten dari Provinsi Lainnya', metode: 'bulanan' },
            { kode: '4.3.3.03.01', nama: 'Dana Bagi Hasil Pajak dari Kabupaten dari Provinsi Lainnya', metode: 'bulanan' }
        ]
    },
    {
        jenis: '4.3.4', nama: 'Dana Penyesuaian dan Otonomi Khusus', metode: 'bulanan',
        rekening: [
            { kode: '4.3.4.02', nama: 'Dana Otonomi Khusus', metode: 'bulanan' },
            { kode: '4.3.4.02.01', nama: 'Dana Otonomi Khusus', metode: 'bulanan' }
        ]
    },
    {
        jenis: '4.3.5', nama: 'Bantuan Keuangan dari Pemerintah Provinsi atau Pemda Lain', metode: 'bulanan',
        rekening: [
            { kode: '4.3.5.01', nama: 'Bantuan Keuangan Provinsi', metode: 'bulanan' },
            { kode: '4.3.5.01.01', nama: 'Bantuan Keuangan dari Provinsi Setempat', metode: 'bulanan' }
        ]
    }
];
