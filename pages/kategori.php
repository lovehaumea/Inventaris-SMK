<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin');
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tambah'])) {
        $stmt = $pdo->prepare("INSERT INTO kategori (nama_kategori, deskripsi) VALUES (?, ?)");
        $stmt->execute([$_POST['nama_kategori'], $_POST['deskripsi']]);
        logActivity($pdo, $_SESSION['user_id'], "Menambah kategori: {$_POST['nama_kategori']}");
        flashMessage('success', 'Kategori berhasil ditambahkan');
        header('Location: kategori.php');
        exit;
    }
    if (isset($_POST['edit'])) {
        $stmt = $pdo->prepare("UPDATE kategori SET nama_kategori=?, deskripsi=? WHERE id=?");
        $stmt->execute([$_POST['nama_kategori'], $_POST['deskripsi'], $_POST['id']]);
        logActivity($pdo, $_SESSION['user_id'], "Mengedit kategori ID: {$_POST['id']}");
        flashMessage('success', 'Kategori berhasil diupdate');
        header('Location: kategori.php');
        exit;
    }
}

if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM kategori WHERE id=?");
    $stmt->execute([$_GET['delete']]);
    logActivity($pdo, $_SESSION['user_id'], "Menghapus kategori ID: {$_GET['delete']}");
    flashMessage('success', 'Kategori berhasil dihapus');
    header('Location: kategori.php');
    exit;
}

$kategori = $pdo->query("SELECT * FROM kategori ORDER BY id ASC")->fetchAll();
$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM kategori WHERE id=?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch();
}
?>
<div class="content p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="fas fa-tags me-2"></i>Kategori</h2>
        <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#modalTambah">
            <i class="fas fa-plus me-1"></i>Tambah Kategori
        </button>
    </div>
    <?php if ($flash = getFlash()): ?>
        <div class="alert alert-<?= $flash['type'] ?>"><?= $flash['message'] ?></div>
    <?php endif; ?>
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr><th>ID</th><th>Nama Kategori</th><th>Deskripsi</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($kategori as $k): ?>
                    <tr>
                        <td><?= $k['id'] ?></td>
                        <td><?= $k['nama_kategori'] ?></td>
                        <td><?= $k['deskripsi'] ?></td>
                        <td>
                            <a href="?edit=<?= $k['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                            <a href="?delete=<?= $k['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin hapus?')"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">Tambah Kategori</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label>Nama Kategori</label><input type="text" name="nama_kategori" class="form-control" required></div>
                <div class="mb-3"><label>Deskripsi</label><textarea name="deskripsi" class="form-control"></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="tambah" class="btn btn-dark">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit -->
<?php if ($edit): ?>
<div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">Edit Kategori</h5>
                <a href="kategori.php" class="btn-close btn-close-white"></a>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" value="<?= $edit['id'] ?>">
                <div class="mb-3"><label>Nama Kategori</label><input type="text" name="nama_kategori" class="form-control" value="<?= $edit['nama_kategori'] ?>" required></div>
                <div class="mb-3"><label>Deskripsi</label><textarea name="deskripsi" class="form-control"><?= $edit['deskripsi'] ?></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="edit" class="btn btn-dark">Update</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php require_once '../includes/footer.php'; ?>