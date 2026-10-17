<?php

require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();

require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$isPeminjam = hasRole('peminjam');
$isAdmin    = hasRole('admin');
$isPetugas  = hasRole('petugas');


// ============================================================
// AMBIL RIWAYAT
// ============================================================

if ($isPeminjam) {

    /*
     * Peminjam hanya boleh melihat riwayat miliknya sendiri.
     */

    $stmt = $pdo->prepare("
        SELECT
            p.*,
            a.nama_alat,
            a.kode_alat
        FROM peminjaman p
        INNER JOIN alat a
            ON p.id_alat = a.id
        WHERE p.id_user = ?
        ORDER BY p.id DESC
    ");

    $stmt->execute([
        $_SESSION['user_id']
    ]);
} else {

    /*
     * Admin dan petugas dapat melihat seluruh riwayat.
     */

    $stmt = $pdo->query("
        SELECT
            p.*,
            a.nama_alat,
            a.kode_alat,
            u.nama_lengkap
        FROM peminjaman p
        INNER JOIN alat a
            ON p.id_alat = a.id
        INNER JOIN users u
            ON p.id_user = u.id
        ORDER BY p.id DESC
    ");
}


$riwayat = $stmt->fetchAll();

?>

<div class="content p-4">

    <!-- ======================================================
         HEADER
    ======================================================= -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2 class="fw-bold mb-1">
                <i class="fas fa-history me-2"></i>
                Riwayat Peminjaman
            </h2>

            <p class="text-muted mb-0">
                <?= $isPeminjam
                    ? 'Daftar seluruh pengajuan dan peminjaman yang pernah kamu lakukan.'
                    : 'Daftar seluruh transaksi peminjaman alat.'
                ?>
            </p>
        </div>

    </div>


    <!-- ======================================================
         CARD
    ======================================================= -->

    <div class="card shadow-sm border-0">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead>

                        <tr>

                            <th>Kode</th>

                            <?php if (!$isPeminjam): ?>

                                <th>Peminjam</th>

                            <?php endif; ?>

                            <th>Alat</th>

                            <th>Jumlah</th>

                            <th>Tgl Pinjam</th>

                            <th>Rencana Kembali</th>

                            <th>Status</th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php if (empty($riwayat)): ?>

                            <tr>

                                <td
                                    colspan="<?= $isPeminjam ? 6 : 7 ?>"
                                    class="text-center py-5 text-muted">

                                    <i class="fas fa-history fa-2x mb-3"></i>

                                    <br>

                                    Belum ada riwayat peminjaman.

                                </td>

                            </tr>


                        <?php else: ?>


                            <?php foreach ($riwayat as $p): ?>

                                <tr>

                                    <!-- KODE -->

                                    <td>

                                        <span class="fw-semibold">

                                            <?= htmlspecialchars(
                                                $p['kode_pinjam']
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- PEMINJAM -->

                                    <?php if (!$isPeminjam): ?>

                                        <td>

                                            <?= htmlspecialchars(
                                                $p['nama_lengkap'] ?? '-'
                                            ) ?>

                                        </td>

                                    <?php endif; ?>


                                    <!-- ALAT -->

                                    <td>

                                        <div class="fw-semibold">

                                            <?= htmlspecialchars(
                                                $p['nama_alat']
                                            ) ?>

                                        </div>

                                        <small class="text-muted">

                                            <?= htmlspecialchars(
                                                $p['kode_alat']
                                            ) ?>

                                        </small>

                                    </td>


                                    <!-- JUMLAH -->

                                    <td>

                                        <?= (int) $p['jumlah'] ?>

                                        unit

                                    </td>


                                    <!-- TANGGAL PINJAM -->

                                    <td>

                                        <?= htmlspecialchars(
                                            $p['tanggal_pinjam']
                                        ) ?>

                                    </td>


                                    <!-- TANGGAL KEMBALI -->

                                    <td>

                                        <?= htmlspecialchars(
                                            $p['tanggal_kembali_rencana']
                                        ) ?>

                                    </td>


                                    <!-- STATUS -->

                                    <td>

                                        <?php

                                        $status =
                                            strtolower(
                                                trim(
                                                    $p['status']
                                                )
                                            );


                                        switch ($status) {

                                            case 'menunggu':

                                                $badge = 'warning text-dark';
                                                $label = 'Menunggu';

                                                break;


                                            case 'disetujui':

                                                $badge = 'info';
                                                $label = 'Disetujui';

                                                break;


                                            case 'dipinjam':

                                                $badge = 'primary';
                                                $label = 'Sedang Dipinjam';

                                                break;


                                            case 'dikembalikan':

                                                $badge = 'success';
                                                $label = 'Dikembalikan';

                                                break;


                                            case 'ditolak':

                                                $badge = 'danger';
                                                $label = 'Ditolak';

                                                break;


                                            default:

                                                $badge = 'secondary';
                                                $label = ucfirst(
                                                    $status
                                                );

                                                break;
                                        }

                                        ?>


                                        <span
                                            class="badge bg-<?= $badge ?>">

                                            <?= htmlspecialchars(
                                                $label
                                            ) ?>

                                        </span>
                                        <?php if ($p['status'] === 'ditolak' && !empty($p['alasan_ditolak'])): ?>
                                            <div class="alert alert-danger small mb-0 mt-2">
                                                <strong>Alasan ditolak:</strong>
                                                <?= htmlspecialchars($p['alasan_ditolak']) ?>
                                            </div>
                                        <?php endif; ?>
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


<?php require_once '../includes/footer.php'; ?>