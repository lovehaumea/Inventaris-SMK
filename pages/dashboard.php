<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();

try { $total_alat = $pdo->query("SELECT COUNT(*) FROM alat")->fetchColumn() ?: 0; } catch (Exception $e) { $total_alat = 0; }
try { $total_peminjaman = $pdo->query("SELECT COUNT(*) FROM peminjaman WHERE status IN ('menunggu','disetujui','dipinjam')")->fetchColumn() ?: 0; } catch (Exception $e) { $total_peminjaman = 0; }
try { $total_user = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?: 0; } catch (Exception $e) { $total_user = 0; }
try { $total_denda = $pdo->query("SELECT COALESCE(SUM(denda),0) FROM peminjaman WHERE status='dikembalikan'")->fetchColumn() ?: 0; } catch (Exception $e) { $total_denda = 0; }
try { $total_kategori = $pdo->query("SELECT COUNT(*) FROM kategori")->fetchColumn() ?: 0; } catch (Exception $e) { $total_kategori = 0; }

// ============================================
// STATISTIK TAMBAHAN
// ============================================
try { $peminjaman_menunggu = $pdo->query("SELECT COUNT(*) FROM peminjaman WHERE status='menunggu'")->fetchColumn() ?: 0; } catch (Exception $e) { $peminjaman_menunggu = 0; }
try { $peminjaman_dipinjam = $pdo->query("SELECT COUNT(*) FROM peminjaman WHERE status='dipinjam'")->fetchColumn() ?: 0; } catch (Exception $e) { $peminjaman_dipinjam = 0; }
try { $peminjaman_selesai = $pdo->query("SELECT COUNT(*) FROM peminjaman WHERE status='dikembalikan'")->fetchColumn() ?: 0; } catch (Exception $e) { $peminjaman_selesai = 0; }

// ============================================
// DATA GRAFIK: PEMINJAMAN 7 HARI TERAKHIR
// ============================================
$chart_pinjam_labels = [];
$chart_pinjam_data = [];
for ($i = 6; $i >= 0; $i--) {
    $tgl = date('Y-m-d', strtotime("-$i days"));
    $chart_pinjam_labels[] = date('d M', strtotime($tgl));
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM peminjaman WHERE DATE(created_at) = ?");
        $stmt->execute([$tgl]);
        $chart_pinjam_data[] = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $chart_pinjam_data[] = 0;
    }
}

// ============================================
// DATA GRAFIK: STATUS PEMINJAMAN (DOUGHNUT)
// ============================================
$chart_status_data = [$peminjaman_menunggu, $peminjaman_dipinjam, $peminjaman_selesai];

// ============================================
// DATA GRAFIK: ALAT PER KATEGORI (BAR)
// ============================================
$chart_kategori_labels = [];
$chart_kategori_data = [];
try {
    $stmt = $pdo->query("SELECT k.nama_kategori, COUNT(a.id) as jumlah 
                         FROM kategori k LEFT JOIN alat a ON a.id_kategori = k.id 
                         GROUP BY k.id ORDER BY jumlah DESC LIMIT 6");
    while ($row = $stmt->fetch()) {
        $chart_kategori_labels[] = $row['nama_kategori'];
        $chart_kategori_data[] = (int)$row['jumlah'];
    }
} catch (Exception $e) {}

// ============================================
// TOP 5 ALAT TERPOPULER
// ============================================
$top_alat = [];
try {
    $stmt = $pdo->query("SELECT a.nama_alat, a.kode_alat, COUNT(p.id) as total_dipinjam 
                         FROM alat a LEFT JOIN peminjaman p ON p.id_alat = a.id 
                         GROUP BY a.id ORDER BY total_dipinjam DESC LIMIT 5");
    $top_alat = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================
// AKTIVITAS TERBARU
// ============================================
$aktivitas_terbaru = [];
try {
    $stmt = $pdo->query("SELECT p.kode_pinjam, p.status, p.created_at, a.nama_alat, u.nama_lengkap 
                         FROM peminjaman p 
                         JOIN alat a ON p.id_alat = a.id 
                         JOIN users u ON p.id_user = u.id 
                         ORDER BY p.id DESC LIMIT 5");
    $aktivitas_terbaru = $stmt->fetchAll();
} catch (Exception $e) {}

// Fallback formatRupiah
if (!function_exists('formatRupiah')) {
    function formatRupiah($angka) {
        return 'Rp ' . number_format((float)$angka, 0, ',', '.');
    }
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$namaUser = $_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'User';
$roleUser = $_SESSION['role'] ?? 'peminjam';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<div class="content p-4">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in-up dashboard-heading">
        <div class="d-flex align-items-center gap-3">
            <div>
            <h2 class="fw-bold mb-1">
                <i class="fas fa-chart-line me-2"></i>Dashboard
            </h2>
            <p class="text-muted mb-0 small">
                <i class="fas fa-calendar-alt me-1"></i>
                <?= date('l, d F Y') ?> · 
                <span id="jamLive"><?= date('H:i:s') ?></span> WIB
            </p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="user-chip">
                <div class="user-chip-avatar">
                    <?= strtoupper(substr($namaUser, 0, 1)) ?>
                </div>
                <div class="user-chip-info">
                    <span class="user-chip-name"><?= htmlspecialchars($namaUser) ?></span>
                    <span class="user-chip-role"><?= htmlspecialchars($roleUser) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================
         STAT CARDS (5 CARDS)
         ======================== -->
    <div class="row g-3 mb-4">
        <div class="col-xl col-lg-4 col-md-6 fade-in-up delay-1">
            <div class="stat-card stat-card-1">
                <div class="stat-card-icon"><i class="fas fa-tools"></i></div>
                <div class="stat-card-content">
                    <span class="stat-card-label">Total Alat</span>
                    <h3 class="stat-card-value"><?= (int)$total_alat ?></h3>
                    <span class="stat-card-trend up">
                        <i class="fas fa-arrow-up"></i> Semua unit
                    </span>
                </div>
            </div>
        </div>

        <div class="col-xl col-lg-4 col-md-6 fade-in-up delay-2">
            <div class="stat-card stat-card-2">
                <div class="stat-card-icon"><i class="fas fa-hand-holding"></i></div>
                <div class="stat-card-content">
                    <span class="stat-card-label">Aktif</span>
                    <h3 class="stat-card-value"><?= (int)$total_peminjaman ?></h3>
                    <span class="stat-card-trend">
                        <i class="fas fa-clock"></i> Sedang berjalan
                    </span>
                </div>
            </div>
        </div>

        <div class="col-xl col-lg-4 col-md-6 fade-in-up delay-3">
            <div class="stat-card stat-card-3">
                <div class="stat-card-icon"><i class="fas fa-users"></i></div>
                <div class="stat-card-content">
                    <span class="stat-card-label">Total User</span>
                    <h3 class="stat-card-value"><?= (int)$total_user ?></h3>
                    <span class="stat-card-trend">
                        <i class="fas fa-user-check"></i> Terdaftar
                    </span>
                </div>
            </div>
        </div>

        <div class="col-xl col-lg-4 col-md-6 fade-in-up delay-4">
            <div class="stat-card stat-card-4">
                <div class="stat-card-icon"><i class="fas fa-tags"></i></div>
                <div class="stat-card-content">
                    <span class="stat-card-label">Kategori</span>
                    <h3 class="stat-card-value"><?= (int)$total_kategori ?></h3>
                    <span class="stat-card-trend">
                        <i class="fas fa-layer-group"></i> Jenis alat
                    </span>
                </div>
            </div>
        </div>

        <div class="col-xl col-lg-4 col-md-6 fade-in-up delay-4">
            <div class="stat-card stat-card-5">
                <div class="stat-card-icon"><i class="fas fa-money-bill-wave"></i></div>
                <div class="stat-card-content">
                    <span class="stat-card-label">Total Denda</span>
                    <h3 class="stat-card-value" style="font-size:1.4rem;"><?= formatRupiah($total_denda) ?></h3>
                    <span class="stat-card-trend">
                        <i class="fas fa-coins"></i> Terkumpul
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================
         CHARTS ROW
         ======================== -->
    <div class="row g-3 mb-4">
        <!-- Line Chart -->
        <div class="col-lg-8 fade-in-up delay-1">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-chart-area me-2"></i>Tren Peminjaman 7 Hari Terakhir</span>
                    <span class="badge bg-light text-dark">Realtime</span>
                </div>
                <div class="card-body">
                    <div style="position:relative; height:280px;">
                        <canvas id="chartLine"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Doughnut Chart -->
        <div class="col-lg-4 fade-in-up delay-2">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-chart-pie me-2"></i>Status Peminjaman
                </div>
                <div class="card-body">
                    <div style="position:relative; height:200px;">
                        <canvas id="chartDoughnut"></canvas>
                    </div>
                    <div class="chart-legend mt-3">
                        <div class="legend-item">
                            <span class="legend-dot" style="background:#EAE2D6;"></span>
                            <span>Menunggu</span>
                            <strong><?= $peminjaman_menunggu ?></strong>
                        </div>
                        <div class="legend-item">
                            <span class="legend-dot" style="background:#8B9A6E;"></span>
                            <span>Dipinjam</span>
                            <strong><?= $peminjaman_dipinjam ?></strong>
                        </div>
                        <div class="legend-item">
                            <span class="legend-dot" style="background:#6f7d57;"></span>
                            <span>Selesai</span>
                            <strong><?= $peminjaman_selesai ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <!-- Bar Chart -->
        <div class="col-lg-6 fade-in-up delay-1">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-chart-bar me-2"></i>Alat per Kategori
                </div>
                <div class="card-body">
                    <div style="position:relative; height:260px;">
                        <canvas id="chartBar"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Alat -->
        <div class="col-lg-6 fade-in-up delay-2">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-trophy me-2"></i>Top 5 Alat Terpopuler
                </div>
                <div class="card-body">
                    <?php if (empty($top_alat)): ?>
                        <p class="text-muted text-center py-4 mb-0">Belum ada data peminjaman.</p>
                    <?php else: ?>
                        <?php 
                        $max = max(array_column($top_alat, 'total_dipinjam')) ?: 1;
                        foreach ($top_alat as $i => $a): 
                            $persen = $max > 0 ? ($a['total_dipinjam'] / $max) * 100 : 0;
                        ?>
                        <div class="top-item">
                            <div class="top-item-rank <?= $i == 0 ? 'rank-gold' : ($i == 1 ? 'rank-silver' : ($i == 2 ? 'rank-bronze' : '')) ?>">
                                <?= $i + 1 ?>
                            </div>
                            <div class="top-item-body">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="top-item-name"><?= htmlspecialchars($a['nama_alat']) ?></span>
                                    <span class="top-item-count"><?= $a['total_dipinjam'] ?>x</span>
                                </div>
                                <div class="progress-custom">
                                    <div class="progress-custom-bar" style="width: <?= $persen ?>%"></div>
                                </div>
                                <small class="text-muted"><?= htmlspecialchars($a['kode_alat']) ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================
         AKTIVITAS + QUICK ACTION
         ======================== -->
    <div class="row g-3 mb-4">
        <!-- Aktivitas Terbaru -->
        <div class="col-lg-8 fade-in-up delay-1">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-clock-rotate-left me-2"></i>Aktivitas Peminjaman Terbaru</span>
                    <a href="peminjaman.php" class="btn btn-sm btn-light">Lihat Semua <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($aktivitas_terbaru)): ?>
                        <p class="text-muted text-center py-5 mb-0">Belum ada aktivitas.</p>
                    <?php else: ?>
                    <div class="activity-list">
                        <?php foreach ($aktivitas_terbaru as $act): 
                            $statusClass = match($act['status']) {
                                'menunggu' => 'pending',
                                'disetujui' => 'approved',
                                'dipinjam' => 'active',
                                'dikembalikan' => 'done',
                                default => 'rejected'
                            };
                            $statusIcon = match($act['status']) {
                                'menunggu' => 'fa-hourglass-half',
                                'disetujui' => 'fa-check',
                                'dipinjam' => 'fa-hand-holding',
                                'dikembalikan' => 'fa-undo',
                                default => 'fa-times'
                            };
                        ?>
                        <div class="activity-item">
                            <div class="activity-icon activity-<?= $statusClass ?>">
                                <i class="fas <?= $statusIcon ?>"></i>
                            </div>
                            <div class="activity-body">
                                <div class="d-flex justify-content-between">
                                    <span class="activity-title">
                                        <strong><?= htmlspecialchars($act['nama_lengkap']) ?></strong>
                                        meminjam <strong><?= htmlspecialchars($act['nama_alat']) ?></strong>
                                    </span>
                                    <span class="badge bg-<?= $statusClass === 'pending' ? 'warning text-dark' : ($statusClass === 'active' ? 'dark' : ($statusClass === 'done' ? 'success' : ($statusClass === 'approved' ? 'secondary' : 'danger'))) ?>">
                                        <?= $act['status'] ?>
                                    </span>
                                </div>
                                <div class="activity-meta">
                                    <i class="fas fa-hashtag"></i> <?= $act['kode_pinjam'] ?>
                                    · <i class="fas fa-clock"></i> <?= date('d M Y, H:i', strtotime($act['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Action -->
        <div class="col-lg-4 fade-in-up delay-2">
            <div class="card h-100">
                <div class="card-header">
                    <i class="fas fa-bolt me-2"></i>Aksi Cepat
                </div>
                <div class="card-body">
                    <div class="quick-action-grid">
                        <?php if (hasRole('admin')): ?>
                            <a href="users.php" class="quick-action">
                                <i class="fas fa-users"></i>
                                <span>Kelola User</span>
                            </a>
                            <a href="alat.php" class="quick-action">
                                <i class="fas fa-tools"></i>
                                <span>Kelola Alat</span>
                            </a>
                            <a href="kategori.php" class="quick-action">
                                <i class="fas fa-tags"></i>
                                <span>Kategori</span>
                            </a>
                            <a href="laporan.php" class="quick-action">
                                <i class="fas fa-print"></i>
                                <span>Laporan</span>
                            </a>
                            <a href="log.php" class="quick-action">
                                <i class="fas fa-history"></i>
                                <span>Log Aktivitas</span>
                            </a>
                        <?php endif; ?>
                        <?php if (hasRole(['admin','petugas'])): ?>
                            <a href="peminjaman.php" class="quick-action">
                                <i class="fas fa-hand-holding"></i>
                                <span>Peminjaman</span>
                            </a>
                            <a href="pengembalian.php" class="quick-action">
                                <i class="fas fa-undo"></i>
                                <span>Pengembalian</span>
                            </a>
                        <?php endif; ?>
                        <?php if (hasRole('peminjam')): ?>
                            <a href="alat.php" class="quick-action">
                                <i class="fas fa-list"></i>
                                <span>Daftar Alat</span>
                            </a>
                            <a href="peminjaman.php?action=create" class="quick-action">
                                <i class="fas fa-plus-circle"></i>
                                <span>Ajukan Pinjam</span>
                            </a>
                            <a href="peminjaman.php" class="quick-action">
                                <i class="fas fa-history"></i>
                                <span>Riwayat</span>
                            </a>
                            <a href="pengembalian.php" class="quick-action">
                                <i class="fas fa-undo"></i>
                                <span>Kembalikan</span>
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Info Ringkas -->
                    <div class="info-box mt-3">
                        <div class="info-box-row">
                            <span><i class="fas fa-hourglass-half me-2 text-warning"></i>Menunggu</span>
                            <strong><?= $peminjaman_menunggu ?></strong>
                        </div>
                        <div class="info-box-row">
                            <span><i class="fas fa-hand-holding me-2 text-primary"></i>Dipinjam</span>
                            <strong><?= $peminjaman_dipinjam ?></strong>
                        </div>
                        <div class="info-box-row">
                            <span><i class="fas fa-check-circle me-2 text-success"></i>Selesai</span>
                            <strong><?= $peminjaman_selesai ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>



<!-- ========================
     SCRIPT CHART.JS
     ======================== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Warna palet
    const olive = '#8B9A6E';
    const oliveDark = '#6f7d57';
    const oliveLight = '#a3b184';
    const beige = '#EAE2D6';
    const cream = '#F7F2EB';
    const grey = '#EEEEEE';

    // ============ CHART LINE ============
    const ctxLine = document.getElementById('chartLine');
    if (ctxLine) {
        const gradient = ctxLine.getContext('2d').createLinearGradient(0, 0, 0, 280);
        gradient.addColorStop(0, 'rgba(139, 154, 110, 0.35)');
        gradient.addColorStop(1, 'rgba(139, 154, 110, 0.02)');

        new Chart(ctxLine, {
            type: 'line',
            data: {
                labels: <?= json_encode($chart_pinjam_labels) ?>,
                datasets: [{
                    label: 'Jumlah Peminjaman',
                    data: <?= json_encode($chart_pinjam_data) ?>,
                    borderColor: olive,
                    backgroundColor: gradient,
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: olive,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 3,
                    pointRadius: 5,
                    pointHoverRadius: 8,
                    pointHoverBackgroundColor: oliveDark,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: oliveDark,
                        titleColor: cream,
                        bodyColor: cream,
                        padding: 12,
                        borderRadius: 10,
                        displayColors: false,
                        titleFont: { family: 'Poppins', size: 13, weight: '600' },
                        bodyFont: { family: 'Poppins', size: 12 },
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { 
                            color: '#8a8f80',
                            font: { family: 'Poppins', size: 11 },
                            stepSize: 1,
                            precision: 0
                        },
                        grid: { color: grey, drawBorder: false }
                    },
                    x: {
                        ticks: { 
                            color: '#8a8f80',
                            font: { family: 'Poppins', size: 11 }
                        },
                        grid: { display: false }
                    }
                },
                interaction: { intersect: false, mode: 'index' }
            }
        });
    }

    // ============ CHART DOUGHNUT ============
    const ctxDoughnut = document.getElementById('chartDoughnut');
    if (ctxDoughnut) {
        new Chart(ctxDoughnut, {
            type: 'doughnut',
            data: {
                labels: ['Menunggu', 'Dipinjam', 'Selesai'],
                datasets: [{
                    data: <?= json_encode($chart_status_data) ?>,
                    backgroundColor: [beige, olive, oliveDark],
                    borderColor: '#fff',
                    borderWidth: 4,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: oliveDark,
                        titleColor: cream,
                        bodyColor: cream,
                        padding: 12,
                        borderRadius: 10,
                        titleFont: { family: 'Poppins', size: 13, weight: '600' },
                        bodyFont: { family: 'Poppins', size: 12 },
                    }
                }
            }
        });
    }

    // ============ CHART BAR ============
    const ctxBar = document.getElementById('chartBar');
    if (ctxBar) {
        new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chart_kategori_labels) ?>,
                datasets: [{
                    label: 'Jumlah Alat',
                    data: <?= json_encode($chart_kategori_data) ?>,
                    backgroundColor: [
                        olive, oliveLight, beige, 
                        '#a3b184', '#c9d3b0', '#d9e0c7'
                    ],
                    borderRadius: 10,
                    borderSkipped: false,
                    barThickness: 36,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: oliveDark,
                        titleColor: cream,
                        bodyColor: cream,
                        padding: 12,
                        borderRadius: 10,
                        displayColors: false,
                        titleFont: { family: 'Poppins', size: 13, weight: '600' },
                        bodyFont: { family: 'Poppins', size: 12 },
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { 
                            color: '#8a8f80', 
                            font: { family: 'Poppins', size: 11 },
                            stepSize: 1,
                            precision: 0
                        },
                        grid: { color: grey, drawBorder: false }
                    },
                    x: {
                        ticks: { 
                            color: '#8a8f80',
                            font: { family: 'Poppins', size: 11 }
                        },
                        grid: { display: false }
                    }
                }
            }
        });
    }

    // ============ JAM LIVE ============
    setInterval(function() {
        const jam = document.getElementById('jamLive');
        if (jam) {
            const now = new Date();
            const h = String(now.getHours()).padStart(2, '0');
            const m = String(now.getMinutes()).padStart(2, '0');
            const s = String(now.getSeconds()).padStart(2, '0');
            jam.textContent = h + ':' + m + ':' + s;
        }
    }, 1000);
});
</script>

<?php require_once '../includes/footer.php'; ?>