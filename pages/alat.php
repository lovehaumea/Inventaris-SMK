<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole(['admin', 'peminjam']);

$action  = $_GET['action'] ?? 'list';
$id      = (int) ($_GET['id'] ?? 0);
$isAdmin = hasRole('admin');

// =========================
// VARIABEL FILTER
// =========================
$cari      = trim($_GET['cari'] ?? '');
$fKategori = (int) ($_GET['kategori'] ?? 0);
$fKondisi  = trim($_GET['kondisi'] ?? '');
$fStok     = trim($_GET['stok'] ?? '');

/* =========================
   FUNGSI UPLOAD FOTO
========================= */
function uploadFotoAlat($file)
{
    if (empty($file['name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $uploadDir = __DIR__ . '/../uploads/alat/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        throw new Exception('Folder upload foto alat tidak dapat dibuat.');
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('Ukuran foto maksimal 5 MB.');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowed[$mime]) || @getimagesize($file['tmp_name']) === false) {
        throw new Exception('Foto harus berupa JPG, PNG, atau WEBP yang valid.');
    }

    $fileName = 'alat_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
        throw new Exception('Foto alat gagal diunggah.');
    }

    return 'uploads/alat/' . $fileName;
}

/* =========================
   PROSES CRUD ADMIN
========================= */
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {

    // ==============================================
    // PROSES TAMBAH ALAT
    // ==============================================
    if (isset($_POST['tambah'])) {
        $fotoPath = null;
        try {
            $fotoPath = uploadFotoAlat($_FILES['foto_alat'] ?? []);
            $stok = max(0, (int)($_POST['stok'] ?? 0));

            $stmt = $pdo->prepare("
                INSERT INTO alat
                (kode_alat, nama_alat, id_kategori, stok, stok_tersedia,
                 stok_rusak_ringan, stok_rusak_berat,
                 kondisi, keterangan, merk, nomor_seri, tahun_perolehan,
                 lokasi, spesifikasi, foto_alat)
                VALUES (?, ?, ?, ?, ?, 0, 0, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                trim($_POST['kode_alat'] ?? ''),
                trim($_POST['nama_alat'] ?? ''),
                (int)($_POST['id_kategori'] ?? 0),
                $stok,
                $stok,
                $_POST['kondisi'] ?? 'baik',
                trim($_POST['keterangan'] ?? ''),
                trim($_POST['merk'] ?? ''),
                trim($_POST['nomor_seri'] ?? ''),
                ($_POST['tahun_perolehan'] ?? '') !== '' ? (int)$_POST['tahun_perolehan'] : null,
                trim($_POST['lokasi'] ?? ''),
                trim($_POST['spesifikasi'] ?? ''),
                $fotoPath
            ]);

            logActivity($pdo, $_SESSION['user_id'], "Menambah alat: " . trim($_POST['nama_alat'] ?? ''));
            flashMessage('success', 'Alat berhasil ditambahkan.');
        } catch (Throwable $e) {
            if ($fotoPath && file_exists(__DIR__ . '/../' . $fotoPath)) {
                @unlink(__DIR__ . '/../' . $fotoPath);
            }
            flashMessage('danger', 'Gagal menambahkan alat: ' . $e->getMessage());
        }

        header('Location: alat.php');
        exit;
    }

    // ==============================================
    // PROSES EDIT ALAT
    // ==============================================
    if (isset($_POST['edit'])) {
        $newFotoPath = null;
        try {
            $alatId = (int)($_POST['id'] ?? 0);

            $oldStmt = $pdo->prepare("SELECT * FROM alat WHERE id=?");
            $oldStmt->execute([$alatId]);
            $oldAlat = $oldStmt->fetch();

            if (!$oldAlat) {
                throw new Exception('Data alat tidak ditemukan.');
            }

            $fotoPath    = $oldAlat['foto_alat'] ?? null;
            $newFotoPath = uploadFotoAlat($_FILES['foto_alat'] ?? []);

            if ($newFotoPath) {
                $fotoPath = $newFotoPath;
            }

            $stok              = max(0, (int)($_POST['stok'] ?? 0));
            $stok_tersedia     = max(0, (int)($_POST['stok_tersedia'] ?? 0));
            $stok_rusak_ringan = max(0, (int)($_POST['stok_rusak_ringan'] ?? 0));
            $stok_rusak_berat  = max(0, (int)($_POST['stok_rusak_berat'] ?? 0));

            // Validasi: total (tersedia + rusak ringan + rusak berat) tidak boleh melebihi stok total
            $totalFisik = $stok_tersedia + $stok_rusak_ringan + $stok_rusak_berat;
            if ($totalFisik > $stok) {
                throw new Exception(
                    'Total (tersedia + rusak ringan + rusak berat) = ' . $totalFisik .
                    ' melebihi stok total (' . $stok . '). ' .
                    'Silakan sesuaikan nilai stok terlebih dahulu.'
                );
            }

            $stmt = $pdo->prepare("
                UPDATE alat SET
                    kode_alat=?, nama_alat=?, id_kategori=?,
                    stok=?, stok_tersedia=?,
                    stok_rusak_ringan=?, stok_rusak_berat=?,
                    kondisi=?, keterangan=?, merk=?, nomor_seri=?, tahun_perolehan=?,
                    lokasi=?, spesifikasi=?, foto_alat=?
                WHERE id=?
            ");

            $stmt->execute([
                trim($_POST['kode_alat'] ?? ''),
                trim($_POST['nama_alat'] ?? ''),
                (int)($_POST['id_kategori'] ?? 0),
                $stok,
                $stok_tersedia,
                $stok_rusak_ringan,
                $stok_rusak_berat,
                $_POST['kondisi'] ?? 'baik',
                trim($_POST['keterangan'] ?? ''),
                trim($_POST['merk'] ?? ''),
                trim($_POST['nomor_seri'] ?? ''),
                ($_POST['tahun_perolehan'] ?? '') !== '' ? (int)$_POST['tahun_perolehan'] : null,
                trim($_POST['lokasi'] ?? ''),
                trim($_POST['spesifikasi'] ?? ''),
                $fotoPath,
                $alatId
            ]);

            if ($newFotoPath && !empty($oldAlat['foto_alat'])) {
                $oldPhoto = __DIR__ . '/../' . ltrim($oldAlat['foto_alat'], '/');
                if (file_exists($oldPhoto)) {
                    @unlink($oldPhoto);
                }
            }

            logActivity($pdo, $_SESSION['user_id'], "Mengedit alat ID: $alatId");
            flashMessage('success', 'Data alat berhasil diupdate.');
        } catch (Throwable $e) {
            if ($newFotoPath && file_exists(__DIR__ . '/../' . $newFotoPath)) {
                @unlink(__DIR__ . '/../' . $newFotoPath);
            }
            flashMessage('danger', 'Gagal mengupdate alat: ' . $e->getMessage());
        }

        header('Location: alat.php');
        exit;
    }
}

/* =========================
   PROSES HAPUS ALAT
========================= */
if ($isAdmin && $action === 'delete' && $id) {
    try {
        $stmt = $pdo->prepare("SELECT foto_alat FROM alat WHERE id=?");
        $stmt->execute([$id]);
        $dataHapus = $stmt->fetch();

        $stmt = $pdo->prepare("DELETE FROM alat WHERE id=?");
        $stmt->execute([$id]);

        if ($dataHapus && !empty($dataHapus['foto_alat'])) {
            $photo = __DIR__ . '/../' . ltrim($dataHapus['foto_alat'], '/');
            if (file_exists($photo)) @unlink($photo);
        }

        logActivity($pdo, $_SESSION['user_id'], "Menghapus alat ID: $id");
        flashMessage('success', 'Alat berhasil dihapus.');
    } catch (Throwable $e) {
        flashMessage('danger', 'Gagal menghapus alat: ' . $e->getMessage());
    }

    header('Location: alat.php');
    exit;
}

/* =========================
   AMBIL DATA UNTUK DITAMPILKAN (DENGAN FILTER)
========================= */
$where  = [];
$params = [];

if ($cari !== '') {
    $where[] = "(a.nama_alat LIKE ? OR a.kode_alat LIKE ? OR a.merk LIKE ? OR a.nomor_seri LIKE ?)";
    $like = '%' . $cari . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($fKategori > 0) {
    $where[]  = "a.id_kategori = ?";
    $params[] = $fKategori;
}

if ($fKondisi !== '') {
    $where[]  = "a.kondisi = ?";
    $params[] = $fKondisi;
}

if ($fStok === 'tersedia') {
    $where[] = "a.stok_tersedia > 0";
} elseif ($fStok === 'habis') {
    $where[] = "a.stok_tersedia <= 0";
} elseif ($fStok === 'rusak') {
    $where[] = "(a.stok_rusak_ringan > 0 OR a.stok_rusak_berat > 0)";
}

$sql = "
    SELECT a.*, k.nama_kategori
    FROM alat a
    LEFT JOIN kategori k ON a.id_kategori = k.id
";

if ($where) {
    $sql .= " WHERE " . implode(' AND ', $where);
}

$sql .= " ORDER BY a.nama_alat ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$alat = $stmt->fetchAll();

$kategori = $pdo->query("SELECT * FROM kategori ORDER BY nama_kategori ASC")->fetchAll();

$editAlat   = null;
$detailAlat = null;

if ($isAdmin && $action === 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM alat WHERE id=?");
    $stmt->execute([$id]);
    $editAlat = $stmt->fetch();
}

if ($action === 'detail' && $id) {
    $stmt = $pdo->prepare("
        SELECT a.*, k.nama_kategori
        FROM alat a
        LEFT JOIN kategori k ON a.id_kategori = k.id
        WHERE a.id=?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $detailAlat = $stmt->fetch();
}

function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<!-- Konten Utama -->
<div class="content p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">
                <i class="fas fa-tools me-2"></i><?= $isAdmin ? 'Manajemen Alat' : 'Daftar Alat' ?>
            </h2>
            <p class="text-muted mb-0">
                <?= $isAdmin ? 'Kelola data, spesifikasi, kondisi, dan foto alat.' : 'Lihat informasi lengkap alat yang tersedia.' ?>
            </p>
        </div>

        <?php if ($isAdmin): ?>
            <div class="d-flex gap-2">
                <a href="kelola_rusak.php" class="btn btn-outline-danger">
                    <i class="fas fa-tools me-1"></i>Kelola Stok Rusak
                </a>
                <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#modalTambah">
                    <i class="fas fa-plus me-1"></i>Tambah Alat
                </button>
            </div>
        <?php endif; ?>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">

            <!-- ==============================================
                 FORM PENCARIAN & FILTER
            ============================================== -->
            <form method="GET" class="row g-2 mb-4">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold mb-1">Cari</label>
                    <input
                        type="text"
                        name="cari"
                        class="form-control"
                        placeholder="Nama, kode, merk, atau nomor seri..."
                        value="<?= e($cari) ?>"
                    >
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">Kategori</label>
                    <select name="kategori" class="form-select">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($kategori as $k): ?>
                            <option
                                value="<?= (int) $k['id'] ?>"
                                <?= $fKategori === (int) $k['id'] ? 'selected' : '' ?>
                            >
                                <?= e($k['nama_kategori']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Kondisi</label>
                    <select name="kondisi" class="form-select">
                        <option value="">Semua</option>
                        <option value="baik"         <?= $fKondisi === 'baik'         ? 'selected' : '' ?>>Baik</option>
                        <option value="rusak ringan" <?= $fKondisi === 'rusak ringan' ? 'selected' : '' ?>>Rusak Ringan</option>
                        <option value="rusak berat"  <?= $fKondisi === 'rusak berat'  ? 'selected' : '' ?>>Rusak Berat</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Stok</label>
                    <select name="stok" class="form-select">
                        <option value=""         <?= $fStok === ''         ? 'selected' : '' ?>>Semua</option>
                        <option value="tersedia" <?= $fStok === 'tersedia' ? 'selected' : '' ?>>Tersedia</option>
                        <option value="habis"    <?= $fStok === 'habis'    ? 'selected' : '' ?>>Habis</option>
                        <option value="rusak"    <?= $fStok === 'rusak'    ? 'selected' : '' ?>>Ada Rusak</option>
                    </select>
                </div>

                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-dark w-100" title="Filter">
                        <i class="fas fa-search"></i>
                    </button>
                </div>

                <?php if ($cari !== '' || $fKategori > 0 || $fKondisi !== '' || $fStok !== ''): ?>
                    <div class="col-12">
                        <a href="alat.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-times me-1"></i>Reset Filter
                        </a>
                        <span class="text-muted small ms-2">
                            Menampilkan <strong><?= count($alat) ?></strong> hasil
                        </span>
                    </div>
                <?php endif; ?>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Foto</th>
                            <th>Kode</th>
                            <th>Nama Alat</th>
                            <th>Kategori</th>
                            <th>Stok Total</th>
                            <th>Tersedia</th>
                            <th>Rusak Ringan</th>
                            <th>Rusak Berat</th>
                            <th>Kondisi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$alat): ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted py-5">
                                    <?php if ($cari !== '' || $fKategori > 0 || $fKondisi !== '' || $fStok !== ''): ?>
                                        <i class="fas fa-search fa-2x mb-2 d-block opacity-50"></i>
                                        Tidak ada alat yang cocok dengan filter.<br>
                                        <a href="alat.php" class="small">Reset filter</a>
                                    <?php else: ?>
                                        Belum ada data alat.
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($alat as $a): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($a['foto_alat'])): ?>
                                            <img src="<?= e('../' . ltrim($a['foto_alat'], '/')) ?>"
                                                alt="<?= e($a['nama_alat']) ?>"
                                                class="rounded"
                                                style="width:58px;height:58px;object-fit:cover;">
                                        <?php else: ?>
                                            <div class="rounded bg-light d-flex align-items-center justify-content-center"
                                                style="width:58px;height:58px;">
                                                <i class="fas fa-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($a['kode_alat']) ?></td>
                                    <td class="fw-semibold"><?= e($a['nama_alat']) ?></td>
                                    <td><?= e($a['nama_kategori'] ?? '-') ?></td>
                                    <td>
                                        <strong><?= (int)$a['stok'] ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= (int)$a['stok_tersedia'] > 0 ? 'success' : 'secondary' ?>">
                                            <?= (int)$a['stok_tersedia'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ((int)($a['stok_rusak_ringan'] ?? 0) > 0): ?>
                                            <span class="badge bg-warning text-dark">
                                                <?= (int)$a['stok_rusak_ringan'] ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ((int)($a['stok_rusak_berat'] ?? 0) > 0): ?>
                                            <span class="badge bg-danger">
                                                <?= (int)$a['stok_rusak_berat'] ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e(ucwords($a['kondisi'])) ?></td>
                                    <td class="text-nowrap">
                                        <a href="?action=detail&id=<?= (int)$a['id'] ?>" class="btn btn-sm btn-info text-white" title="Detail">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($isAdmin): ?>
                                            <a href="?action=edit&id=<?= (int)$a['id'] ?>" class="btn btn-sm btn-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?action=delete&id=<?= (int)$a['id'] ?>" class="btn btn-sm btn-danger" title="Hapus"
                                                onclick="return confirm('Yakin ingin menghapus alat ini?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
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

<!-- MODAL DETAIL ALAT -->
<?php if ($detailAlat): ?>
    <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,.55);" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="fas fa-circle-info me-2"></i>Detail Alat</h5>
                    <a href="alat.php" class="btn-close btn-close-white"></a>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-4">
                        <div class="col-md-5">
                            <?php if (!empty($detailAlat['foto_alat'])): ?>
                                <img src="<?= e('../' . ltrim($detailAlat['foto_alat'], '/')) ?>"
                                    class="img-fluid rounded-4 shadow-sm w-100"
                                    style="max-height:360px;object-fit:cover;"
                                    alt="<?= e($detailAlat['nama_alat']) ?>">
                            <?php else: ?>
                                <div class="bg-light rounded-4 d-flex align-items-center justify-content-center"
                                    style="height:300px;">
                                    <div class="text-center text-muted">
                                        <i class="fas fa-image fa-3x mb-2"></i>
                                        <div>Belum ada foto alat</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-7">
                            <h3 class="fw-bold mb-1"><?= e($detailAlat['nama_alat']) ?></h3>
                            <p class="text-muted mb-4"><?= e($detailAlat['kode_alat']) ?></p>

                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <small class="text-muted d-block">Kategori</small>
                                    <strong><?= e($detailAlat['nama_kategori'] ?? '-') ?></strong>
                                </div>
                                <div class="col-sm-6">
                                    <small class="text-muted d-block">Kondisi Default</small>
                                    <strong><?= e(ucwords($detailAlat['kondisi'])) ?></strong>
                                </div>
                                <div class="col-sm-6">
                                    <small class="text-muted d-block">Stok Total</small>
                                    <strong><?= (int)$detailAlat['stok'] ?> unit</strong>
                                </div>
                                <div class="col-sm-6">
                                    <small class="text-muted d-block">Stok Tersedia</small>
                                    <strong class="text-success"><?= (int)$detailAlat['stok_tersedia'] ?> unit</strong>
                                </div>
                                <div class="col-sm-6">
                                    <small class="text-muted d-block">Stok Rusak Ringan</small>
                                    <strong class="text-warning"><?= (int)($detailAlat['stok_rusak_ringan'] ?? 0) ?> unit</strong>
                                </div>
                                <div class="col-sm-6">
                                    <small class="text-muted d-block">Stok Rusak Berat</small>
                                    <strong class="text-danger"><?= (int)($detailAlat['stok_rusak_berat'] ?? 0) ?> unit</strong>
                                </div>
                                <div class="col-sm-6">
                                    <small class="text-muted d-block">Merek</small>
                                    <strong><?= e($detailAlat['merk'] ?: '-') ?></strong>
                                </div>
                                <div class="col-sm-6">
                                    <small class="text-muted d-block">Nomor Seri</small>
                                    <strong><?= e($detailAlat['nomor_seri'] ?: '-') ?></strong>
                                </div>
                                <div class="col-sm-6">
                                    <small class="text-muted d-block">Tahun Perolehan</small>
                                    <strong><?= e($detailAlat['tahun_perolehan'] ?: '-') ?></strong>
                                </div>
                                <div class="col-sm-6">
                                    <small class="text-muted d-block">Lokasi</small>
                                    <strong><?= e($detailAlat['lokasi'] ?: '-') ?></strong>
                                </div>
                            </div>

                            <div class="alert alert-light border small mt-3 mb-0">
                                <i class="fas fa-info-circle me-1"></i>
                                <strong>Stok Total</strong> = Stok Tersedia + Rusak Ringan + Rusak Berat
                            </div>
                        </div>

                        <div class="col-12">
                            <hr>
                            <h6 class="fw-bold"><i class="fas fa-microchip me-2"></i>Spesifikasi Perangkat</h6>
                            <div class="bg-light rounded-3 p-3" style="white-space:pre-line;">
                                <?= e($detailAlat['spesifikasi'] ?: 'Belum ada spesifikasi.') ?>
                            </div>
                        </div>

                        <div class="col-12">
                            <h6 class="fw-bold"><i class="fas fa-note-sticky me-2"></i>Keterangan</h6>
                            <div class="bg-light rounded-3 p-3" style="white-space:pre-line;">
                                <?= e($detailAlat['keterangan'] ?: 'Tidak ada keterangan tambahan.') ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="alat.php" class="btn btn-dark">Tutup</a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($isAdmin): ?>
    <!-- MODAL TAMBAH ALAT -->
    <div class="modal fade" id="modalTambah" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form method="POST" enctype="multipart/form-data" class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title">Tambah Alat</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Kode Alat</label><input type="text" name="kode_alat" class="form-control" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Nama Alat</label><input type="text" name="nama_alat" class="form-control" required></div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kategori</label>
                            <select name="id_kategori" class="form-select" required>
                                <?php foreach ($kategori as $k): ?><option value="<?= (int)$k['id'] ?>"><?= e($k['nama_kategori']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Stok</label>
                            <input type="number" name="stok" class="form-control" min="0" required>
                            <div class="form-text">Stok awal. Nilai ini akan jadi stok tersedia.</div>
                        </div>
                        <div class="col-md-6 mb-3"><label class="form-label">Merek</label><input type="text" name="merk" class="form-control" placeholder="Contoh: ASUS"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Nomor Seri</label><input type="text" name="nomor_seri" class="form-control"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Tahun Perolehan</label><input type="number" name="tahun_perolehan" class="form-control" min="1900" max="<?= date('Y') ?>"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Lokasi</label><input type="text" name="lokasi" class="form-control" placeholder="Contoh: Lab Komputer 1"></div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kondisi</label>
                            <select name="kondisi" class="form-select">
                                <option value="baik">Baik</option>
                                <option value="rusak ringan">Rusak Ringan</option>
                                <option value="rusak berat">Rusak Berat</option>
                            </select>
                        </div>
                        <div class="col-12 mb-3"><label class="form-label">Spesifikasi Perangkat</label><textarea name="spesifikasi" class="form-control" rows="4" placeholder="CPU, RAM, penyimpanan, ukuran layar, fitur, dan lain-lain"></textarea></div>
                        <div class="col-12 mb-3"><label class="form-label">Keterangan</label><textarea name="keterangan" class="form-control" rows="3"></textarea></div>
                        <div class="col-12 mb-3"><label class="form-label">Foto Barang</label><input type="file" name="foto_alat" class="form-control" accept="image/jpeg,image/png,image/webp">
                            <div class="form-text">JPG, PNG, WEBP. Maksimal 5 MB.</div>
                        </div>
                    </div>

                    <div class="alert alert-info small mb-0">
                        <i class="fas fa-info-circle me-1"></i>
                        Stok rusak ringan/berat akan bertambah otomatis ketika ada pengembalian alat
                        dengan kondisi rusak. Anda bisa kelola di halaman <strong>Kelola Stok Rusak</strong>.
                    </div>
                </div>
                <div class="modal-footer"><button type="submit" name="tambah" class="btn btn-dark">Simpan</button></div>
            </form>
        </div>
    </div>

    <!-- MODAL EDIT ALAT -->
    <?php if ($editAlat): ?>
        <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,.55);">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <form method="POST" enctype="multipart/form-data" class="modal-content">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title">Edit Alat</h5><a href="alat.php" class="btn-close btn-close-white"></a>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" value="<?= (int)$editAlat['id'] ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3"><label class="form-label">Kode Alat</label><input type="text" name="kode_alat" class="form-control" value="<?= e($editAlat['kode_alat']) ?>" required></div>
                            <div class="col-md-6 mb-3"><label class="form-label">Nama Alat</label><input type="text" name="nama_alat" class="form-control" value="<?= e($editAlat['nama_alat']) ?>" required></div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kategori</label>
                                <select name="id_kategori" class="form-select" required>
                                    <?php foreach ($kategori as $k): ?><option value="<?= (int)$k['id'] ?>" <?= (int)$editAlat['id_kategori'] === (int)$k['id'] ? 'selected' : '' ?>><?= e($k['nama_kategori']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Stok Total</label>
                                <input type="number" name="stok" class="form-control" value="<?= (int)$editAlat['stok'] ?>" min="0" required>
                                <div class="form-text">Total fisik yang dimiliki.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Stok Tersedia</label>
                                <input type="number" name="stok_tersedia" class="form-control" value="<?= (int)$editAlat['stok_tersedia'] ?>" min="0" required>
                                <div class="form-text">Yang bisa dipinjam.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Stok Rusak Ringan</label>
                                <input type="number" name="stok_rusak_ringan" class="form-control" value="<?= (int)($editAlat['stok_rusak_ringan'] ?? 0) ?>" min="0" required>
                                <div class="form-text">Biasanya terisi otomatis dari pengembalian.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Stok Rusak Berat</label>
                                <input type="number" name="stok_rusak_berat" class="form-control" value="<?= (int)($editAlat['stok_rusak_berat'] ?? 0) ?>" min="0" required>
                                <div class="form-text">Biasanya terisi otomatis dari pengembalian.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kondisi Default</label>
                                <select name="kondisi" class="form-select">
                                    <option value="baik" <?= $editAlat['kondisi'] == 'baik' ? 'selected' : '' ?>>Baik</option>
                                    <option value="rusak ringan" <?= $editAlat['kondisi'] == 'rusak ringan' ? 'selected' : '' ?>>Rusak Ringan</option>
                                    <option value="rusak berat" <?= $editAlat['kondisi'] == 'rusak berat' ? 'selected' : '' ?>>Rusak Berat</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3"><label class="form-label">Merek</label><input type="text" name="merk" class="form-control" value="<?= e($editAlat['merk']) ?>"></div>
                            <div class="col-md-6 mb-3"><label class="form-label">Nomor Seri</label><input type="text" name="nomor_seri" class="form-control" value="<?= e($editAlat['nomor_seri']) ?>"></div>
                            <div class="col-md-6 mb-3"><label class="form-label">Tahun Perolehan</label><input type="number" name="tahun_perolehan" class="form-control" value="<?= e($editAlat['tahun_perolehan']) ?>" min="1900" max="<?= date('Y') ?>"></div>
                            <div class="col-md-6 mb-3"><label class="form-label">Lokasi</label><input type="text" name="lokasi" class="form-control" value="<?= e($editAlat['lokasi']) ?>"></div>
                            <div class="col-12 mb-3"><label class="form-label">Spesifikasi Perangkat</label><textarea name="spesifikasi" class="form-control" rows="4"><?= e($editAlat['spesifikasi']) ?></textarea></div>
                            <div class="col-12 mb-3"><label class="form-label">Keterangan</label><textarea name="keterangan" class="form-control" rows="3"><?= e($editAlat['keterangan']) ?></textarea></div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Foto Barang</label>
                                <?php if (!empty($editAlat['foto_alat'])): ?><div class="mb-2"><img src="<?= e('../' . ltrim($editAlat['foto_alat'], '/')) ?>" style="width:120px;height:90px;object-fit:cover;" class="rounded"></div><?php endif; ?>
                                <input type="file" name="foto_alat" class="form-control" accept="image/jpeg,image/png,image/webp">
                                <div class="form-text">Kosongkan jika foto tidak ingin diganti.</div>
                            </div>
                        </div>

                        <div class="alert alert-info small mb-0">
                            <i class="fas fa-info-circle me-1"></i>
                            <strong>Catatan:</strong>
                            Stok Total = Stok Tersedia + Stok Rusak Ringan + Stok Rusak Berat.
                            Sistem akan menolak jika total melebihi stok.
                        </div>
                    </div>
                    <div class="modal-footer"><button type="submit" name="edit" class="btn btn-dark">Update</button></div>
                </form>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- ==========================================================
     AUTO-SUBMIT FILTER SAAT DROPDOWN BERUBAH
========================================================== -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectFilter = document.querySelectorAll(
        'select[name="kategori"], select[name="kondisi"], select[name="stok"]'
    );

    selectFilter.forEach(function (el) {
        el.addEventListener('change', function () {
            this.form.submit();
        });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>