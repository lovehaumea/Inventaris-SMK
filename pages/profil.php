<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama_lengkap'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telp = trim($_POST['no_telp'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');

    if ($nama === '') {
        $error = 'Nama lengkap wajib diisi.';
    } else {
        $stmt = $pdo->prepare("UPDATE users SET nama_lengkap=?, email=?, no_telp=?, alamat=? WHERE id=?");
        $stmt->execute([$nama, $email, $telp, $alamat, $_SESSION['user_id']]);

        // Update session
        $_SESSION['nama_lengkap'] = $nama;
        logActivity($pdo, $_SESSION['user_id'], 'Update profil');

        $success = 'Profil berhasil diperbarui.';
    }
}

// Ambil data user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Ganti password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ganti_password'])) {
    $old = $_POST['old_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $new2 = $_POST['new_password2'] ?? '';

    if (!password_verify($old, $user['password'])) {
        $error = 'Password lama salah.';
    } elseif (strlen($new) < 6) {
        $error = 'Password baru minimal 6 karakter.';
    } elseif ($new !== $new2) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        $stmt = $pdo->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['user_id']]);
        logActivity($pdo, $_SESSION['user_id'], 'Ganti password');
        $success = 'Password berhasil diubah.';
    }
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="content p-4">
    <h2 class="fw-bold mb-4"><i class="fas fa-user-circle me-2"></i>Profil Saya</h2>

    <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header"><i class="fas fa-edit me-2"></i>Edit Profil</div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3"><label>Username</label><input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" disabled></div>
                        <div class="mb-3"><label>Nama Lengkap</label><input type="text" name="nama_lengkap" class="form-control" value="<?= htmlspecialchars($user['nama_lengkap']) ?>" required></div>
                        <div class="mb-3"><label>Email</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>"></div>
                        <div class="mb-3"><label>No. Telepon</label><input type="text" name="no_telp" class="form-control" value="<?= htmlspecialchars($user['no_telp'] ?? '') ?>"></div>
                        <div class="mb-3"><label>Alamat</label><textarea name="alamat" class="form-control" rows="3"><?= htmlspecialchars($user['alamat'] ?? '') ?></textarea></div>
                        <button type="submit" class="btn btn-dark"><i class="fas fa-save me-1"></i>Simpan Perubahan</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-key me-2"></i>Ganti Password</div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3"><label>Password Lama</label><input type="password" name="old_password" class="form-control" required></div>
                        <div class="mb-3"><label>Password Baru</label><input type="password" name="new_password" class="form-control" required></div>
                        <div class="mb-3"><label>Konfirmasi Password Baru</label><input type="password" name="new_password2" class="form-control" required></div>
                        <button type="submit" name="ganti_password" class="btn btn-dark w-100"><i class="fas fa-shield-alt me-1"></i>Ganti Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>