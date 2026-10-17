<?php
// register.php — Halaman Registrasi Peminjam
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Jika sudah login, redirect ke dashboard
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . 'pages/dashboard.php');
    exit;
}

$error = '';
$success = '';
$input = ['username' => '', 'nama_lengkap' => '', 'email' => '', 'no_telp' => '', 'alamat' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username     = trim($_POST['username'] ?? '');
    $password     = $_POST['password'] ?? '';
    $password2    = $_POST['password2'] ?? '';
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $no_telp      = trim($_POST['no_telp'] ?? '');
    $alamat       = trim($_POST['alamat'] ?? '');

    $input = compact('username', 'nama_lengkap', 'email', 'no_telp', 'alamat');

    // ============ VALIDASI ============
    if ($username === '' || $password === '' || $nama_lengkap === '') {
        $error = 'Username, password, dan nama lengkap wajib diisi!';
    } elseif (strlen($username) < 3) {
        $error = 'Username minimal 3 karakter.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Username hanya boleh huruf, angka, dan underscore (_).';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif ($password !== $password2) {
        $error = 'Konfirmasi password tidak cocok.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif ($no_telp !== '' && !preg_match('/^[0-9+\-\s]{8,15}$/', $no_telp)) {
        $error = 'Format nomor telepon tidak valid.';
    } else {
        // Cek username sudah ada
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);

        if ($stmt->fetch()) {
            $error = 'Username sudah digunakan, silakan pilih yang lain.';
        } else {
            // Cek email duplikat (jika diisi)
            $email_ok = true;
            if ($email !== '') {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = 'Email sudah terdaftar, silakan gunakan yang lain.';
                    $email_ok = false;
                }
            }

            if ($email_ok) {
                // Insert user baru dengan role peminjam
                try {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users 
                        (username, password, nama_lengkap, role, email, no_telp, alamat, status) 
                        VALUES (?, ?, ?, 'peminjam', ?, ?, ?, 'aktif')");
                    $stmt->execute([$username, $hash, $nama_lengkap, $email, $no_telp, $alamat]);

                    $new_id = $pdo->lastInsertId();
                    logActivity($pdo, $new_id, 'Registrasi akun baru sebagai peminjam');

                    flashMessage('success', 'Registrasi berhasil! Silakan login dengan akun Anda.');
                    header('Location: ' . BASE_URL . 'index.php');
                    exit;

                } catch (PDOException $e) {
                    $error = 'Gagal mendaftar: ' . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>assets/img/logo.svg">
</head>
<body class="login-body">
    <div class="login-page">
        <div class="login-decoration deco-1"></div>
        <div class="login-decoration deco-2"></div>
        <div class="login-decoration deco-3"></div>

        <div class="login-card register-card">
            <div class="text-center mb-3">
                <div class="login-logo-wrapper">
                    <img src="<?= BASE_URL ?>assets/img/logo.svg" alt="Logo SMK" class="login-logo">
                </div>
                <h3 class="login-title mt-3">Daftar Akun Peminjam</h3>
                <p class="login-subtitle">Buat akun untuk mulai meminjam alat</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-center" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <!-- Baris 1: Username + Nama -->
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small">Username <span class="text-danger">*</span></label>
                        <div class="input-group input-group-login">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" name="username" class="form-control" 
                                   placeholder="cth: budi_123"
                                   value="<?= htmlspecialchars($input['username']) ?>" 
                                   required autofocus>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Nama Lengkap <span class="text-danger">*</span></label>
                        <div class="input-group input-group-login">
                            <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                            <input type="text" name="nama_lengkap" class="form-control"
                                   placeholder="cth: Budi Santoso"
                                   value="<?= htmlspecialchars($input['nama_lengkap']) ?>" 
                                   required>
                        </div>
                    </div>
                </div>

                <!-- Baris 2: Password + Konfirmasi -->
                <div class="row g-2 mt-1">
                    <div class="col-md-6">
                        <label class="form-label small">Password <span class="text-danger">*</span></label>
                        <div class="input-group input-group-login">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" name="password" id="passwordInput"
                                   class="form-control" placeholder="Min. 6 karakter" required>
                            <button type="button" class="btn-toggle-pass" onclick="togglePass('passwordInput','eye1')">
                                <i class="fas fa-eye" id="eye1"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Konfirmasi Password <span class="text-danger">*</span></label>
                        <div class="input-group input-group-login">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" name="password2" id="passwordInput2"
                                   class="form-control" placeholder="Ulangi password" required>
                            <button type="button" class="btn-toggle-pass" onclick="togglePass('passwordInput2','eye2')">
                                <i class="fas fa-eye" id="eye2"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Baris 3: Email + Telepon -->
                <div class="row g-2 mt-1">
                    <div class="col-md-7">
                        <label class="form-label small">Email (opsional)</label>
                        <div class="input-group input-group-login">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" name="email" class="form-control"
                                   placeholder="email@sekolah.sch.id"
                                   value="<?= htmlspecialchars($input['email']) ?>">
                        </div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small">No. Telepon (opsional)</label>
                        <div class="input-group input-group-login">
                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                            <input type="text" name="no_telp" class="form-control"
                                   placeholder="0812xxxx"
                                   value="<?= htmlspecialchars($input['no_telp']) ?>">
                        </div>
                    </div>
                </div>

                <!-- Baris 4: Alamat -->
                <div class="mt-1">
                    <label class="form-label small">Alamat (opsional)</label>
                    <div class="input-group input-group-login">
                        <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                        <textarea name="alamat" class="form-control" rows="2" 
                                  placeholder="Alamat lengkap..."><?= htmlspecialchars($input['alamat']) ?></textarea>
                    </div>
                </div>

                <!-- Info role -->
                <div class="alert alert-warning mt-3 mb-3 small">
                    <i class="fas fa-info-circle me-1"></i>
                    Akun akan terdaftar dengan role <strong>Peminjam</strong>. 
                    Anda bisa langsung login dan mengajukan peminjaman alat.
                </div>

                <button type="submit" class="btn btn-login w-100">
                    <i class="fas fa-user-plus me-2"></i>Daftar Sekarang
                </button>
            </form>

            <div class="login-footer">
                <p class="mb-0 small text-muted">
                    Sudah punya akun? 
                    <a href="<?= BASE_URL ?>index.php" class="fw-bold" style="color: var(--primary-dark);">
                        Login di sini
                    </a>
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePass(inputId, eyeId) {
            const input = document.getElementById(inputId);
            const icon  = document.getElementById(eyeId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>