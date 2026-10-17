<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole(['admin', 'petugas']);

$tgl_awal  = $_GET['tgl_awal'] ?? date('Y-m-01');
$tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT 
        p.*,
        a.nama_alat,
        u.nama_lengkap
    FROM peminjaman p
    JOIN alat a 
        ON p.id_alat = a.id
    JOIN users u 
        ON p.id_user = u.id
    WHERE p.tanggal_pinjam BETWEEN ? AND ?
    ORDER BY p.tanggal_pinjam DESC
");

$stmt->execute([
    $tgl_awal,
    $tgl_akhir
]);

$laporan = $stmt->fetchAll();

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<style>
@media print {

    /* Sembunyikan semua elemen halaman */
    body * {
        visibility: hidden !important;
    }

    /* Hanya area laporan yang ditampilkan */
    #areaCetak,
    #areaCetak * {
        visibility: visible !important;
    }

    /* Area laporan memenuhi halaman */
    #areaCetak {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        margin: 0;
        padding: 20px;
    }

    /* Jangan tampilkan elemen yang diberi no-print */
    .no-print {
        display: none !important;
    }

    /* Hilangkan shadow dan border card */
    #areaCetak .card {
        border: none !important;
        box-shadow: none !important;
    }

    #areaCetak .card-body {
        padding: 0 !important;
    }

    /* Judul laporan */
    .judul-cetak {
        display: block !important;
        text-align: center;
        margin-bottom: 20px;
    }

    .judul-cetak h2 {
        font-size: 22px;
        margin-bottom: 5px;
    }

    .judul-cetak p {
        margin: 0;
        font-size: 13px;
    }

    /* Tabel */
    #areaCetak table {
        width: 100% !important;
        border-collapse: collapse !important;
        font-size: 11px;
    }

    #areaCetak th,
    #areaCetak td {
        border: 1px solid #000 !important;
        padding: 6px !important;
        color: #000 !important;
        background: white !important;
    }

    #areaCetak thead th {
        background: #212529 !important;
        color: white !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    /* Hindari baris terpotong di tengah halaman */
    #areaCetak tr {
        page-break-inside: avoid;
    }

    /* Hilangkan URL browser pada beberapa konfigurasi */
    @page {
        size: A4 landscape;
        margin: 10mm;
    }
}

/* Judul khusus cetak tidak terlihat saat normal */
.judul-cetak {
    display: none;
}
</style>


<div class="content p-4">

    <div class="no-print">

        <h2 class="fw-bold mb-4">
            <i class="fas fa-print me-2"></i>
            Laporan Peminjaman
        </h2>

        <div class="card mb-4">
            <div class="card-body">

                <form method="GET" class="row g-3">

                    <div class="col-md-4">
                        <label class="form-label">
                            Tanggal Awal
                        </label>

                        <input
                            type="date"
                            name="tgl_awal"
                            class="form-control"
                            value="<?= htmlspecialchars($tgl_awal) ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">
                            Tanggal Akhir
                        </label>

                        <input
                            type="date"
                            name="tgl_akhir"
                            class="form-control"
                            value="<?= htmlspecialchars($tgl_akhir) ?>">
                    </div>

                    <div class="col-md-4 d-flex align-items-end">

                        <button
                            type="submit"
                            class="btn btn-dark me-2">

                            <i class="fas fa-filter me-1"></i>
                            Filter

                        </button>

                        <button
                            type="button"
                            onclick="window.print()"
                            class="btn btn-secondary">

                            <i class="fas fa-print me-1"></i>
                            Cetak

                        </button>

                    </div>

                </form>

            </div>
        </div>

    </div>


    <div id="areaCetak">
        <div class="judul-cetak">

            <h2>
                LAPORAN PEMINJAMAN ALAT
            </h2>

            <p>
                Periode:
                <?= date('d/m/Y', strtotime($tgl_awal)) ?>
                -
                <?= date('d/m/Y', strtotime($tgl_akhir)) ?>
            </p>

        </div>


        <div class="card">

            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-bordered">

                        <thead class="table-dark">

                            <tr>
                                <th>Kode</th>
                                <th>Peminjam</th>
                                <th>Alat</th>
                                <th>Jumlah</th>
                                <th>Tgl Pinjam</th>
                                <th>Tgl Kembali</th>
                                <th>Status</th>
                                <th>Denda</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php if (empty($laporan)): ?>

                                <tr>
                                    <td
                                        colspan="8"
                                        class="text-center">

                                        Tidak ada data peminjaman
                                        pada periode tersebut.

                                    </td>
                                </tr>

                            <?php else: ?>

                                <?php foreach ($laporan as $l): ?>

                                    <tr>

                                        <td>
                                            <?= htmlspecialchars(
                                                $l['kode_pinjam']
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $l['nama_lengkap']
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $l['nama_alat']
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= (int) $l['jumlah'] ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $l['tanggal_pinjam']
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= !empty($l['tanggal_kembali_actual'])
                                                ? htmlspecialchars($l['tanggal_kembali_actual'])
                                                : '-' ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $l['status']
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= formatRupiah(
                                                $l['denda'] ?? 0
                                            ) ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>

</div>


<?php require_once '../includes/footer.php'; ?>