<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin');
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$logs = $pdo->query("SELECT l.*, u.nama_lengkap FROM log_aktivitas l LEFT JOIN users u ON l.id_user=u.id ORDER BY l.id DESC LIMIT 100")->fetchAll();
?>
<div class="content p-4">
    <h2 class="fw-bold mb-4"><i class="fas fa-history me-2"></i>Log Aktivitas</h2>
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr><th>Waktu</th><th>User</th><th>Aktivitas</th><th>IP</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $l): ?>
                    <tr>
                        <td><?= $l['waktu'] ?></td>
                        <td><?= $l['nama_lengkap'] ?? 'System' ?></td>
                        <td><?= $l['aktivitas'] ?></td>
                        <td><?= $l['ip_address'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>