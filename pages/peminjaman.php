<?php

require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();

$isPeminjam = hasRole('peminjam');
$isPetugas  = hasRole('petugas');
$isAdmin    = hasRole('admin');


// ============================================================
// PROSES PENGAJUAN PEMINJAMAN
// ============================================================

if (
    $isPeminjam &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['ajukan'])
) {

    $idAlat = (int) ($_POST['id_alat'] ?? 0);
    $jumlah = (int) ($_POST['jumlah'] ?? 0);

    $tanggalPinjam =
        trim($_POST['tanggal_pinjam'] ?? '');

    $tanggalKembali =
        trim($_POST['tanggal_kembali_rencana'] ?? '');

    $keterangan =
        trim($_POST['keterangan'] ?? '');

    $errors = [];


    // --------------------------------------------------------
    // VALIDASI DASAR
    // --------------------------------------------------------

    if ($idAlat <= 0) {
        $errors[] = 'Silakan pilih alat.';
    }

    if ($jumlah <= 0) {
        $errors[] = 'Jumlah peminjaman harus lebih dari 0.';
    }

    if (empty($tanggalPinjam)) {
        $errors[] = 'Tanggal pinjam wajib diisi.';
    }

    if (empty($tanggalKembali)) {
        $errors[] = 'Tanggal rencana kembali wajib diisi.';
    }

    if (mb_strlen($keterangan) > 500) {
        $errors[] = 'Keterangan maksimal 500 karakter.';
    }


    // --------------------------------------------------------
    // VALIDASI TANGGAL
    // --------------------------------------------------------

    if (
        !empty($tanggalPinjam) &&
        !empty($tanggalKembali)
    ) {

        if ($tanggalKembali < $tanggalPinjam) {

            $errors[] =
                'Tanggal rencana kembali tidak boleh sebelum tanggal pinjam.';
        }
    }


    // --------------------------------------------------------
    // CEK ALAT
    // --------------------------------------------------------

    if (empty($errors)) {

        $stmt = $pdo->prepare("
            SELECT
                id,
                kode_alat,
                nama_alat,
                stok,
                stok_tersedia,
                kondisi
            FROM alat
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$idAlat]);

        $alatDipilih = $stmt->fetch();


        if (!$alatDipilih) {

            $errors[] =
                'Alat yang dipilih tidak ditemukan.';

        } elseif ($alatDipilih['stok_tersedia'] <= 0) {

            $errors[] =
                'Alat tersebut sedang tidak tersedia.';

        } elseif ($jumlah > $alatDipilih['stok_tersedia']) {

            $errors[] =
                'Jumlah yang diminta melebihi stok yang tersedia. ' .
                'Stok tersedia: ' .
                $alatDipilih['stok_tersedia'] . ' unit.';
        }
    }


    // --------------------------------------------------------
    // SIMPAN PENGAJUAN
    // --------------------------------------------------------

    if (empty($errors)) {

        try {

            $kode = generateKodePinjam();


            $stmt = $pdo->prepare("
                CALL sp_pinjam_alat(?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $kode,
                $_SESSION['user_id'],
                $idAlat,
                $jumlah,
                $tanggalPinjam,
                $tanggalKembali,
                ($keterangan !== '' ? $keterangan : null)
            ]);


            while ($stmt->nextRowset()) {
                // kosong
            }

            $stmt->closeCursor();


            logActivity(
                $pdo,
                $_SESSION['user_id'],
                "Mengajukan peminjaman: {$kode}"
            );


            flashMessage(
                'success',
                'Peminjaman berhasil diajukan dan menunggu persetujuan.'
            );


            header('Location: peminjaman.php');
            exit;

        } catch (PDOException $e) {

            flashMessage(
                'danger',
                'Peminjaman gagal diproses. Silakan periksa kembali data yang dimasukkan.'
            );

            header('Location: peminjaman.php');
            exit;
        }
    }


    if (!empty($errors)) {

        flashMessage(
            'danger',
            implode('<br>', $errors)
        );

        header('Location: peminjaman.php');
        exit;
    }
}


// ============================================================
// PROSES ADMIN / PETUGAS - SETUJUI PEMINJAMAN
// ============================================================

if (
    ($isAdmin || $isPetugas) &&
    isset($_GET['approve'])
) {

    $id = (int) $_GET['approve'];

    try {

        $pdo->beginTransaction();


        $stmt = $pdo->prepare("
            SELECT
                p.id,
                p.id_alat,
                p.jumlah,
                p.status,
                a.stok_tersedia,
                a.nama_alat
            FROM peminjaman p
            INNER JOIN alat a
                ON p.id_alat = a.id
            WHERE p.id = ?
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([$id]);

        $data = $stmt->fetch();


        if (!$data) {
            throw new Exception(
                'Data peminjaman tidak ditemukan.'
            );
        }


        if ($data['status'] !== 'menunggu') {
            throw new Exception(
                'Peminjaman tersebut sudah diproses.'
            );
        }


        if ($data['jumlah'] > $data['stok_tersedia']) {
            throw new Exception(
                'Stok alat tidak mencukupi.'
            );
        }


        $stmt = $pdo->prepare("
            UPDATE alat
            SET stok_tersedia = stok_tersedia - ?
            WHERE id = ?
              AND stok_tersedia >= ?
        ");

        $stmt->execute([
            $data['jumlah'],
            $data['id_alat'],
            $data['jumlah']
        ]);


        if ($stmt->rowCount() !== 1) {
            throw new Exception(
                'Stok alat gagal diperbarui.'
            );
        }


        $stmt = $pdo->prepare("
            UPDATE peminjaman
            SET
                status = 'disetujui',
                id_petugas = ?
            WHERE id = ?
              AND status = 'menunggu'
        ");

        $stmt->execute([
            $_SESSION['user_id'],
            $id
        ]);


        if ($stmt->rowCount() !== 1) {
            throw new Exception(
                'Status peminjaman gagal diperbarui.'
            );
        }


        $pdo->commit();


        logActivity(
            $pdo,
            $_SESSION['user_id'],
            "Menyetujui peminjaman ID: {$id}"
        );


        flashMessage(
            'success',
            'Peminjaman berhasil disetujui.'
        );

    } catch (Exception $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        flashMessage(
            'danger',
            $e->getMessage()
        );
    }


    header('Location: peminjaman.php');
    exit;
}


// ============================================================
// TOLAK PEMINJAMAN
// ============================================================

if (
    ($isAdmin || $isPetugas) &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['tolak'])
) {

    $id =
        (int) ($_POST['id_peminjaman'] ?? 0);

    $alasan =
        trim($_POST['alasan_ditolak'] ?? '');


    if ($id <= 0) {

        flashMessage(
            'danger',
            'Data peminjaman tidak valid.'
        );

        header('Location: peminjaman.php');
        exit;
    }


    if ($alasan === '') {

        flashMessage(
            'danger',
            'Alasan penolakan wajib diisi.'
        );

        header('Location: peminjaman.php');
        exit;
    }


    if (mb_strlen($alasan) > 500) {
        $alasan =
            mb_substr($alasan, 0, 500);
    }


    $stmt = $pdo->prepare("
        UPDATE peminjaman
        SET
            status = 'ditolak',
            id_petugas = ?,
            alasan_ditolak = ?
        WHERE id = ?
          AND status = 'menunggu'
    ");

    $stmt->execute([
        $_SESSION['user_id'],
        $alasan,
        $id
    ]);


    if ($stmt->rowCount() === 1) {

        logActivity(
            $pdo,
            $_SESSION['user_id'],
            "Menolak peminjaman ID: {$id}. Alasan: {$alasan}"
        );

        flashMessage(
            'success',
            'Peminjaman berhasil ditolak.'
        );

    } else {

        flashMessage(
            'danger',
            'Peminjaman tidak ditemukan atau sudah diproses.'
        );
    }


    header('Location: peminjaman.php');
    exit;
}


// ============================================================
// ADMIN / PETUGAS
// AMBIL DATA PENGAJUAN
// ============================================================

$pengajuan = [];

if ($isAdmin || $isPetugas) {

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
        WHERE p.status = 'menunggu'
        ORDER BY p.id DESC
    ");

    $pengajuan =
        $stmt->fetchAll();
}


// ============================================================
// DAFTAR ALAT TERSEDIA
// ============================================================

$alat = [];

if ($isPeminjam) {

    $stmt = $pdo->query("
        SELECT
            id,
            kode_alat,
            nama_alat,
            stok,
            stok_tersedia,
            kondisi
        FROM alat
        WHERE stok_tersedia > 0
        ORDER BY nama_alat ASC
    ");

    $alat =
        $stmt->fetchAll();
}


// ============================================================
// HEADER & SIDEBAR
// ============================================================

require_once '../includes/header.php';
require_once '../includes/sidebar.php';

?>

<div class="content p-4">

    <?php if ($isPeminjam): ?>

        <!-- ==================================================
             HALAMAN PEMINJAM
        =================================================== -->

        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>

                <h2 class="fw-bold mb-1">
                    <i class="fas fa-hand-holding me-2"></i>
                    Ajukan Peminjaman
                </h2>

                <p class="text-muted mb-0">
                    Silakan isi formulir untuk mengajukan peminjaman alat.
                </p>

            </div>

        </div>


        <?php if ($flash = getFlash()): ?>

            <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
                <?= $flash['message'] ?>
            </div>

        <?php endif; ?>


        <div class="card shadow-sm border-0">

            <div class="card-body p-4">

                <?php if (empty($alat)): ?>

                    <div class="text-center py-5">

                        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>

                        <h5>
                            Tidak ada alat yang tersedia
                        </h5>

                        <p class="text-muted mb-0">
                            Saat ini seluruh alat sedang tidak tersedia.
                        </p>

                    </div>

                <?php else: ?>

                    <form method="POST">

                        <div class="row">

                            <!-- ALAT -->

                            <div class="col-md-6 mb-3">

                                <label class="form-label fw-semibold">
                                    Pilih Alat
                                </label>

                                <select
                                    name="id_alat"
                                    id="id_alat"
                                    class="form-select"
                                    required>

                                    <option value="">
                                        -- Pilih Alat --
                                    </option>

                                    <?php foreach ($alat as $a): ?>

                                        <option
                                            value="<?= (int) $a['id'] ?>"
                                            data-stok="<?= (int) $a['stok_tersedia'] ?>">

                                            <?= htmlspecialchars($a['nama_alat']) ?>

                                            -

                                            <?= htmlspecialchars($a['kode_alat']) ?>

                                            (Tersedia:
                                            <?= (int) $a['stok_tersedia'] ?>)

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <!-- JUMLAH -->

                            <div class="col-md-6 mb-3">

                                <label class="form-label fw-semibold">
                                    Jumlah
                                </label>

                                <input
                                    type="number"
                                    name="jumlah"
                                    id="jumlah"
                                    class="form-control"
                                    min="1"
                                    value="1"
                                    required>

                                <small
                                    id="infoStok"
                                    class="text-muted">

                                    Pilih alat terlebih dahulu.

                                </small>

                            </div>


                            <!-- TANGGAL PINJAM -->

                            <div class="col-md-6 mb-3">

                                <label class="form-label fw-semibold">
                                    Tanggal Pinjam
                                </label>

                                <input
                                    type="date"
                                    name="tanggal_pinjam"
                                    id="tanggal_pinjam"
                                    class="form-control"
                                    value="<?= date('Y-m-d') ?>"
                                    min="<?= date('Y-m-d') ?>"
                                    required>

                            </div>


                            <!-- TANGGAL KEMBALI -->

                            <div class="col-md-6 mb-3">

                                <label class="form-label fw-semibold">
                                    Tanggal Rencana Kembali
                                </label>

                                <input
                                    type="date"
                                    name="tanggal_kembali_rencana"
                                    id="tanggal_kembali_rencana"
                                    class="form-control"
                                    min="<?= date('Y-m-d') ?>"
                                    required>

                            </div>


                            <!-- KETERANGAN -->

                            <div class="col-12 mb-3">

                                <label
                                    class="form-label fw-semibold"
                                    for="keterangan">

                                    <i class="fas fa-comment-alt me-1"></i>
                                    Keterangan
                                    <span class="text-muted fw-normal">(Opsional)</span>

                                </label>

                                <textarea
                                    name="keterangan"
                                    id="keterangan"
                                    class="form-control"
                                    rows="3"
                                    maxlength="500"
                                    placeholder="Contoh: digunakan untuk acara HUT RI, kondisi alat akan dijaga dengan baik..."></textarea>

                                <div class="form-text">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Catatan tambahan untuk petugas. Maksimal 500 karakter.
                                </div>

                            </div>

                        </div>


                        <hr class="my-4">


                        <div class="d-flex justify-content-end">

                            <button
                                type="reset"
                                class="btn btn-light me-2">

                                Batal

                            </button>


                            <button
                                type="submit"
                                name="ajukan"
                                class="btn btn-dark px-4">

                                <i class="fas fa-paper-plane me-1"></i>

                                Ajukan Peminjaman

                            </button>

                        </div>

                    </form>

                <?php endif; ?>

            </div>

        </div>


    <?php elseif ($isAdmin || $isPetugas): ?>

        <!-- ==================================================
             ADMIN / PETUGAS
        =================================================== -->

        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>

                <h2 class="fw-bold mb-1">

                    <i class="fas fa-clipboard-list me-2"></i>

                    Pengajuan Peminjaman

                </h2>

                <p class="text-muted mb-0">

                    Kelola pengajuan peminjaman yang menunggu persetujuan.

                </p>

            </div>

        </div>


        <?php if ($flash = getFlash()): ?>

            <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">

                <?= $flash['message'] ?>

            </div>

        <?php endif; ?>


        <div class="card shadow-sm border-0">

            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead>

                            <tr>

                                <th>Kode</th>
                                <th>Peminjam</th>
                                <th>Alat</th>
                                <th>Foto</th>
                                <th>Jumlah</th>
                                <th>Tgl Pinjam</th>
                                <th>Rencana Kembali</th>
                                <th>Keterangan</th>
                                <th>Aksi</th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php if (empty($pengajuan)): ?>

                                <tr>

                                    <td
                                        colspan="9"
                                        class="text-center text-muted py-5">

                                        <i class="fas fa-inbox fa-2x mb-2"></i>

                                        <br>

                                        Tidak ada pengajuan yang menunggu.

                                    </td>

                                </tr>

                            <?php else: ?>


                                <?php foreach ($pengajuan as $p): ?>

                                    <tr>

                                        <td>

                                            <?= htmlspecialchars(
                                                $p['kode_pinjam']
                                            ) ?>

                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                $p['nama_lengkap'] ?? '-'
                                            ) ?>

                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                $p['nama_alat']
                                            ) ?>

                                            <small class="text-muted">

                                                (
                                                <?= htmlspecialchars(
                                                    $p['kode_alat']
                                                ) ?>
                                                )

                                            </small>

                                        </td>


                                        <!-- FOTO -->

                                        <td>

                                            <?php

                                            $fotoUrl = '';

                                            if (
                                                !empty(
                                                    $p['foto_peminjaman']
                                                )
                                            ) {

                                                $fotoUrl =
                                                    '../' .
                                                    ltrim(
                                                        $p['foto_peminjaman'],
                                                        '/'
                                                    );
                                            }

                                            ?>


                                            <?php if ($fotoUrl): ?>

                                                <img
                                                    src="<?= htmlspecialchars($fotoUrl) ?>"
                                                    alt="Foto peminjaman"
                                                    class="rounded shadow-sm foto-peminjaman"
                                                    data-foto="<?= htmlspecialchars($fotoUrl) ?>"
                                                    style="
                                                        width: 60px;
                                                        height: 60px;
                                                        object-fit: cover;
                                                        cursor: pointer;
                                                    "
                                                    title="Klik untuk melihat foto">

                                            <?php else: ?>

                                                <span class="text-muted small">
                                                    Tidak ada foto
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <td>

                                            <?= (int) $p['jumlah'] ?>

                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                $p['tanggal_pinjam']
                                            ) ?>

                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                $p['tanggal_kembali_rencana']
                                            ) ?>

                                        </td>


                                        <!-- KETERANGAN -->

                                        <td style="max-width: 220px;">

                                            <?php if (!empty($p['keterangan'])): ?>

                                                <?php
                                                $ketFull = $p['keterangan'];
                                                $ketShort = mb_strlen($ketFull) > 60
                                                    ? mb_substr($ketFull, 0, 60) . '...'
                                                    : $ketFull;
                                                ?>

                                                <span
                                                    class="text-muted small keterangan-cell"
                                                    style="cursor: pointer;"
                                                    data-keterangan="<?= htmlspecialchars($ketFull) ?>"
                                                    title="Klik untuk lihat lengkap">

                                                    <?= htmlspecialchars($ketShort) ?>

                                                </span>

                                            <?php else: ?>

                                                <span class="text-muted small fst-italic">
                                                    —
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- AKSI -->

                                        <td>

                                            <!-- SETUJUI -->

                                            <button
                                                type="button"
                                                class="btn btn-sm btn-success btn-buka-setuju"
                                                data-id="<?= (int) $p['id'] ?>"
                                                title="Setujui peminjaman">

                                                <i class="fas fa-check"></i>

                                            </button>


                                            <!-- TOLAK -->

                                            <button
                                                type="button"
                                                class="btn btn-sm btn-danger btn-buka-tolak"
                                                data-id="<?= (int) $p['id'] ?>"
                                                title="Tolak dengan alasan">

                                                <i class="fas fa-times"></i>

                                            </button>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    <?php endif; ?>

</div>


<!-- ==========================================================
     MODAL SETUJUI (KONFIRMASI APPROVE)
========================================================== -->

<?php if ($isAdmin || $isPetugas): ?>

    <?php foreach ($pengajuan as $p): ?>

        <div
            class="modal-setuju-custom"
            id="modalSetuju<?= (int) $p['id'] ?>"
            aria-hidden="true">

            <div class="modal-setuju-overlay"></div>


            <div class="modal-setuju-box">

                <!-- HEADER -->

                <div class="modal-setuju-header">

                    <h5>

                        <i class="fas fa-check-circle me-2 text-success"></i>

                        Setujui Peminjaman

                    </h5>


                    <button
                        type="button"
                        class="btn-tutup-setuju"
                        data-id="<?= (int) $p['id'] ?>"
                        aria-label="Tutup">

                        &times;

                    </button>

                </div>


                <!-- BODY -->

                <div class="modal-setuju-body">

                    <div class="alert alert-success mb-3">

                        <i class="fas fa-info-circle me-1"></i>

                        Anda akan <strong>menyetujui</strong> peminjaman ini.
                        Stok alat akan otomatis berkurang.

                    </div>


                    <div class="info-setuju-box">

                        <div class="info-setuju-row">

                            <span>Kode Pinjam</span>

                            <strong>
                                <?= htmlspecialchars(
                                    $p['kode_pinjam']
                                ) ?>
                            </strong>

                        </div>


                        <div class="info-setuju-row">

                            <span>Peminjam</span>

                            <strong>
                                <?= htmlspecialchars(
                                    $p['nama_lengkap'] ?? '-'
                                ) ?>
                            </strong>

                        </div>


                        <div class="info-setuju-row">

                            <span>Alat</span>

                            <strong>
                                <?= htmlspecialchars(
                                    $p['nama_alat']
                                ) ?>
                            </strong>

                        </div>


                        <div class="info-setuju-row">

                            <span>Jumlah</span>

                            <strong>
                                <?= (int) $p['jumlah'] ?>
                                unit
                            </strong>

                        </div>


                        <div class="info-setuju-row">

                            <span>Tgl Pinjam</span>

                            <strong>
                                <?= htmlspecialchars(
                                    $p['tanggal_pinjam']
                                ) ?>
                            </strong>

                        </div>


                        <div class="info-setuju-row">

                            <span>Rencana Kembali</span>

                            <strong>
                                <?= htmlspecialchars(
                                    $p['tanggal_kembali_rencana']
                                ) ?>
                            </strong>

                        </div>


                        <?php if (!empty($p['keterangan'])): ?>

                            <div class="info-setuju-row info-setuju-keterangan">

                                <span>Keterangan</span>

                                <em class="text-muted small">
                                    <?= htmlspecialchars(
                                        $p['keterangan']
                                    ) ?>
                                </em>

                            </div>

                        <?php endif; ?>

                    </div>


                    <div class="text-center mt-3">

                        <p class="mb-0 fw-semibold">

                            Apakah Anda yakin ingin menyetujui peminjaman ini?

                        </p>

                    </div>

                </div>


                <!-- FOOTER -->

                <div class="modal-setuju-footer">

                    <button
                        type="button"
                        class="btn btn-secondary btn-tutup-setuju"
                        data-id="<?= (int) $p['id'] ?>">

                        <i class="fas fa-times me-1"></i>

                        Batal

                    </button>


                    <a
                        href="?approve=<?= (int) $p['id'] ?>"
                        class="btn btn-success">

                        <i class="fas fa-check me-1"></i>

                        Ya, Setujui

                    </a>

                </div>

            </div>

        </div>

    <?php endforeach; ?>

<?php endif; ?>


<!-- ==========================================================
     MODAL TOLAK
========================================================== -->

<?php if ($isAdmin || $isPetugas): ?>

    <?php foreach ($pengajuan as $p): ?>

        <div
            class="modal-tolak-custom"
            id="modalTolak<?= (int) $p['id'] ?>"
            aria-hidden="true">

            <div class="modal-tolak-overlay"></div>


            <div class="modal-tolak-box">

                <!-- HEADER -->

                <div class="modal-tolak-header">

                    <h5>

                        <i class="fas fa-times-circle me-2"></i>

                        Tolak Peminjaman

                    </h5>


                    <button
                        type="button"
                        class="btn-tutup-tolak"
                        data-id="<?= (int) $p['id'] ?>"
                        aria-label="Tutup">

                        &times;

                    </button>

                </div>


                <!-- FORM -->

                <form method="POST">

                    <div class="modal-tolak-body">

                        <input
                            type="hidden"
                            name="id_peminjaman"
                            value="<?= (int) $p['id'] ?>">


                        <div class="mb-3">

                            <div class="fw-semibold">

                                <?= htmlspecialchars(
                                    $p['kode_pinjam']
                                ) ?>

                            </div>


                            <div class="small text-muted">

                                <?= htmlspecialchars(
                                    $p['nama_lengkap'] ?? '-'
                                ) ?>

                                &middot;

                                <?= htmlspecialchars(
                                    $p['nama_alat']
                                ) ?>

                                (
                                <?= (int) $p['jumlah'] ?>
                                unit)

                            </div>

                        </div>


                        <!-- ALASAN -->

                        <div class="mb-3">

                            <label class="form-label fw-semibold">

                                Alasan Penolakan

                                <span class="text-danger">
                                    *
                                </span>

                            </label>


                            <textarea
                                name="alasan_ditolak"
                                class="form-control"
                                rows="3"
                                maxlength="500"
                                required></textarea>

                        </div>


                        <!-- PERINGATAN -->

                        <div class="alert alert-warning mb-0 small">

                            <i class="fas fa-info-circle me-1"></i>

                            Tindakan ini tidak dapat dibatalkan.

                        </div>

                    </div>


                    <!-- FOOTER -->

                    <div class="modal-tolak-footer">

                        <button
                            type="button"
                            class="btn btn-secondary btn-tutup-tolak"
                            data-id="<?= (int) $p['id'] ?>">
                            

                            Batal

                        </button>


                        <button
                            type="submit"
                            name="tolak"
                            class="btn btn-danger">

                            <i class="fas fa-times me-1"></i>

                            Tolak Peminjaman

                        </button>

                    </div>

                </form>

            </div>

        </div>

    <?php endforeach; ?>

<?php endif; ?>


<!-- ==========================================================
     MODAL FOTO
========================================================== -->

<?php if ($isAdmin || $isPetugas): ?>

    <div
        class="modal fade"
        id="fotoModal"
        tabindex="-1"
        aria-labelledby="fotoModalLabel"
        aria-hidden="true">

        <div class="modal-dialog modal-dialog-centered modal-lg">

            <div class="modal-content border-0 shadow">

                <div class="modal-header">

                    <h5
                        class="modal-title"
                        id="fotoModalLabel">

                        <i class="fas fa-image me-2"></i>

                        Foto Peminjaman

                    </h5>


                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                    </button>

                </div>


                <div class="modal-body text-center p-2">

                    <img
                        id="modalFoto"
                        src=""
                        alt="Foto peminjaman"
                        class="img-fluid rounded"
                        style="
                            max-height: 75vh;
                            object-fit: contain;
                        ">

                </div>

            </div>

        </div>

    </div>


    <!-- ==========================================================
         MODAL KETERANGAN
    ========================================================== -->

    <div
        class="modal fade"
        id="keteranganModal"
        tabindex="-1"
        aria-hidden="true">

        <div class="modal-dialog modal-dialog-centered">

            <div class="modal-content border-0 shadow">

                <div class="modal-header">

                    <h5 class="modal-title">
                        <i class="fas fa-comment-alt me-2"></i>
                        Keterangan Peminjaman
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"></button>

                </div>

                <div class="modal-body">

                    <p
                        id="modalKeterangan"
                        class="mb-0"
                        style="white-space: pre-wrap; word-break: break-word;"></p>

                </div>

                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">

                        Tutup

                    </button>

                </div>

            </div>

        </div>

    </div>

<?php endif; ?>


<!-- ==========================================================
     CSS MODAL SETUJUI & TOLAK
========================================================== -->

<style>

    /*
     * ========================================================
     * MODAL SETUJUI CUSTOM (dengan border hijau)
     * ========================================================
     */

    .modal-setuju-custom {

        display: none;

        position: fixed;

        top: 0;
        left: 0;
        right: 0;
        bottom: 0;

        width: 100vw;
        height: 100vh;

        z-index: 999999;

        align-items: center;
        justify-content: center;
    }


    .modal-setuju-custom.active {

        display: flex;
    }


    .modal-setuju-overlay {

        position: absolute;

        top: 0;
        left: 0;

        width: 100%;
        height: 100%;

        background: rgba(0, 0, 0, 0.55);

        z-index: 1;

        cursor: pointer;
    }


    .modal-setuju-box {

        position: relative;

        z-index: 2;

        width: 520px;

        max-width: calc(100vw - 30px);

        max-height: calc(100vh - 30px);

        background: #fff;

        border: 3px solid #198754;

        border-radius: 12px;

        box-shadow:
            0 20px 60px rgba(25, 135, 84, 0.35);

        overflow: hidden;

        display: flex;

        flex-direction: column;
    }


    .modal-setuju-header {

        display: flex;

        align-items: center;

        justify-content: space-between;

        padding: 18px 20px;

        border-bottom: 1px solid #dee2e6;

        background: #f0fff4;

        flex-shrink: 0;
    }


    .modal-setuju-header h5 {

        margin: 0;

        font-weight: 600;
    }


    .btn-tutup-setuju {

        border: none;

        background: transparent;

        font-size: 30px;

        line-height: 1;

        color: #6c757d;

        cursor: pointer;

        padding: 0 5px;

        transition: color 0.15s ease;
    }


    .btn-tutup-setuju:hover {

        color: #000;
    }


    .modal-setuju-body {

        padding: 20px;

        overflow-y: auto;
    }


    .modal-setuju-footer {

        display: flex;

        justify-content: flex-end;

        gap: 8px;

        padding: 15px 20px;

        border-top: 1px solid #dee2e6;

        background: #f8f9fa;

        flex-shrink: 0;
    }


    /* ============================================
     * INFO BOX di dalam modal setujui
     * ============================================ */

    .info-setuju-box {

        border: 1px solid #e0e0e0;

        border-radius: 8px;

        padding: 12px 15px;

        background: #fafafa;
    }


    .info-setuju-row {

        display: flex;

        justify-content: space-between;

        align-items: flex-start;

        padding: 5px 0;

        border-bottom: 1px dashed #e0e0e0;

        font-size: 0.9rem;

        gap: 12px;
    }


    .info-setuju-row:last-child {

        border-bottom: none;
    }


    .info-setuju-row span {

        color: #6c757d;

        flex-shrink: 0;
    }


    .info-setuju-row strong {

        text-align: right;

        word-break: break-word;
    }


    .info-setuju-keterangan em {

        text-align: right;

        display: block;

        max-width: 70%;
    }


    /*
     * ========================================================
     * MODAL TOLAK CUSTOM
     * ========================================================
     */

    .modal-tolak-custom {

        display: none;

        position: fixed;

        top: 0;
        left: 0;
        right: 0;
        bottom: 0;

        width: 100vw;
        height: 100vh;

        z-index: 999999;

        align-items: center;
        justify-content: center;
    }


    .modal-tolak-custom.active {

        display: flex;
    }


    .modal-tolak-overlay {

        position: absolute;

        top: 0;
        left: 0;

        width: 100%;
        height: 100%;

        background: rgba(0, 0, 0, 0.55);

        z-index: 1;

        cursor: pointer;
    }


    .modal-tolak-box {

        position: relative;

        z-index: 2;

        width: 500px;

        max-width: calc(100vw - 30px);

        max-height: calc(100vh - 30px);

        background: #fff;

        border-radius: 12px;

        box-shadow:
            0 20px 60px rgba(0, 0, 0, 0.30);

        overflow: hidden;

        display: flex;

        flex-direction: column;
    }


    .modal-tolak-header {

        display: flex;

        align-items: center;

        justify-content: space-between;

        padding: 18px 20px;

        border-bottom: 1px solid #dee2e6;

        flex-shrink: 0;
    }


    .modal-tolak-header h5 {

        margin: 0;

        font-weight: 600;
    }


    .btn-tutup-tolak {

        border: none;

        background: transparent;

        font-size: 30px;

        line-height: 1;

        color: #6c757d;

        cursor: pointer;

        padding: 0 5px;

        transition: color 0.15s ease;
    }


    .btn-tutup-tolak:hover {

        color: #000;
    }


    .modal-tolak-body {

        padding: 20px;

        overflow-y: auto;
    }


    .modal-tolak-footer {

        display: flex;

        justify-content: flex-end;

        gap: 8px;

        padding: 15px 20px;

        border-top: 1px solid #dee2e6;

        flex-shrink: 0;
    }


    body.modal-custom-open {

        overflow: hidden;
    }


    .modal-setuju-custom,
    .modal-setuju-custom *,
    .modal-tolak-custom,
    .modal-tolak-custom * {

        box-sizing: border-box;
    }


    @media (max-width: 576px) {

        .modal-setuju-box,
        .modal-tolak-box {

            width: calc(100vw - 20px);

            max-width: calc(100vw - 20px);
        }

    }

</style>


<!-- ==========================================================
     JAVASCRIPT PEMINJAM
========================================================== -->

<?php if ($isPeminjam): ?>

    <script>

        document.addEventListener(
            'DOMContentLoaded',
            function () {

                const alatSelect =
                    document.getElementById('id_alat');

                const jumlahInput =
                    document.getElementById('jumlah');

                const infoStok =
                    document.getElementById('infoStok');

                const tanggalPinjam =
                    document.getElementById('tanggal_pinjam');

                const tanggalKembali =
                    document.getElementById(
                        'tanggal_kembali_rencana'
                    );


                if (
                    !alatSelect ||
                    !jumlahInput ||
                    !infoStok ||
                    !tanggalPinjam ||
                    !tanggalKembali
                ) {
                    return;
                }


                function updateStok() {

                    const option =
                        alatSelect.options[
                            alatSelect.selectedIndex
                        ];


                    if (
                        !option ||
                        !option.dataset.stok
                    ) {

                        infoStok.textContent =
                            'Pilih alat terlebih dahulu.';

                        jumlahInput.removeAttribute('max');

                        return;
                    }


                    const stok =
                        parseInt(
                            option.dataset.stok
                        );


                    jumlahInput.max =
                        stok;


                    infoStok.textContent =
                        'Maksimal peminjaman: ' +
                        stok +
                        ' unit.';


                    if (
                        parseInt(
                            jumlahInput.value
                        ) > stok
                    ) {

                        jumlahInput.value =
                            stok;
                    }

                }


                alatSelect.addEventListener(
                    'change',
                    updateStok
                );


                jumlahInput.addEventListener(
                    'input',
                    updateStok
                );


                tanggalPinjam.addEventListener(
                    'change',
                    function () {

                        tanggalKembali.min =
                            tanggalPinjam.value;


                        if (
                            tanggalKembali.value &&
                            tanggalKembali.value <
                            tanggalPinjam.value
                        ) {

                            tanggalKembali.value =
                                tanggalPinjam.value;
                        }

                    }
                );


                updateStok();

            }
        );

    </script>

<?php endif; ?>


<!-- ==========================================================
     JAVASCRIPT MODAL SETUJUI
========================================================== -->

<?php if ($isAdmin || $isPetugas): ?>

    <script>

        document.addEventListener(
            'DOMContentLoaded',
            function () {

                // =================================================
                // BUKA MODAL SETUJUI
                // =================================================

                const tombolBukaSetuju =
                    document.querySelectorAll(
                        '.btn-buka-setuju'
                    );


                tombolBukaSetuju.forEach(
                    function (button) {

                        button.addEventListener(
                            'click',
                            function (event) {

                                event.preventDefault();

                                event.stopPropagation();


                                const id =
                                    this.getAttribute(
                                        'data-id'
                                    );


                                const modal =
                                    document.getElementById(
                                        'modalSetuju' + id
                                    );


                                if (!modal) {
                                    return;
                                }


                                modal.classList.add(
                                    'active'
                                );


                                modal.setAttribute(
                                    'aria-hidden',
                                    'false'
                                );


                                document.body.classList.add(
                                    'modal-custom-open'
                                );

                            }
                        );

                    }
                );


                // =================================================
                // TUTUP MODAL SETUJUI
                // =================================================

                const tombolTutupSetuju =
                    document.querySelectorAll(
                        '.btn-tutup-setuju'
                    );


                tombolTutupSetuju.forEach(
                    function (button) {

                        button.addEventListener(
                            'click',
                            function (event) {

                                event.preventDefault();

                                event.stopPropagation();


                                const id =
                                    this.getAttribute(
                                        'data-id'
                                    );


                                const modal =
                                    document.getElementById(
                                        'modalSetuju' + id
                                    );


                                if (!modal) {
                                    return;
                                }


                                modal.classList.remove(
                                    'active'
                                );


                                modal.setAttribute(
                                    'aria-hidden',
                                    'true'
                                );


                                document.body.classList.remove(
                                    'modal-custom-open'
                                );

                            }
                        );

                    }
                );


                // =================================================
                // KLIK BACKDROP MODAL SETUJUI
                // =================================================

                const overlaySetuju =
                    document.querySelectorAll(
                        '.modal-setuju-overlay'
                    );


                overlaySetuju.forEach(
                    function (item) {

                        item.addEventListener(
                            'click',
                            function (event) {

                                event.preventDefault();

                                event.stopPropagation();


                                const modal =
                                    this.closest(
                                        '.modal-setuju-custom'
                                    );


                                if (!modal) {
                                    return;
                                }


                                modal.classList.remove(
                                    'active'
                                );


                                modal.setAttribute(
                                    'aria-hidden',
                                    'true'
                                );


                                document.body.classList.remove(
                                    'modal-custom-open'
                                );

                            }
                        );

                    }
                );

            }
        );

    </script>

<?php endif; ?>


<!-- ==========================================================
     JAVASCRIPT MODAL TOLAK
========================================================== -->

<?php if ($isAdmin || $isPetugas): ?>

    <script>

        document.addEventListener(
            'DOMContentLoaded',
            function () {

                // =================================================
                // BUKA MODAL TOLAK
                // =================================================

                const tombolBuka =
                    document.querySelectorAll(
                        '.btn-buka-tolak'
                    );


                tombolBuka.forEach(
                    function (button) {

                        button.addEventListener(
                            'click',
                            function (event) {

                                event.preventDefault();

                                event.stopPropagation();


                                const id =
                                    this.getAttribute(
                                        'data-id'
                                    );


                                const modal =
                                    document.getElementById(
                                        'modalTolak' + id
                                    );


                                if (!modal) {
                                    return;
                                }


                                modal.classList.add(
                                    'active'
                                );


                                modal.setAttribute(
                                    'aria-hidden',
                                    'false'
                                );


                                document.body.classList.add(
                                    'modal-custom-open'
                                );


                                const textarea =
                                    modal.querySelector(
                                        'textarea[name="alasan_ditolak"]'
                                    );


                                if (textarea) {

                                    setTimeout(
                                        function () {

                                            textarea.focus();

                                        },
                                        50
                                    );

                                }

                            }
                        );

                    }
                );


                // =================================================
                // TUTUP MODAL TOLAK
                // =================================================

                const tombolTutup =
                    document.querySelectorAll(
                        '.btn-tutup-tolak'
                    );


                tombolTutup.forEach(
                    function (button) {

                        button.addEventListener(
                            'click',
                            function (event) {

                                event.preventDefault();

                                event.stopPropagation();


                                const id =
                                    this.getAttribute(
                                        'data-id'
                                    );


                                const modal =
                                    document.getElementById(
                                        'modalTolak' + id
                                    );


                                if (!modal) {
                                    return;
                                }


                                modal.classList.remove(
                                    'active'
                                );


                                modal.setAttribute(
                                    'aria-hidden',
                                    'true'
                                );


                                document.body.classList.remove(
                                    'modal-custom-open'
                                );

                            }
                        );

                    }
                );


                // =================================================
                // KLIK BACKDROP MODAL TOLAK
                // =================================================

                const overlay =
                    document.querySelectorAll(
                        '.modal-tolak-overlay'
                    );


                overlay.forEach(
                    function (item) {

                        item.addEventListener(
                            'click',
                            function (event) {

                                event.preventDefault();

                                event.stopPropagation();


                                const modal =
                                    this.closest(
                                        '.modal-tolak-custom'
                                    );


                                if (!modal) {
                                    return;
                                }


                                modal.classList.remove(
                                    'active'
                                );


                                modal.setAttribute(
                                    'aria-hidden',
                                    'true'
                                );


                                document.body.classList.remove(
                                    'modal-custom-open'
                                );

                            }
                        );

                    }
                );


                // =================================================
                // ESC UNTUK MENUTUP SEMUA MODAL CUSTOM
                // =================================================

                document.addEventListener(
                    'keydown',
                    function (event) {

                        if (
                            event.key !== 'Escape'
                        ) {
                            return;
                        }


                        const modalAktif =
                            document.querySelector(
                                '.modal-setuju-custom.active, .modal-tolak-custom.active'
                            );


                        if (!modalAktif) {
                            return;
                        }


                        modalAktif.classList.remove(
                            'active'
                        );


                        modalAktif.setAttribute(
                            'aria-hidden',
                            'true'
                        );


                        document.body.classList.remove(
                            'modal-custom-open'
                        );

                    }
                );

            }
        );

    </script>

<?php endif; ?>


<!-- ==========================================================
     JAVASCRIPT FOTO + KETERANGAN PEMINJAMAN
========================================================== -->

<?php if ($isAdmin || $isPetugas): ?>

    <script>

        document.addEventListener(
            'DOMContentLoaded',
            function () {

                // ==========================================
                // MODAL FOTO
                // ==========================================

                const fotoElements =
                    document.querySelectorAll(
                        '.foto-peminjaman'
                    );

                const modalFoto =
                    document.getElementById(
                        'modalFoto'
                    );

                const fotoModalElement =
                    document.getElementById(
                        'fotoModal'
                    );


                if (
                    modalFoto &&
                    fotoModalElement &&
                    typeof bootstrap !== 'undefined'
                ) {

                    const fotoModal =
                        bootstrap.Modal.getOrCreateInstance(
                            fotoModalElement
                        );


                    fotoElements.forEach(
                        function (img) {

                            img.addEventListener(
                                'click',
                                function () {

                                    modalFoto.src =
                                        this.dataset.foto;

                                    fotoModal.show();

                                }
                            );

                        }
                    );


                    fotoModalElement.addEventListener(
                        'hidden.bs.modal',
                        function () {

                            modalFoto.src = '';

                        }
                    );
                }


                // ==========================================
                // MODAL KETERANGAN
                // ==========================================

                const keteranganCells =
                    document.querySelectorAll(
                        '.keterangan-cell'
                    );

                const modalKeteranganEl =
                    document.getElementById(
                        'keteranganModal'
                    );

                const modalKeteranganBody =
                    document.getElementById(
                        'modalKeterangan'
                    );


                if (
                    modalKeteranganEl &&
                    modalKeteranganBody &&
                    keteranganCells.length > 0 &&
                    typeof bootstrap !== 'undefined'
                ) {

                    const keteranganModal =
                        bootstrap.Modal.getOrCreateInstance(
                            modalKeteranganEl
                        );


                    keteranganCells.forEach(
                        function (el) {

                            el.addEventListener(
                                'click',
                                function () {

                                    modalKeteranganBody.textContent =
                                        this.dataset.keterangan;

                                    keteranganModal.show();

                                }
                            );

                        }
                    );

                }

            }
        );

    </script>

<?php endif; ?>


<?php require_once '../includes/footer.php'; ?>