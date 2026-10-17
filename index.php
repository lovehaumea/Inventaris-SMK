<?php
// index.php — Halaman Login
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Jika sudah login, langsung redirect ke dashboard
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . 'pages/dashboard.php');
    exit;
}

$error = '';
$username_input = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username_input = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username_input === '' || $password === '') {
        $error = 'Username dan password wajib diisi!';
    } else {
        // Gunakan direct query (lebih stabil dari stored procedure)
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'aktif' LIMIT 1");
        $stmt->execute([$username_input]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Regenerasi session ID untuk keamanan
            session_regenerate_id(true);

            // Set session dengan pengaman (fallback jika kolom kosong)
            $_SESSION['user_id']      = $user['id'];
            $_SESSION['username']     = !empty($user['username']) ? $user['username'] : 'user';
            $_SESSION['nama_lengkap'] = !empty($user['nama_lengkap']) ? $user['nama_lengkap'] : $user['username'];
            $_SESSION['role']         = !empty($user['role']) ? $user['role'] : 'peminjam';

            // Catat log aktivitas
            logActivity($pdo, $user['id'], 'Login ke sistem');

            // Redirect ke dashboard
            header('Location: ' . BASE_URL . 'pages/dashboard.php');
            exit;
        } else {
            $error = 'Username atau password salah, atau akun tidak aktif.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts: Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>assets/img/logo.svg">
</head>
<body class="login-body">
    <div class="login-page">
        <!-- Dekorasi background -->
        <div class="login-decoration deco-1"></div>
        <div class="login-decoration deco-2"></div>
        <div class="login-decoration deco-3"></div>

        <div class="login-card">
            <!-- Logo & Header -->
            <div class="text-center mb-4">
                <div class="login-logo-wrapper">
                    <img src="<?= BASE_URL ?>assets/img/logo.svg" alt="Logo SMK" class="login-logo">
                </div>
                <h3 class="login-title mt-3">SMK Peminjaman Alat</h3>
                <p class="login-subtitle">Silakan login untuk melanjutkan</p>
            </div>

            <!-- Pesan Error -->
            <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-center" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <!-- Form Login -->
            <form method="POST" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <div class="input-group input-group-login">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input 
                            type="text" 
                            name="username" 
                            class="form-control" 
                            placeholder="Masukkan username"
                            value="<?= htmlspecialchars($username_input) ?>"
                            required 
                            autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <div class="input-group input-group-login">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input 
                            type="password" 
                            name="password" 
                            id="passwordInput"
                            class="form-control" 
                            placeholder="Masukkan password" 
                            required>
                        <button type="button" class="btn btn-toggle-pass" onclick="togglePassword()">
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-login w-100">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </button>
            </form>

            <!-- Info Akun Default -->
            <div class="login-footer">
                <p class="mb-2 small text-muted">Belum punya akun?</p>
                <a href="<?= BASE_URL ?>register.php" class="btn btn-outline-secondary btn-sm w-100 mb-3">
                    <i class="fas fa-user-plus me-1"></i>Daftar Sebagai Peminjam
            </a>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle show/hide password
        function togglePassword() {
            const input = document.getElementById('passwordInput');
            const icon  = document.getElementById('eyeIcon');
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