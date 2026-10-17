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

    $tanggalPinjam = trim(
        $_POST['tanggal_pinjam'] ?? ''
    );

    $tanggalKembali = trim(
        $_POST['tanggal_kembali_rencana'] ?? ''
    );

    $keterangan = trim(
        $_POST['keterangan'] ?? ''
    );

    $errors = [];

    // ========================================================
    // VALIDASI DASAR
    // ========================================================

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


    // ========================================================
    // VALIDASI TANGGAL
    // ========================================================

    $hariIni = date('Y-m-d');

    if (!empty($tanggalPinjam)) {

        if ($tanggalPinjam < $hariIni) {
            $errors[] = 'Tanggal pinjam tidak boleh sebelum hari ini.';
        }
    }

    if (
        !empty($tanggalPinjam) &&
        !empty($tanggalKembali)
    ) {

        if ($tanggalKembali < $tanggalPinjam) {

            $errors[] =
                'Tanggal rencana kembali tidak boleh sebelum tanggal pinjam.';
        }
    }


    // ========================================================
    // CEK ALAT
    // ========================================================

    $alatDipilih = null;

    if (empty($errors)) {

        try {

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

            $stmt->execute([
                $idAlat
            ]);

            $alatDipilih = $stmt->fetch();

            if (!$alatDipilih) {

                $errors[] =
                    'Alat yang dipilih tidak ditemukan.';
            } elseif (
                (int) $alatDipilih['stok_tersedia'] <= 0
            ) {

                $errors[] =
                    'Alat tersebut sedang tidak tersedia.';
            } elseif (
                $jumlah >
                (int) $alatDipilih['stok_tersedia']
            ) {

                $errors[] =
                    'Jumlah yang diminta melebihi stok yang tersedia. ' .
                    'Stok tersedia: ' .
                    (int) $alatDipilih['stok_tersedia'] .
                    ' unit.';
            }
        } catch (PDOException $e) {

            $errors[] =
                'Gagal memeriksa alat: ' .
                $e->getMessage();
        }
    }


    // ========================================================
    // FOTO PEMINJAMAN
    // FOTO SEKARANG OPSIONAL
    // ========================================================

    $fotoPath = null;
    $fotoUploadedPath = null;
    $mimeType = null;

    if (
        empty($errors) &&
        isset($_FILES['foto_peminjaman']) &&
        $_FILES['foto_peminjaman']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        $foto = $_FILES['foto_peminjaman'];

        // ----------------------------------------------------
        // CEK ERROR UPLOAD
        // ----------------------------------------------------

        if ($foto['error'] !== UPLOAD_ERR_OK) {

            $errors[] =
                'Foto gagal diunggah. Kode error upload: ' .
                $foto['error'];
        } else {

            // ------------------------------------------------
            // BATAS UKURAN 5 MB
            // ------------------------------------------------

            if (
                $foto['size'] >
                (5 * 1024 * 1024)
            ) {

                $errors[] =
                    'Ukuran foto maksimal 5 MB.';
            }


            // ------------------------------------------------
            // CEK MIME TYPE
            // ------------------------------------------------

            $allowedTypes = [
                'image/jpeg',
                'image/png',
                'image/webp'
            ];

            $finfo = finfo_open(
                FILEINFO_MIME_TYPE
            );

            if ($finfo !== false) {

                $mimeType = finfo_file(
                    $finfo,
                    $foto['tmp_name']
                );

                finfo_close($finfo);
            } else {

                $errors[] =
                    'Sistem tidak dapat memeriksa tipe foto.';
            }


            if (
                $mimeType !== null &&
                !in_array(
                    $mimeType,
                    $allowedTypes,
                    true
                )
            ) {

                $errors[] =
                    'Format foto harus JPG, PNG, atau WEBP.';
            }


            // ------------------------------------------------
            // CEK GAMBAR VALID
            // ------------------------------------------------

            if (
                empty($errors) &&
                @getimagesize(
                    $foto['tmp_name']
                ) === false
            ) {

                $errors[] =
                    'File yang diunggah bukan gambar yang valid.';
            }
        }
    }


    // ========================================================
    // SIMPAN DATA
    // ========================================================

    if (empty($errors)) {

        try {

            // =================================================
            // GENERATE KODE PEMINJAMAN
            // =================================================

            $kode = generateKodePinjam();


            // =================================================
            // FOLDER UPLOAD
            // =================================================

            $uploadDir =
                __DIR__ .
                '/../uploads/peminjaman/';


            // =================================================
            // UPLOAD FOTO JIKA ADA
            // =================================================

            if (
                isset($_FILES['foto_peminjaman']) &&
                $_FILES['foto_peminjaman']['error'] === UPLOAD_ERR_OK
            ) {

                if (!is_dir($uploadDir)) {

                    if (
                        !mkdir(
                            $uploadDir,
                            0755,
                            true
                        )
                    ) {

                        throw new Exception(
                            'Folder upload foto tidak dapat dibuat.'
                        );
                    }
                }


                // --------------------------------------------
                // EKSTENSI FILE
                // --------------------------------------------

                $extensions = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp'
                ];

                $extension =
                    $extensions[$mimeType] ?? null;


                if (!$extension) {

                    throw new Exception(
                        'Format foto tidak didukung.'
                    );
                }


                // --------------------------------------------
                // NAMA FILE
                // --------------------------------------------

                $fileName =
                    $kode .
                    '_' .
                    bin2hex(
                        random_bytes(5)
                    ) .
                    '.' .
                    $extension;


                $destination =
                    $uploadDir .
                    $fileName;


                // --------------------------------------------
                // PINDAHKAN FOTO
                // --------------------------------------------

                if (
                    !move_uploaded_file(
                        $_FILES['foto_peminjaman']['tmp_name'],
                        $destination
                    )
                ) {

                    throw new Exception(
                        'Foto gagal diunggah ke server.'
                    );
                }


                $fotoUploadedPath =
                    $destination;

                $fotoPath =
                    'uploads/peminjaman/' .
                    $fileName;
            }


            // =================================================
            // TRANSAKSI DATABASE
            // =================================================

            $pdo->beginTransaction();


            // =================================================
            // CEK ULANG STOK
            // =================================================

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    nama_alat,
                    stok_tersedia
                FROM alat
                WHERE id = ?
                LIMIT 1
                FOR UPDATE
            ");

            $stmt->execute([
                $idAlat
            ]);

            $alat = $stmt->fetch();


            if (!$alat) {

                throw new Exception(
                    'Alat tidak ditemukan.'
                );
            }


            if (
                (int) $alat['stok_tersedia'] <= 0
            ) {

                throw new Exception(
                    'Stok alat sudah tidak tersedia.'
                );
            }


            if (
                $jumlah >
                (int) $alat['stok_tersedia']
            ) {

                throw new Exception(
                    'Stok alat tidak mencukupi. ' .
                        'Stok tersedia: ' .
                        (int) $alat['stok_tersedia'] .
                        ' unit.'
                );
            }


            // =================================================
            // SIMPAN PEMINJAMAN
            // =================================================
            //
            // Status awal:
            // menunggu
            //
            // Stok TIDAK dikurangi di sini.
            // Pengurangan stok dilakukan ketika
            // peminjaman diproses sesuai alur sistem.
            // =================================================

            $stmt = $pdo->prepare("
                INSERT INTO peminjaman (
                    kode_pinjam,
                    id_user,
                    id_alat,
                    jumlah,
                    tanggal_pinjam,
                    tanggal_kembali_rencana,
                    status,
                    id_petugas,
                    foto_peminjaman,
                    keterangan
                )
                VALUES (
                    ?, ?, ?, ?, ?, ?, 'menunggu', NULL, ?, ?
                )
            ");


            $stmt->execute([
                $kode,
                $_SESSION['user_id'],
                $idAlat,
                $jumlah,
                $tanggalPinjam,
                $tanggalKembali,
                $fotoPath,
                ($keterangan !== '' ? $keterangan : null)
            ]);


            // =================================================
            // PASTIKAN INSERT BERHASIL
            // =================================================

            if (
                $stmt->rowCount() !== 1
            ) {

                throw new Exception(
                    'Data peminjaman gagal disimpan.'
                );
            }


            // =================================================
            // COMMIT
            // =================================================

            $pdo->commit();


            // =================================================
            // LOG AKTIVITAS
            // =================================================

            try {

                logActivity(
                    $pdo,
                    $_SESSION['user_id'],
                    "Mengajukan peminjaman: {$kode}"
                );
            } catch (Throwable $logError) {

                // Jangan menggagalkan peminjaman
                // jika log gagal.
            }


            // =================================================
            // PESAN SUKSES
            // =================================================

            flashMessage(
                'success',
                'Peminjaman berhasil diajukan dan menunggu persetujuan.'
            );


            header(
                'Location: ajukan.php'
            );

            exit;
        } catch (PDOException $e) {

            // =================================================
            // ROLLBACK DATABASE
            // =================================================

            if (
                $pdo->inTransaction()
            ) {

                $pdo->rollBack();
            }


            // =================================================
            // HAPUS FOTO JIKA DATABASE GAGAL
            // =================================================

            if (
                $fotoUploadedPath &&
                file_exists($fotoUploadedPath)
            ) {

                @unlink(
                    $fotoUploadedPath
                );
            }


            // =================================================
            // PESAN ERROR DATABASE
            // =================================================

            flashMessage(
                'danger',
                'Peminjaman gagal disimpan. Error database: ' .
                    $e->getMessage()
            );


            header(
                'Location: ajukan.php'
            );

            exit;
        } catch (Exception $e) {

            // =================================================
            // ROLLBACK
            // =================================================

            if (
                $pdo->inTransaction()
            ) {

                $pdo->rollBack();
            }


            // =================================================
            // HAPUS FOTO JIKA PROSES GAGAL
            // =================================================

            if (
                $fotoUploadedPath &&
                file_exists($fotoUploadedPath)
            ) {

                @unlink(
                    $fotoUploadedPath
                );
            }


            // =================================================
            // PESAN ERROR
            // =================================================

            flashMessage(
                'danger',
                'Peminjaman gagal: ' .
                    $e->getMessage()
            );


            header(
                'Location: ajukan.php'
            );

            exit;
        }
    }


    // ========================================================
    // VALIDASI GAGAL
    // ========================================================

    if (!empty($errors)) {

        flashMessage(
            'danger',
            implode('<br>', $errors)
        );


        header(
            'Location: ajukan.php'
        );

        exit;
    }
}


// ============================================================
// PROSES ADMIN / PETUGAS - APPROVE
// ============================================================

if (
    ($isAdmin || $isPetugas) &&
    isset($_GET['approve'])
) {

    $id = (int) $_GET['approve'];


    try {

        $pdo->beginTransaction();


        // ====================================================
        // AMBIL DATA PEMINJAMAN
        // ====================================================

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


        $stmt->execute([
            $id
        ]);


        $data = $stmt->fetch();


        if (!$data) {

            throw new Exception(
                'Data peminjaman tidak ditemukan.'
            );
        }


        // ====================================================
        // CEK STATUS
        // ====================================================

        if (
            strtolower(
                trim($data['status'])
            ) !== 'menunggu'
        ) {

            throw new Exception(
                'Peminjaman tersebut sudah diproses.'
            );
        }


        // ====================================================
        // CEK STOK
        // ====================================================

        if (
            (int) $data['jumlah'] >
            (int) $data['stok_tersedia']
        ) {

            throw new Exception(
                'Stok alat tidak mencukupi.'
            );
        }


        // ====================================================
        // KURANGI STOK
        // ====================================================

        $stmt = $pdo->prepare("
            UPDATE alat
            SET stok_tersedia =
                stok_tersedia - ?
            WHERE id = ?
              AND stok_tersedia >= ?
        ");


        $stmt->execute([
            $data['jumlah'],
            $data['id_alat'],
            $data['jumlah']
        ]);


        if (
            $stmt->rowCount() !== 1
        ) {

            throw new Exception(
                'Stok alat gagal diperbarui.'
            );
        }


        // ====================================================
        // UBAH STATUS
        // ====================================================

        $stmt = $pdo->prepare("
            UPDATE peminjaman
            SET
                status = 'dipinjam',
                id_petugas = ?
            WHERE id = ?
              AND status = 'menunggu'
        ");


        $stmt->execute([
            $_SESSION['user_id'],
            $id
        ]);


        if (
            $stmt->rowCount() !== 1
        ) {

            throw new Exception(
                'Status peminjaman gagal diperbarui.'
            );
        }


        // ====================================================
        // COMMIT
        // ====================================================

        $pdo->commit();


        // ====================================================
        // LOG
        // ====================================================

        try {

            logActivity(
                $pdo,
                $_SESSION['user_id'],
                "Menyetujui peminjaman ID: {$id}"
            );
        } catch (Throwable $logError) {
            // Abaikan jika log gagal.
        }


        flashMessage(
            'success',
            'Peminjaman berhasil disetujui.'
        );
    } catch (Exception $e) {

        if (
            $pdo->inTransaction()
        ) {

            $pdo->rollBack();
        }


        flashMessage(
            'danger',
            $e->getMessage()
        );
    }


    header(
        'Location: ajukan.php'
    );

    exit;
}


// ============================================================
// TOLAK PEMINJAMAN
// ============================================================

if (
    ($isAdmin || $isPetugas) &&
    isset($_GET['reject'])
) {

    $id = (int) $_GET['reject'];


    try {

        $stmt = $pdo->prepare("
            UPDATE peminjaman
            SET
                status = 'ditolak',
                id_petugas = ?
            WHERE id = ?
              AND status = 'menunggu'
        ");


        $stmt->execute([
            $_SESSION['user_id'],
            $id
        ]);


        if (
            $stmt->rowCount() === 1
        ) {

            try {

                logActivity(
                    $pdo,
                    $_SESSION['user_id'],
                    "Menolak peminjaman ID: {$id}"
                );
            } catch (Throwable $logError) {
                // Abaikan jika log gagal.
            }


            flashMessage(
                'success',
                'Peminjaman ditolak.'
            );
        } else {

            flashMessage(
                'danger',
                'Peminjaman tidak ditemukan atau sudah diproses.'
            );
        }
    } catch (PDOException $e) {

        flashMessage(
            'danger',
            'Gagal menolak peminjaman: ' .
                $e->getMessage()
        );
    }


    header(
        'Location: ajukan.php'
    );

    exit;
}


// ============================================================
// ADMIN / PETUGAS
// AMBIL DATA PENGAJUAN
// ============================================================

$pengajuan = [];


if (
    $isAdmin ||
    $isPetugas
) {

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


<?php if ($isPeminjam): ?>
<style>
    /* ================================================
       CAMERA WRAPPER - tampilan kamera lebih luas
    ================================================ */
    .camera-wrapper {
        position: relative;
        width: 100%;
        background: #000;
        border-radius: 10px;
        overflow: hidden;
        aspect-ratio: 16 / 9;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 260px;
    }

    .camera-wrapper video {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: none;
        background: #000;
    }

    /* Kelas ini mem-flip video agar tidak mirror */
    .camera-wrapper video.is-mirrored {
        transform: scaleX(-1);
    }

    /* Preview foto bisa diklik untuk memperbesar */
    .preview-clickable {
        cursor: zoom-in;
        transition: transform .2s ease, opacity .2s ease;
    }
    .preview-clickable:hover {
        transform: scale(1.02);
        opacity: .92;
    }

    /* Tombol kecil untuk info */
    .camera-hint {
        font-size: .8rem;
        color: #6c757d;
    }
</style>
<?php endif; ?>


<div class="content p-4">

    <?php if ($isPeminjam): ?>

        <!-- ======================================================
         PEMINJAM
    ======================================================= -->

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


        <!-- ======================================================
         FLASH MESSAGE
    ======================================================= -->

        <?php if ($flash = getFlash()): ?>

            <div
                class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show"
                role="alert">

                <?= $flash['message'] ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"></button>

            </div>

        <?php endif; ?>


        <!-- ======================================================
         FORM
    ======================================================= -->

        <div class="card shadow-sm border-0">

            <div class="card-body p-4">

                <?php if (empty($alat)): ?>

                    <div class="text-center py-5">

                        <i
                            class="fas fa-box-open fa-3x text-muted mb-3"></i>

                        <h5>
                            Tidak ada alat yang tersedia
                        </h5>

                        <p class="text-muted mb-0">
                            Saat ini seluruh alat sedang tidak tersedia.
                        </p>

                    </div>

                <?php else: ?>

                    <form
                        method="POST"
                        enctype="multipart/form-data"
                        id="formPeminjaman">

                        <input type="hidden" name="ajukan" value="1">


                        <div class="row">

                            <!-- PILIH ALAT -->

                            <div class="col-md-6 mb-3">

                                <label
                                    class="form-label fw-semibold"
                                    for="id_alat">
                                    <i class="fas fa-toolbox me-1"></i>
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

                                            <?= htmlspecialchars(
                                                $a['nama_alat']
                                            ) ?>

                                            -

                                            <?= htmlspecialchars(
                                                $a['kode_alat']
                                            ) ?>

                                            (Tersedia:
                                            <?= (int) $a['stok_tersedia'] ?>
                                            )

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <!-- JUMLAH -->

                            <div class="col-md-6 mb-3">

                                <label
                                    class="form-label fw-semibold"
                                    for="jumlah">
                                    <i class="fas fa-layer-group me-1"></i>
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

                                <label
                                    class="form-label fw-semibold"
                                    for="tanggal_pinjam">
                                    <i class="fas fa-calendar-alt me-1"></i>
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

                                <label
                                    class="form-label fw-semibold"
                                    for="tanggal_kembali_rencana">
                                    <i class="fas fa-calendar-check me-1"></i>
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

                            <div class="col-12 mb-4">

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


                            <!-- FOTO -->

                            <div class="col-12 mb-4">

                                <label class="form-label fw-semibold">
                                    <i class="fas fa-camera me-1"></i>
                                    Foto Saat Peminjaman
                                    <span class="text-muted fw-normal"></span>
                                </label>


                                <!-- TOGGLE MODE -->
                                <div class="mb-3">

                                    <div class="btn-group" role="group" aria-label="Pilih metode foto">

                                        <input
                                            type="radio"
                                            class="btn-check"
                                            name="mode_foto"
                                            id="mode_upload"
                                            value="upload"
                                            autocomplete="off"
                                            checked>

                                        <label class="btn btn-outline-dark" for="mode_upload">
                                            <i class="fas fa-upload me-1"></i> Upload Foto
                                        </label>


                                        <input
                                            type="radio"
                                            class="btn-check"
                                            name="mode_foto"
                                            id="mode_kamera"
                                            value="kamera"
                                            autocomplete="off">

                                        <label class="btn btn-outline-dark" for="mode_kamera">
                                            <i class="fas fa-camera me-1"></i> Ambil dari Kamera
                                        </label>

                                    </div>

                                </div>


                                <!-- MODE: UPLOAD FILE -->
                                <div id="sectionUpload">

                                    <input
                                        type="file"
                                        name="foto_peminjaman"
                                        id="foto_peminjaman"
                                        class="form-control"
                                        accept="image/jpeg,image/png,image/webp">

                                </div>


                                <!-- MODE: KAMERA -->
                                <div id="sectionKamera" class="d-none">

                                    <!-- Pilihan kamera + tombol mirror -->
                                    <div class="d-flex flex-wrap gap-2 mb-2">

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-dark"
                                            id="btnKameraBelakang">
                                            <i class="fas fa-camera me-1"></i> Kamera Belakang
                                        </button>

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-dark"
                                            id="btnKameraDepan">
                                            <i class="fas fa-user me-1"></i> Kamera Depan
                                        </button>

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-secondary"
                                            id="btnToggleMirror">
                                            <i class="fas fa-exchange-alt me-1"></i>
                                            <span id="mirrorLabel">Mirror: OFF</span>
                                        </button>

                                    </div>


                                    <!-- VIDEO WRAPPER (lebar penuh) -->
                                    <div class="camera-wrapper">

                                        <video
                                            id="videoKamera"
                                            autoplay
                                            playsinline
                                            muted></video>

                                        <div
                                            id="kameraPlaceholder"
                                            class="text-center text-muted p-4">

                                            <i class="fas fa-video fa-3x mb-3"></i>

                                            <p class="mb-0">
                                                Klik <strong>Aktifkan Kamera</strong>
                                                untuk mulai mengambil foto
                                                menggunakan kamera perangkat.
                                            </p>

                                        </div>

                                    </div>


                                    <!-- Tombol aksi -->
                                    <div class="mt-2 d-flex flex-wrap gap-2">

                                        <button
                                            type="button"
                                            class="btn btn-dark"
                                            id="btnAktifkanKamera">
                                            <i class="fas fa-video me-1"></i> Aktifkan Kamera
                                        </button>

                                        <button
                                            type="button"
                                            class="btn btn-primary d-none"
                                            id="btnAmbilFoto">
                                            <i class="fas fa-camera me-1"></i> Ambil Foto
                                        </button>

                                        <button
                                            type="button"
                                            class="btn btn-light d-none"
                                            id="btnMatikanKamera">
                                            <i class="fas fa-stop me-1"></i> Matikan Kamera
                                        </button>

                                    </div>

                                    <div class="camera-hint mt-2">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Gunakan <strong>Kamera Belakang</strong> untuk hasil terbaik.
                                        Tombol <strong>Mirror</strong> hanya mengubah tampilan preview,
                                        tidak mengubah hasil foto.
                                    </div>

                                    <canvas id="canvasKamera" class="d-none"></canvas>

                                </div>


                                <!-- FORM TEXT -->
                                <div class="form-text mt-2">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Foto kondisi barang sebelum dipinjam.
                                    Format JPG, PNG, atau WEBP. Maksimal 5 MB.
                                </div>


                                <!-- PREVIEW -->
                                <div id="previewContainer" class="mt-3 d-none">

                                    <div class="card border-0 shadow-sm">

                                        <div class="card-header bg-light fw-semibold d-flex justify-content-between align-items-center">

                                            <span>
                                                <i class="fas fa-image me-1"></i> Preview Foto
                                            </span>

                                            <small class="text-muted">
                                                Klik gambar untuk memperbesar
                                            </small>

                                        </div>

                                        <div class="card-body p-2 text-center">

                                            <img id="previewFoto"
                                                 src=""
                                                 alt="Preview foto peminjaman"
                                                 class="img-fluid rounded preview-clickable"
                                                 style="
                                                    width: 100%;
                                                    max-height: 480px;
                                                    object-fit: contain;
                                                    background: #f8f9fa;
                                                 ">

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>


                        <hr class="my-4">


                        <!-- BUTTON -->

                        <div class="d-flex justify-content-end">

                            <button
                                type="reset"
                                class="btn btn-light me-2"
                                id="btnReset">

                                <i class="fas fa-undo me-1"></i>

                                Batal

                            </button>


                            <button
                                type="submit"
                                class="btn btn-dark px-4"
                                id="btnSubmit">

                                <i class="fas fa-paper-plane me-1"></i>

                                Ajukan Peminjaman

                            </button>

                        </div>

                    </form>

                <?php endif; ?>

            </div>

        </div>


    <?php elseif ($isAdmin || $isPetugas): ?>


        <!-- ======================================================
         ADMIN / PETUGAS
    ======================================================= -->

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


        <!-- ======================================================
         FLASH MESSAGE
    ======================================================= -->

        <?php if ($flash = getFlash()): ?>

            <div
                class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show"
                role="alert">

                <?= $flash['message'] ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"></button>

            </div>

        <?php endif; ?>


        <!-- ======================================================
         TABEL PENGAJUAN
    ======================================================= -->

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

                                        <i
                                            class="fas fa-inbox fa-2x mb-2"></i>

                                        <br>

                                        Tidak ada pengajuan
                                        yang menunggu.

                                    </td>

                                </tr>

                            <?php else: ?>


                                <?php foreach ($pengajuan as $p): ?>

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

                                        <td>

                                            <?= htmlspecialchars(
                                                $p['nama_lengkap'] ?? '-'
                                            ) ?>

                                        </td>


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


                                        <!-- FOTO -->

                                        <td>

                                            <?php

                                            $fotoUrl = '';

                                            if (
                                                !empty($p['foto_peminjaman'])
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
                                                width: 70px;
                                                height: 70px;
                                                object-fit: cover;
                                                cursor: pointer;
                                            "
                                                    title="Klik untuk melihat foto">

                                            <?php else: ?>

                                                <span
                                                    class="text-muted small">

                                                    Tidak ada foto

                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- JUMLAH -->

                                        <td>

                                            <span class="badge bg-secondary">

                                                <?= (int) $p['jumlah'] ?>

                                                unit

                                            </span>

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

                                            <div class="d-flex gap-1">

                                                <a
                                                    href="?approve=<?= (int) $p['id'] ?>"
                                                    class="btn btn-sm btn-success"
                                                    onclick="return confirm('Setujui peminjaman ini?')"
                                                    title="Setujui">

                                                    <i
                                                        class="fas fa-check"></i>

                                                </a>


                                                <a
                                                    href="?reject=<?= (int) $p['id'] ?>"
                                                    class="btn btn-sm btn-danger"
                                                    onclick="return confirm('Tolak peminjaman ini?')"
                                                    title="Tolak">

                                                    <i
                                                        class="fas fa-times"></i>

                                                </a>

                                            </div>

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
     MODAL PREVIEW FOTO (PEMINJAM)
========================================================== -->

<?php if ($isPeminjam): ?>

    <div class="modal fade"
         id="previewModal"
         tabindex="-1"
         aria-hidden="true">

        <div class="modal-dialog modal-dialog-centered modal-xl">

            <div class="modal-content border-0 shadow">

                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-image me-2"></i>
                        Preview Foto
                    </h5>
                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                            aria-label="Close"></button>
                </div>

                <div class="modal-body text-center p-2">

                    <img id="previewModalFoto"
                         src=""
                         alt="Preview foto peminjaman"
                         class="img-fluid rounded"
                         style="max-height: 85vh; object-fit: contain;">

                </div>

            </div>

        </div>

    </div>

<?php endif; ?>


<!-- ==========================================================
     MODAL FOTO (ADMIN / PETUGAS)
========================================================== -->

<?php if ($isAdmin || $isPetugas): ?>

    <div
        class="modal fade"
        id="fotoModal"
        tabindex="-1"
        aria-hidden="true">

        <div
            class="modal-dialog modal-dialog-centered modal-lg">

            <div
                class="modal-content border-0 shadow">

                <div class="modal-header">

                    <h5 class="modal-title">

                        <i class="fas fa-image me-2"></i>

                        Foto Peminjaman

                    </h5>


                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"></button>

                </div>


                <div
                    class="modal-body text-center p-2">

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
         MODAL KETERANGAN (ADMIN / PETUGAS)
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
     JAVASCRIPT PEMINJAM
========================================================== -->

<?php if ($isPeminjam): ?>

    <script>
    document.addEventListener('DOMContentLoaded', function () {

        // ====================================================
        // ELEMEN FORM
        // ====================================================

        const alatSelect       = document.getElementById('id_alat');
        const jumlahInput      = document.getElementById('jumlah');
        const infoStok         = document.getElementById('infoStok');
        const tanggalPinjam    = document.getElementById('tanggal_pinjam');
        const tanggalKembali   = document.getElementById('tanggal_kembali_rencana');
        const fotoInput        = document.getElementById('foto_peminjaman');
        const previewContainer = document.getElementById('previewContainer');
        const previewFoto      = document.getElementById('previewFoto');
        const form             = document.getElementById('formPeminjaman');
        const btnSubmit        = document.getElementById('btnSubmit');
        const btnReset         = document.getElementById('btnReset');


        // ====================================================
        // ELEMEN MODE FOTO
        // ====================================================

        const modeUpload         = document.getElementById('mode_upload');
        const modeKamera         = document.getElementById('mode_kamera');
        const sectionUpload      = document.getElementById('sectionUpload');
        const sectionKamera      = document.getElementById('sectionKamera');
        const videoKamera        = document.getElementById('videoKamera');
        const kameraPlaceholder  = document.getElementById('kameraPlaceholder');
        const canvasKamera       = document.getElementById('canvasKamera');
        const btnAktifkanKamera  = document.getElementById('btnAktifkanKamera');
        const btnAmbilFoto       = document.getElementById('btnAmbilFoto');
        const btnMatikanKamera   = document.getElementById('btnMatikanKamera');
        const btnKameraBelakang  = document.getElementById('btnKameraBelakang');
        const btnKameraDepan     = document.getElementById('btnKameraDepan');
        const btnToggleMirror    = document.getElementById('btnToggleMirror');
        const mirrorLabel        = document.getElementById('mirrorLabel');


        // ====================================================
        // STATE KAMERA
        // ====================================================

        let kameraStream = null;
        let facingMode   = 'environment';   // default kamera belakang
        let isMirrored   = false;           // default tidak mirror


        // ====================================================
        // UPDATE KELAS MIRROR PADA VIDEO
        // ====================================================

        function applyMirror() {

            if (!videoKamera) return;

            if (isMirrored) {
                videoKamera.classList.add('is-mirrored');
            } else {
                videoKamera.classList.remove('is-mirrored');
            }

            if (mirrorLabel) {
                mirrorLabel.textContent = 'Mirror: ' + (isMirrored ? 'ON' : 'OFF');
            }
        }


        // ====================================================
        // STOP KAMERA
        // ====================================================

        function stopKamera() {

            if (kameraStream) {
                kameraStream.getTracks().forEach(t => t.stop());
                kameraStream = null;
            }

            if (videoKamera) {
                videoKamera.srcObject = null;
                videoKamera.style.display = 'none';
                videoKamera.classList.remove('is-mirrored');
            }

            if (kameraPlaceholder) {
                kameraPlaceholder.style.display = 'block';
            }

            if (btnAktifkanKamera) btnAktifkanKamera.classList.remove('d-none');
            if (btnAmbilFoto)      btnAmbilFoto.classList.add('d-none');
            if (btnMatikanKamera)  btnMatikanKamera.classList.add('d-none');
        }


        // ====================================================
        // MULAI KAMERA
        // ====================================================

        async function startKamera(mode) {

            // Kalau stream sudah ada, matikan dulu
            if (kameraStream) {
                kameraStream.getTracks().forEach(t => t.stop());
                kameraStream = null;
            }

            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                alert('Browser Anda tidak mendukung akses kamera.');
                return;
            }

            facingMode = mode || facingMode;

            try {

                kameraStream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: { ideal: facingMode },
                        width:  { ideal: 1920 },
                        height: { ideal: 1080 }
                    },
                    audio: false
                });

                videoKamera.srcObject = kameraStream;
                videoKamera.style.display = 'block';
                kameraPlaceholder.style.display = 'none';

                // Deteksi facing mode aktual
                try {
                    const track    = kameraStream.getVideoTracks()[0];
                    const settings = track.getSettings ? track.getSettings() : {};
                    if (settings.facingMode) {
                        facingMode = settings.facingMode;
                    }
                } catch (e) { /* abaikan */ }

                // AUTO LOGIC:
                // - Kamera depan (user) -> browser auto-mirror,
                //   flip balik dengan scaleX(-1) agar TIDAK mirror.
                // - Kamera belakang (environment) -> biarkan apa adanya.
                if (facingMode === 'user') {
                    isMirrored = true;    // aktifkan CSS scaleX(-1) untuk UN-mirror
                } else {
                    isMirrored = false;
                }
                applyMirror();

                btnAktifkanKamera.classList.add('d-none');
                btnAmbilFoto.classList.remove('d-none');
                btnMatikanKamera.classList.remove('d-none');

            } catch (err) {
                console.error(err);
                alert('Gagal mengakses kamera: ' + err.message);
            }
        }


        // ====================================================
        // TOGGLE MODE FOTO
        // ====================================================

        if (modeUpload && modeKamera) {

            modeUpload.addEventListener('change', function () {
                if (this.checked) {
                    sectionUpload.classList.remove('d-none');
                    sectionKamera.classList.add('d-none');
                    stopKamera();
                }
            });

            modeKamera.addEventListener('change', function () {
                if (this.checked) {
                    sectionUpload.classList.add('d-none');
                    sectionKamera.classList.remove('d-none');
                }
            });
        }


        // ====================================================
        // BUTTON KAMERA
        // ====================================================

        if (btnAktifkanKamera) {
            btnAktifkanKamera.addEventListener('click', function () {
                startKamera(facingMode);
            });
        }

        if (btnMatikanKamera) {
            btnMatikanKamera.addEventListener('click', stopKamera);
        }

        if (btnKameraBelakang) {
            btnKameraBelakang.addEventListener('click', function () {
                facingMode = 'environment';
                startKamera(facingMode);
            });
        }

        if (btnKameraDepan) {
            btnKameraDepan.addEventListener('click', function () {
                facingMode = 'user';
                startKamera(facingMode);
            });
        }

        // Toggle mirror manual (hanya mempengaruhi preview, bukan hasil foto)
        if (btnToggleMirror) {
            btnToggleMirror.addEventListener('click', function () {
                isMirrored = !isMirrored;
                applyMirror();
            });
        }


        // ====================================================
        // AMBIL FOTO DARI KAMERA
        // ====================================================

        if (btnAmbilFoto) {

            btnAmbilFoto.addEventListener('click', function () {

                if (!videoKamera.videoWidth) {
                    alert('Kamera belum siap, coba tunggu sebentar.');
                    return;
                }

                canvasKamera.width  = videoKamera.videoWidth;
                canvasKamera.height = videoKamera.videoHeight;

                const ctx = canvasKamera.getContext('2d');

                // Ambil foto tanpa mirror.
// Jika preview sedang dibalik, balik kembali canvas agar hasil foto normal.
if (isMirrored) {
    ctx.save();

    ctx.translate(canvasKamera.width, 0);
    ctx.scale(-1, 1);

    ctx.drawImage(
        videoKamera,
        0,
        0,
        canvasKamera.width,
        canvasKamera.height
    );

    ctx.restore();
} else {
    ctx.drawImage(
        videoKamera,
        0,
        0,
        canvasKamera.width,
        canvasKamera.height
    );
}

                canvasKamera.toBlob(function (blob) {

                    if (!blob) {
                        alert('Gagal mengambil foto.');
                        return;
                    }

                    const file = new File(
                        [blob],
                        'kamera_' + Date.now() + '.jpg',
                        { type: 'image/jpeg' }
                    );

                    // Set file ke input file agar ikut ter-submit
                    try {
                        const dt = new DataTransfer();
                        dt.items.add(file);
                        fotoInput.files = dt.files;
                    } catch (e) {
                        console.error(e);
                        alert('Browser tidak mendukung pengambilan foto ini.');
                        return;
                    }

                    // Update preview
                    const url = URL.createObjectURL(blob);
                    previewFoto.src = url;
                    previewContainer.classList.remove('d-none');

                    // Matikan kamera setelah jepret
                    stopKamera();

                }, 'image/jpeg', 0.92);
            });
        }


        // ====================================================
        // UPDATE STOK
        // ====================================================

        function updateStok() {

            const option = alatSelect.options[alatSelect.selectedIndex];

            if (!option || !option.dataset.stok) {
                infoStok.textContent = 'Pilih alat terlebih dahulu.';
                jumlahInput.removeAttribute('max');
                return;
            }

            const stok = parseInt(option.dataset.stok, 10);
            jumlahInput.max = stok;
            infoStok.textContent = 'Maksimal peminjaman: ' + stok + ' unit.';

            if (parseInt(jumlahInput.value, 10) > stok) {
                jumlahInput.value = stok;
            }
        }

        alatSelect.addEventListener('change', updateStok);
        jumlahInput.addEventListener('input', updateStok);


        // ====================================================
        // TANGGAL
        // ====================================================

        tanggalPinjam.addEventListener('change', function () {
            tanggalKembali.min = tanggalPinjam.value;
            if (tanggalKembali.value && tanggalKembali.value < tanggalPinjam.value) {
                tanggalKembali.value = tanggalPinjam.value;
            }
        });


        // ====================================================
        // PREVIEW FOTO (UPLOAD)
        // ====================================================

        fotoInput.addEventListener('change', function () {

            const file = this.files[0];

            if (!file) {
                previewContainer.classList.add('d-none');
                previewFoto.src = '';
                return;
            }

            const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

            if (!allowedTypes.includes(file.type)) {
                alert('Format foto harus JPG, PNG, atau WEBP.');
                this.value = '';
                previewContainer.classList.add('d-none');
                previewFoto.src = '';
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                alert('Ukuran foto maksimal 5 MB.');
                this.value = '';
                previewContainer.classList.add('d-none');
                previewFoto.src = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function (e) {
                previewFoto.src = e.target.result;
                previewContainer.classList.remove('d-none');
            };
            reader.readAsDataURL(file);
        });


        // ====================================================
        // KLIK PREVIEW -> BUKA MODAL
        // ====================================================

        const previewModalEl   = document.getElementById('previewModal');
        const previewModalFoto = document.getElementById('previewModalFoto');

        if (previewFoto && previewModalEl && previewModalFoto && typeof bootstrap !== 'undefined') {

            const previewModal = new bootstrap.Modal(previewModalEl);

            previewFoto.addEventListener('click', function () {
                if (!this.src) return;
                previewModalFoto.src = this.src;
                previewModal.show();
            });

            previewModalEl.addEventListener('hidden.bs.modal', function () {
                previewModalFoto.src = '';
            });
        }


        // ====================================================
        // RESET
        // ====================================================

        btnReset.addEventListener('click', function () {

            stopKamera();

            setTimeout(function () {

                previewContainer.classList.add('d-none');
                previewFoto.src = '';

                if (modeUpload) modeUpload.checked = true;
                if (sectionUpload) sectionUpload.classList.remove('d-none');
                if (sectionKamera) sectionKamera.classList.add('d-none');

                // Reset mirror ke default
                isMirrored = false;
                applyMirror();

                tanggalKembali.min = tanggalPinjam.value;
                updateStok();

            }, 50);
        });


        // ====================================================
        // SUBMIT
        // ====================================================

        form.addEventListener('submit', function (e) {

            if (!alatSelect.value) {
                e.preventDefault();
                alert('Silakan pilih alat terlebih dahulu.');
                alatSelect.focus();
                return;
            }

            const jumlah = parseInt(jumlahInput.value, 10);
            const option = alatSelect.options[alatSelect.selectedIndex];
            const stok   = parseInt(option.dataset.stok || 0, 10);

            if (jumlah <= 0 || jumlah > stok) {
                e.preventDefault();
                alert('Jumlah peminjaman tidak valid.');
                jumlahInput.focus();
                return;
            }

            if (!tanggalPinjam.value || !tanggalKembali.value) {
                e.preventDefault();
                alert('Tanggal pinjam dan tanggal kembali wajib diisi.');
                return;
            }

            if (tanggalKembali.value < tanggalPinjam.value) {
                e.preventDefault();
                alert('Tanggal rencana kembali tidak boleh sebelum tanggal pinjam.');
                tanggalKembali.focus();
                return;
            }

            // Matikan kamera agar tidak mengganggu proses upload
            if (typeof stopKamera === 'function') {
                stopKamera();
            }

            btnSubmit.disabled = true;
            btnSubmit.innerHTML =
                '<span class="spinner-border spinner-border-sm me-2"></span>' +
                'Mengirim pengajuan...';
        });


        // ====================================================
        // INITIAL
        // ====================================================

        tanggalKembali.min = tanggalPinjam.value;
        updateStok();
        applyMirror();

    });
    </script>

<?php endif; ?>


<!-- ==========================================================
     JAVASCRIPT MODAL FOTO + KETERANGAN (ADMIN / PETUGAS)
========================================================== -->

<?php if ($isAdmin || $isPetugas): ?>

    <script>
        document.addEventListener(
            'DOMContentLoaded',
            function() {

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
                        new bootstrap.Modal(
                            fotoModalElement
                        );


                    fotoElements.forEach(
                        function(img) {

                            img.addEventListener(
                                'click',
                                function() {

                                    modalFoto.src =
                                        this.dataset.foto;

                                    fotoModal.show();

                                }
                            );

                        }
                    );


                    fotoModalElement.addEventListener(
                        'hidden.bs.modal',
                        function() {

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
                        new bootstrap.Modal(
                            modalKeteranganEl
                        );


                    keteranganCells.forEach(
                        function(el) {

                            el.addEventListener(
                                'click',
                                function() {

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