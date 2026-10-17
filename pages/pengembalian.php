<?php
// pages/pengembalian.php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();

if (!function_exists('formatRupiah')) {
    function formatRupiah($angka) {
        return 'Rp ' . number_format((float)$angka, 0, ',', '.');
    }
}

// ============================================
// PROSES KEMBALIKAN ALAT
// ============================================
if (hasRole(['admin', 'petugas']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kembalikan'])) {
    try {
        $pdo->beginTransaction();

        $id_peminjaman   = (int)$_POST['id_peminjaman'];
        $tanggal_kembali = $_POST['tanggal_kembali'];
        $kondisi         = $_POST['kondisi_alat'];
        $keterangan      = $_POST['keterangan'] ?? '';
        $id_petugas      = $_SESSION['user_id'] ?? 1;

        $stmt = $pdo->prepare("SELECT * FROM peminjaman WHERE id = ? AND status = 'dipinjam'");
        $stmt->execute([$id_peminjaman]);
        $pinjam = $stmt->fetch();

        if (!$pinjam) {
            throw new Exception('Data peminjaman tidak ditemukan atau sudah dikembalikan.');
        }

        // Denda keterlambatan Rp 5.000 / hari
        $denda_per_hari = 5000;
        $hari_terlambat = max(0, (strtotime($tanggal_kembali) - strtotime($pinjam['tanggal_kembali_rencana'])) / 86400);
        $denda          = $hari_terlambat * $denda_per_hari;

        // Insert pengembalian
        $stmt = $pdo->prepare("INSERT INTO pengembalian 
            (id_peminjaman, tanggal_kembali, denda, kondisi_alat, id_petugas, keterangan) 
            VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id_peminjaman, $tanggal_kembali, $denda, $kondisi, $id_petugas, $keterangan]);

        // Update peminjaman
        $stmt = $pdo->prepare("UPDATE peminjaman 
            SET status='dikembalikan', tanggal_kembali_actual=?, denda=? 
            WHERE id=?");
        $stmt->execute([$tanggal_kembali, $denda, $id_peminjaman]);

        // ============================================
        // KEMBALIKAN STOK BERDASARKAN KONDISI
        // ============================================
        $jumlah = (int)$pinjam['jumlah'];

        if ($kondisi === 'baik') {
            $stmt = $pdo->prepare("UPDATE alat SET stok_tersedia = stok_tersedia + ? WHERE id = ?");
            $stmt->execute([$jumlah, $pinjam['id_alat']]);
        } elseif ($kondisi === 'rusak ringan') {
            $stmt = $pdo->prepare("UPDATE alat SET stok_rusak_ringan = stok_rusak_ringan + ? WHERE id = ?");
            $stmt->execute([$jumlah, $pinjam['id_alat']]);
        } elseif ($kondisi === 'rusak berat') {
            $stmt = $pdo->prepare("UPDATE alat SET stok_rusak_berat = stok_rusak_berat + ? WHERE id = ?");
            $stmt->execute([$jumlah, $pinjam['id_alat']]);
        } else {
            throw new Exception('Kondisi alat tidak dikenali: ' . $kondisi);
        }

        $pdo->commit();

        $pesanKondisi = '';
        if ($kondisi === 'rusak ringan') {
            $pesanKondisi = " ({$jumlah} unit masuk stok RUSAK RINGAN, perlu tindak lanjut admin)";
        } elseif ($kondisi === 'rusak berat') {
            $pesanKondisi = " ({$jumlah} unit masuk stok RUSAK BERAT, perlu tindak lanjut admin)";
        }

        logActivity($pdo, $id_petugas, "Mengembalikan alat ID Peminjaman: $id_peminjaman | Kondisi: $kondisi");
        flashMessage('success', 'Alat berhasil dikembalikan' . $pesanKondisi . ($denda > 0 ? ' dengan denda ' . formatRupiah($denda) : ''));

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flashMessage('danger', 'Gagal: ' . $e->getMessage());
    }

    header('Location: pengembalian.php');
    exit;
}

// ============================================
// AMBIL DATA
// ============================================

// --- AUTO: peminjaman yang sudah 'disetujui' langsung dianggap 'dipinjam' ---
// (fallback kalau proses approve belum diubah; idealnya ubah di halaman approve)
try {
    $id_petugas_auto = $_SESSION['user_id'] ?? 1;
    $stmt = $pdo->prepare("
        UPDATE peminjaman
        SET status = 'dipinjam',
            id_petugas = COALESCE(id_petugas, ?)
        WHERE status = 'disetujui'
    ");
    $stmt->execute([$id_petugas_auto]);
} catch (Exception $e) {}

$peminjaman = [];
try {
    $stmt = $pdo->query("SELECT p.*, a.nama_alat, a.kode_alat, 
                                a.stok_tersedia, a.stok_rusak_ringan, a.stok_rusak_berat,
                                u.nama_lengkap 
                         FROM peminjaman p 
                         JOIN alat a ON p.id_alat = a.id 
                         JOIN users u ON p.id_user = u.id 
                         WHERE p.status = 'dipinjam' 
                         ORDER BY p.id DESC");
    $peminjaman = $stmt->fetchAll();
} catch (Exception $e) {}

$riwayat = [];
try {
    $stmt = $pdo->query("SELECT pg.*, p.kode_pinjam, a.nama_alat, u.nama_lengkap 
                         FROM pengembalian pg 
                         JOIN peminjaman p ON pg.id_peminjaman = p.id 
                         JOIN alat a ON p.id_alat = a.id 
                         JOIN users u ON p.id_user = u.id 
                         ORDER BY pg.id DESC LIMIT 50");
    $riwayat = $stmt->fetchAll();
} catch (Exception $e) {}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="content p-4">

    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in-up">
        <div>
            <h2 class="fw-bold mb-1">
                <i class="fas fa-undo me-2"></i>Pengembalian Alat
            </h2>
            <p class="text-muted mb-0 small">
                <i class="fas fa-info-circle me-1"></i>
                Kelola pengembalian alat yang sedang dipinjam
            </p>
        </div>
    </div>

    <!-- FLASH -->
    <?php if ($flash = getFlash()): ?>
        <div class="alert alert-<?= $flash['type'] ?>">
            <i class="fas <?= $flash['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> me-2"></i>
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <!-- ALAT SEDANG DIPINJAM -->
    <?php if (hasRole(['admin', 'petugas'])): ?>
        <div class="card mb-4 fade-in-up delay-1">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-hand-holding me-2"></i>Alat Sedang Dipinjam</span>
                <span class="badge bg-light text-dark"><?= count($peminjaman) ?> item</span>
            </div>
            <div class="card-body">
                <?php if (empty($peminjaman)): ?>
                    <p class="text-muted text-center py-4 mb-0">
                        <i class="fas fa-inbox fa-2x d-block mb-2 opacity-50"></i>
                        Tidak ada alat yang sedang dipinjam.
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Kode Pinjam</th>
                                    <th>Peminjam</th>
                                    <th>Alat</th>
                                    <th>Jumlah</th>
                                    <th>Tgl Rencana Kembali</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($peminjaman as $p):
                                    $terlambat = strtotime(date('Y-m-d')) > strtotime($p['tanggal_kembali_rencana']);
                                ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($p['kode_pinjam']) ?></strong></td>
                                        <td><?= htmlspecialchars($p['nama_lengkap']) ?></td>
                                        <td><?= htmlspecialchars($p['nama_alat']) ?></td>
                                        <td><?= (int)$p['jumlah'] ?></td>
                                        <td>
                                            <?= date('d M Y', strtotime($p['tanggal_kembali_rencana'])) ?>
                                            <?php if ($terlambat): ?>
                                                <span class="badge bg-danger ms-1">Terlambat</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-dark" data-bs-toggle="modal" data-bs-target="#modalKembali<?= $p['id'] ?>">
                                                <i class="fas fa-undo me-1"></i>Kembalikan
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- MODAL KEMBALIKAN -->
        <?php foreach ($peminjaman as $p): ?>
            <div class="modal fade" id="modalKembali<?= $p['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                    <form method="POST" class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fas fa-undo me-2"></i>Kembalikan Alat</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="id_peminjaman" value="<?= $p['id'] ?>">

                            <div class="info-box mb-3">
                                <div class="info-box-row"><span>Kode Pinjam</span><strong><?= htmlspecialchars($p['kode_pinjam']) ?></strong></div>
                                <div class="info-box-row"><span>Alat</span><strong><?= htmlspecialchars($p['nama_alat']) ?></strong></div>
                                <div class="info-box-row"><span>Jumlah</span><strong><?= (int)$p['jumlah'] ?> unit</strong></div>
                                <div class="info-box-row"><span>Rencana Kembali</span><strong><?= date('d M Y', strtotime($p['tanggal_kembali_rencana'])) ?></strong></div>
                            </div>

                            <div class="alert alert-light border small mb-3">
                                <div class="fw-semibold mb-1"><i class="fas fa-boxes me-1"></i>Stok Alat Saat Ini</div>
                                <div class="d-flex justify-content-between"><span>Tersedia</span><strong class="text-success"><?= (int)$p['stok_tersedia'] ?> unit</strong></div>
                                <div class="d-flex justify-content-between"><span>Rusak Ringan</span><strong class="text-warning"><?= (int)$p['stok_rusak_ringan'] ?> unit</strong></div>
                                <div class="d-flex justify-content-between"><span>Rusak Berat</span><strong class="text-danger"><?= (int)$p['stok_rusak_berat'] ?> unit</strong></div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Tanggal Kembali Aktual</label>
                                <input type="date" name="tanggal_kembali" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Kondisi Alat</label>
                                <select name="kondisi_alat" class="form-select" required>
                                    <option value="baik">✅ Baik</option>
                                    <option value="rusak ringan">⚠️ Rusak Ringan</option>
                                    <option value="rusak berat">❌ Rusak Berat</option>
                                </select>
                                <div class="form-text">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Alat yang rusak <strong>tidak akan</strong> menambah stok tersedia —
                                    dipisahkan ke stok rusak dan menunggu tindak lanjut admin.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Keterangan (opsional)</label>
                                <textarea name="keterangan" class="form-control" rows="2" placeholder="Catatan tambahan..."></textarea>
                            </div>

                            <div class="alert alert-warning mb-0 small">
                                <i class="fas fa-info-circle me-1"></i>
                                Denda keterlambatan: <strong>Rp 5.000 / hari</strong>. Denda dihitung otomatis.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" name="kembalikan" class="btn btn-dark">
                                <i class="fas fa-check me-1"></i>Konfirmasi
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- RIWAYAT -->
    <div class="card fade-in-up delay-2">
        <div class="card-header"><i class="fas fa-history me-2"></i>Riwayat Pengembalian</div>
        <div class="card-body">
            <?php if (empty($riwayat)): ?>
                <p class="text-muted text-center py-4 mb-0">
                    <i class="fas fa-inbox fa-2x d-block mb-2 opacity-50"></i>
                    Belum ada riwayat pengembalian.
                </p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Kode Pinjam</th>
                                <th>Peminjam</th>
                                <th>Alat</th>
                                <th>Tgl Kembali</th>
                                <th>Denda</th>
                                <th>Kondisi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($riwayat as $r): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($r['kode_pinjam']) ?></strong></td>
                                    <td><?= htmlspecialchars($r['nama_lengkap']) ?></td>
                                    <td><?= htmlspecialchars($r['nama_alat']) ?></td>
                                    <td><?= date('d M Y', strtotime($r['tanggal_kembali'])) ?></td>
                                    <td>
                                        <?php if ($r['denda'] > 0): ?>
                                            <span class="badge bg-danger"><?= formatRupiah($r['denda']) ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Rp 0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $r['kondisi_alat'] === 'baik' ? 'success' : ($r['kondisi_alat'] === 'rusak ringan' ? 'warning text-dark' : 'danger') ?>">
                                            <?= htmlspecialchars($r['kondisi_alat']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once '../includes/footer.php'; ?>