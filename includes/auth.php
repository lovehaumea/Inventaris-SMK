<?php
// includes/auth.php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function hasRole($roles) {
    if (!isLoggedIn()) return false;
    if (is_array($roles)) {
        return in_array($_SESSION['role'], $roles);
    }
    return $_SESSION['role'] === $roles;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }
}

function requireRole($roles) {
    requireLogin();
    if (!hasRole($roles)) {
        header('Location: ' . BASE_URL . 'pages/dashboard.php?error=akses_ditolak');
        exit;
    }
}

function logActivity($pdo, $user_id, $aktivitas) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $pdo->prepare("INSERT INTO log_aktivitas (id_user, aktivitas, ip_address) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $aktivitas, $ip]);
}
?>