<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin');
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? 0;

// Proses CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tambah'])) {
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $nama = $_POST['nama_lengkap'];
        $role = $_POST['role'];
        $stmt = $pdo->prepare("INSERT INTO users (username, password, nama_lengkap, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $password, $nama, $role]);
        logActivity($pdo, $_SESSION['user_id'], "Menambah user: $username");
        flashMessage('success', 'User berhasil ditambahkan');
        header('Location: users.php');
        exit;
    }
    if (isset($_POST['edit'])) {
        $id = $_POST['id'];
        $username = $_POST['username'];
        $nama = $_POST['nama_lengkap'];
        $role = $_POST['role'];
        $status = $_POST['status'];
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET username=?, password=?, nama_lengkap=?, role=?, status=? WHERE id=?");
            $stmt->execute([$username, $password, $nama, $role, $status, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET username=?, nama_lengkap=?, role=?, status=? WHERE id=?");
            $stmt->execute([$username, $nama, $role, $status, $id]);
        }
        logActivity($pdo, $_SESSION['user_id'], "Mengedit user ID: $id");
        flashMessage('success', 'User berhasil diupdate');
        header('Location: users.php');
        exit;
    }
}

if ($action == 'delete' && $id) {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
    $stmt->execute([$id]);
    logActivity($pdo, $_SESSION['user_id'], "Menghapus user ID: $id");
    flashMessage('success', 'User berhasil dihapus');
    header('Location: users.php');
    exit;
}

$users = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();
$editUser = null;
if ($action == 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $stmt->execute([$id]);
    $editUser = $stmt->fetch();
}
?>
<div class="content p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="fas fa-users me-2"></i>Manajemen User</h2>
        <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#modalTambah">
            <i class="fas fa-plus me-1"></i>Tambah User
        </button>
    </div>

    <?php if ($flash = getFlash()): ?>
        <div class="alert alert-<?= $flash['type'] ?>"><?= $flash['message'] ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Nama Lengkap</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= $u['id'] ?></td>
                            <td><?= $u['username'] ?></td>
                            <td><?= $u['nama_lengkap'] ?></td>
                            <td><span class="badge bg-<?= $u['role']=='admin'?'dark':($u['role']=='petugas'?'secondary':'light text-dark') ?>"><?= $u['role'] ?></span></td>
                            <td><?= $u['status'] ?></td>
                            <td>
                                <a href="?action=edit&id=<?= $u['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                <a href="?action=delete&id=<?= $u['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin hapus?')"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">Tambah User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label>Username</label><input type="text" name="username" class="form-control" required></div>
                <div class="mb-3"><label>Password</label><input type="password" name="password" class="form-control" required></div>
                <div class="mb-3"><label>Nama Lengkap</label><input type="text" name="nama_lengkap" class="form-control" required></div>
                <div class="mb-3"><label>Role</label>
                    <select name="role" class="form-select">
                        <option value="admin">Admin</option>
                        <option value="petugas">Petugas</option>
                        <option value="peminjam">Peminjam</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="tambah" class="btn btn-dark">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit -->
<?php if ($editUser): ?>
<div class="modal fade show d-block" id="modalEdit" tabindex="-1" style="background: rgba(0,0,0,0.5);">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">Edit User</h5>
                <a href="users.php" class="btn-close btn-close-white"></a>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" value="<?= $editUser['id'] ?>">
                <div class="mb-3"><label>Username</label><input type="text" name="username" class="form-control" value="<?= $editUser['username'] ?>" required></div>
                <div class="mb-3"><label>Password (kosongkan jika tidak diubah)</label><input type="password" name="password" class="form-control"></div>
                <div class="mb-3"><label>Nama Lengkap</label><input type="text" name="nama_lengkap" class="form-control" value="<?= $editUser['nama_lengkap'] ?>" required></div>
                <div class="mb-3"><label>Role</label>
                    <select name="role" class="form-select">
                        <option value="admin" <?= $editUser['role']=='admin'?'selected':'' ?>>Admin</option>
                        <option value="petugas" <?= $editUser['role']=='petugas'?'selected':'' ?>>Petugas</option>
                        <option value="peminjam" <?= $editUser['role']=='peminjam'?'selected':'' ?>>Peminjam</option>
                    </select>
                </div>
                <div class="mb-3"><label>Status</label>
                    <select name="status" class="form-select">
                        <option value="aktif" <?= $editUser['status']=='aktif'?'selected':'' ?>>Aktif</option>
                        <option value="nonaktif" <?= $editUser['status']=='nonaktif'?'selected':'' ?>>Nonaktif</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="edit" class="btn btn-dark">Update</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>