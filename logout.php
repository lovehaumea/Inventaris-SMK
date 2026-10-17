<?php
require_once 'includes/auth.php';
if (isLoggedIn()) {
    logActivity($pdo, $_SESSION['user_id'], 'Logout dari sistem');
}
session_destroy();
header('Location: ' . BASE_URL . 'index.php');
exit;
?>