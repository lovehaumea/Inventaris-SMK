<?php
// pages/kelola_rusak.php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole(['admin']);

// ============================================
// PROSES AKSI ADMIN TERHADAP STOK RUSAK
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi_rusak'])) {
    try {
        $pdo->beginTransaction();

        $idAlat      = (int)$_POST['id_alat'];
        $aksi        = $_POST['aksi'] ?? '';
        $dariKondisi = $_POST['dari_kondisi'] ?? '';
        $jumlah      = (int)($_POST['jumlah'] ?? 0);
        $keterangan  = trim($_POST['keterangan'] ?? '');
        $idAdmin     = $_SESSION['user_id'];

        // Validasi
        if ($jumlah <= 0) {
            throw new Exception('Jumlah harus lebih dari 0.');
        }
        if (!in_array($aksi, ['perbaiki', 'buang', 'turun_kondisi'], true)) {
            throw new Exception('Aksi tidak valid.');
        }
        if (!in_array($dariKondisi, ['rusak_ringan', 'rusak_berat'], true)) {
            throw new Exception('Sumber kondisi tidak valid.');
        }
        if ($aksi === 'turun_kondisi' && $dariKondisi !== 'rusak_ringan') {
            throw new Exception('Turun kondisi hanya berlaku dari Rusak Ringan ke Rusak Berat.');
        }

        // Lock baris alat
        $stmt = $pdo->prepare("SELECT * FROM alat WHERE id = ? FOR UPDATE");
        $stmt->execute([$idAlat]);
        $alat = $stmt->fetch();

        if (!$alat) {
            throw new Exception('Alat tidak ditemukan.');
        }

        $kolomSumber = $dariKondisi === 'rusak_ringan' ? 'stok_rusak_ringan' : 'stok_rusak_berat';
        $stokSumber  = (int)$alat[$kolomSumber];

        if ($jumlah > $stokSumber) {
            throw new Exception("Jumlah melebihi stok rusak yang tersedia ($stokSumber unit).");
        }

        // Eksekusi aksi
        if ($aksi === 'perbaiki') {
            // rusak -> tersedia
            $stmt = $pdo->prepare("
                UPDATE alat SET
                    {$kolomSumber} = {$kolomSumber} - ?,
                    stok_tersedia = stok_tersedia + ?
                WHERE id = ?
            ");
            $stmt->execute([$jumlah, $jumlah, $idAlat]);
            $pesanAksi = "diperbaiki dan dikembalikan ke Stok Tersedia";

        } elseif ($aksi === 'buang') {
            // rusak -> hilang, kurangi stok total juga
            $stmt = $pdo->prepare("
                UPDATE alat SET
                    {$kolomSumber} = {$kolomSumber} - ?,
                    stok = GREATEST(0, stok - ?)
                WHERE id = ?
            ");
            $stmt->execute([$jumlah, $jumlah, $idAlat]);
            $pesanAksi = "dibuang dari inventaris";

        } else { // turun_kondisi
            // rusak ringan -> rusak berat
            $stmt = $pdo->prepare("
                UPDATE alat SET
                    stok_rusak_ringan = stok_rusak_ringan - ?,
                    stok_rusak_berat  = stok_rusak_berat  + ?
                WHERE id = ?
            ");
            $stmt->execute([$jumlah, $jumlah, $idAlat]);
            $pesanAksi = "diturunkan kondisinya menjadi Rusak Berat";
        }

        // Log tindakan
        $stmt = $pdo->prepare("
            INSERT INTO tindakan_rusak
            (id_alat, aksi, dari_kondisi, jumlah, keterangan, id_admin)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$idAlat, $aksi, $dariKondisi, $jumlah, $keterangan, $idAdmin]);

        $pdo->commit();

        try {
            logActivity($pdo, $idAdmin, "Aksi stok rusak: $aksi pada alat ID $idAlat, jumlah $jumlah");
        } catch (Throwable $e) {}

        flashMessage('success', "$jumlah unit berhasil $pesanAksi.");

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flashMessage('danger', 'Gagal: ' . $e->getMessage());
    }

    header('Location: kelola_rusak.php');
    exit;
}

// ============================================
// AMBIL DATA
// ============================================
$alatRusak = [];
try {
    $stmt = $pdo->query("
        SELECT a.*, k.nama_kategori
        FROM alat a
        LEFT JOIN kategori k ON a.id_kategori = k.id
        WHERE a.stok_rusak_ringan > 0 OR a.stok_rusak_berat > 0
        ORDER BY a.nama_alat ASC
    ");
    $alatRusak = $stmt->fetchAll();
} catch (Exception $e) {}

$riwayat = [];
try {
    $stmt = $pdo->query("
        SELECT t.*, a.nama_alat, a.kode_alat, u.nama_lengkap AS nama_admin
        FROM tindakan_rusak t
        LEFT JOIN alat a  ON t.id_alat = a.id
        LEFT JOIN users u ON t.id_admin = u.id
        ORDER BY t.id DESC
        LIMIT 100
    ");
    $riwayat = $stmt->fetchAll();
} catch (Exception $e) {}

function e($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="content p-4">

    <!-- ============================================
         HEADER dengan tombol kembali
    ============================================ -->
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in-up flex-wrap gap-2">
        <div>
            <h2 class="fw-bold mb-1">
                <i class="fas fa-tools me-2"></i>Kelola Stok Rusak
            </h2>
            <p class="text-muted mb-0 small">
                <i class="fas fa-info-circle me-1"></i>
                Tindak lanjuti alat yang rusak: perbaiki, buang, atau turunkan kondisinya.
            </p>
        </div>

        <div class="d-flex gap-2">
            <a href="alat.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Kembali ke Manajemen Alat
            </a>
        </div>
    </div>

    <!-- FLASH -->
    <?php if ($flash = getFlash()): ?>
        <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
            <i class="fas <?= $flash['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> me-2"></i>
            <?= htmlspecialchars($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- INFO BOX -->
    <div class="alert alert-light border mb-4 small">
        <div class="fw-semibold mb-1"><i class="fas fa-lightbulb me-1"></i>Panduan Tindakan</div>
        <ul class="mb-0 ps-3">
            <li><strong>Perbaiki</strong> — alat rusak yang berhasil diperbaiki, dikembalikan ke stok tersedia.</li>
            <li><strong>Buang</strong> — alat rusak yang tidak bisa dipakai lagi, dihapus dari inventaris (stok total ikut berkurang).</li>
            <li><strong>Turunkan ke Rusak Berat</strong> — rusak ringan yang ternyata makin parah.</li>
        </ul>
    </div>

    <!-- TABEL ALAT RUSAK -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-exclamation-triangle me-2 text-warning"></i>Daftar Alat dengan Stok Rusak</span>
            <span class="badge bg-light text-dark"><?= count($alatRusak) ?> alat</span>
        </div>
        <div class="card-body">
            <?php if (empty($alatRusak)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-check-circle fa-3x mb-3 text-success opacity-75"></i>
                    <h5 class="mb-1">Tidak ada alat rusak</h5>
                    <p class="mb-0 small">Semua alat dalam kondisi baik atau tersedia.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Foto</th>
                                <th>Kode</th>
                                <th>Nama Alat</th>
                                <th>Kategori</th>
                                <th>Stok Tersedia</th>
                                <th>Rusak Ringan</th>
                                <th>Rusak Berat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alatRusak as $a): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($a['foto_alat'])): ?>
                                            <img src="<?= e('../' . ltrim($a['foto_alat'], '/')) ?>"
                                                 class="rounded"
                                                 style="width:58px;height:58px;object-fit:cover;">
                                        <?php else: ?>
                                            <div class="rounded bg-light d-flex align-items-center justify-content-center"
                                                 style="width:58px;height:58px;">
                                                <i class="fas fa-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($a['kode_alat']) ?></td>
                                    <td class="fw-semibold"><?= e($a['nama_alat']) ?></td>
                                    <td><?= e($a['nama_kategori'] ?? '-') ?></td>
                                    <td>
                                        <span class="badge bg-success"><?= (int)$a['stok_tersedia'] ?></span>
                                    </td>
                                    <td>
                                        <?php if ((int)$a['stok_rusak_ringan'] > 0): ?>
                                            <span class="badge bg-warning text-dark"><?= (int)$a['stok_rusak_ringan'] ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ((int)$a['stok_rusak_berat'] > 0): ?>
                                            <span class="badge bg-danger"><?= (int)$a['stok_rusak_berat'] ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-dark"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalKelola"
                                                data-id="<?= (int)$a['id'] ?>"
                                                data-nama="<?= e($a['nama_alat']) ?>"
                                                data-kode="<?= e($a['kode_alat']) ?>"
                                                data-rr="<?= (int)$a['stok_rusak_ringan'] ?>"
                                                data-rb="<?= (int)$a['stok_rusak_berat'] ?>">
                                            <i class="fas fa-tools me-1"></i>Kelola
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

    <!-- RIWAYAT TINDAKAN -->
    <div class="card shadow-sm border-0">
        <div class="card-header">
            <i class="fas fa-history me-2"></i>Riwayat Tindakan Stok Rusak
        </div>
        <div class="card-body">
            <?php if (empty($riwayat)): ?>
                <p class="text-muted text-center py-4 mb-0">
                    <i class="fas fa-inbox fa-2x d-block mb-2 opacity-50"></i>
                    Belum ada tindakan.
                </p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Alat</th>
                                <th>Dari</th>
                                <th>Aksi</th>
                                <th>Jumlah</th>
                                <th>Admin</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($riwayat as $r): ?>
                                <tr>
                                    <td class="small text-muted"><?= date('d M Y H:i', strtotime($r['tanggal'])) ?></td>
                                    <td>
                                        <strong><?= e($r['nama_alat'] ?? '—') ?></strong><br>
                                        <small class="text-muted"><?= e($r['kode_alat'] ?? '') ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $r['dari_kondisi'] === 'rusak_ringan' ? 'warning text-dark' : 'danger' ?>">
                                            <?= e(str_replace('_', ' ', $r['dari_kondisi'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $aksiLabel = [
                                            'perbaiki'      => ['Perbaiki', 'primary'],
                                            'buang'         => ['Buang', 'dark'],
                                            'turun_kondisi' => ['Turun Kondisi', 'secondary'],
                                        ];
                                        $lbl = $aksiLabel[$r['aksi']] ?? [$r['aksi'], 'secondary'];
                                        ?>
                                        <span class="badge bg-<?= $lbl[1] ?>"><?= e($lbl[0]) ?></span>
                                    </td>
                                    <td><?= (int)$r['jumlah'] ?> unit</td>
                                    <td><?= e($r['nama_admin'] ?? '—') ?></td>
                                    <td class="small text-muted"><?= e($r['keterangan'] ?: '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- ==========================================================
     MODAL KELOLA
========================================================== -->
<div class="modal fade" id="modalKelola" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content">
            <input type="hidden" name="aksi_rusak" value="1">
            <input type="hidden" name="id_alat" id="k_id_alat">

            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="fas fa-tools me-2"></i>Kelola Stok Rusak</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <div class="info-box mb-3">
                    <div class="info-box-row">
                        <span>Nama Alat</span>
                        <strong id="k_nama">—</strong>
                    </div>
                    <div class="info-box-row">
                        <span>Kode</span>
                        <strong id="k_kode">—</strong>
                    </div>
                    <div class="info-box-row">
                        <span>Rusak Ringan</span>
                        <strong class="text-warning" id="k_rr">0 unit</strong>
                    </div>
                    <div class="info-box-row">
                        <span>Rusak Berat</span>
                        <strong class="text-danger" id="k_rb">0 unit</strong>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Sumber Kondisi</label>
                    <select name="dari_kondisi" id="k_dari" class="form-select" required>
                        <option value="rusak_ringan">Dari Rusak Ringan</option>
                        <option value="rusak_berat">Dari Rusak Berat</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Tindakan</label>
                    <select name="aksi" id="k_aksi" class="form-select" required>
                        <option value="perbaiki">🔧 Perbaiki &rarr; kembali ke Stok Tersedia</option>
                        <option value="buang">🗑️ Buang &rarr; hapus dari inventaris</option>
                        <option value="turun_kondisi">⬇️ Turunkan ke Rusak Berat</option>
                    </select>
                    <div class="form-text" id="k_aksi_hint"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Jumlah</label>
                    <input type="number" name="jumlah" id="k_jumlah" class="form-control" min="1" value="1" required>
                    <div class="form-text" id="k_max_info"></div>
                </div>

                <div class="mb-0">
                    <label class="form-label fw-semibold">Keterangan</label>
                    <textarea name="keterangan" class="form-control" rows="2"
                              placeholder="Misal: rusak karena jatuh, diganti komponen, dsb."></textarea>
                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-dark">
                    <i class="fas fa-check me-1"></i>Konfirmasi
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const modal    = document.getElementById('modalKelola');
    if (!modal) return;

    const elId     = document.getElementById('k_id_alat');
    const elNama   = document.getElementById('k_nama');
    const elKode   = document.getElementById('k_kode');
    const elRR     = document.getElementById('k_rr');
    const elRB     = document.getElementById('k_rb');
    const elDari   = document.getElementById('k_dari');
    const elAksi   = document.getElementById('k_aksi');
    const elJumlah = document.getElementById('k_jumlah');
    const elMax    = document.getElementById('k_max_info');
    const elHint   = document.getElementById('k_aksi_hint');

    function getStok(kondisi) {
        return kondisi === 'rusak_ringan'
            ? parseInt(modal.dataset.rr, 10) || 0
            : parseInt(modal.dataset.rb, 10) || 0;
    }

    function updateUI() {

        const sumber = elDari.value;
        const max    = getStok(sumber);

        // Batas jumlah
        elJumlah.max = max;
        if (parseInt(elJumlah.value, 10) > max) {
            elJumlah.value = max;
        }
        elMax.textContent = 'Maksimal: ' + max + ' unit dari ' +
                            sumber.replace('_', ' ');

        // Toggle opsi turun_kondisi
        const optTurun = elAksi.querySelector('option[value="turun_kondisi"]');
        if (sumber === 'rusak_berat') {
            optTurun.disabled = true;
            if (elAksi.value === 'turun_kondisi') {
                elAksi.value = 'perbaiki';
            }
        } else {
            optTurun.disabled = false;
        }

        // Hint
        const hints = {
            perbaiki:      'Alat akan dikembalikan ke stok tersedia (bisa dipinjam lagi).',
            buang:         'Alat akan dihapus dari inventaris. Stok total ikut berkurang.',
            turun_kondisi: 'Rusak ringan dipindahkan menjadi rusak berat.'
        };
        elHint.textContent = hints[elAksi.value] || '';
    }

    modal.addEventListener('show.bs.modal', function (event) {

        const btn = event.relatedTarget;
        if (!btn) return;

        modal.dataset.rr = btn.dataset.rr || '0';
        modal.dataset.rb = btn.dataset.rb || '0';

        elId.value       = btn.dataset.id   || '';
        elNama.textContent = btn.dataset.nama || '—';
        elKode.textContent = btn.dataset.kode || '—';
        elRR.textContent = (btn.dataset.rr || 0) + ' unit';
        elRB.textContent = (btn.dataset.rb || 0) + ' unit';

        // Default sumber: pilih yang ada stoknya
        if ((parseInt(btn.dataset.rr, 10) || 0) > 0) {
            elDari.value = 'rusak_ringan';
        } else if ((parseInt(btn.dataset.rb, 10) || 0) > 0) {
            elDari.value = 'rusak_berat';
        }

        elAksi.value   = 'perbaiki';
        elJumlah.value = 1;

        updateUI();
    });

    elDari.addEventListener('change', updateUI);
    elAksi.addEventListener('change', updateUI);
    elJumlah.addEventListener('input', function () {
        const max = getStok(elDari.value);
        if (parseInt(this.value, 10) > max) this.value = max;
    });

});
</script>

<?php require_once '../includes/footer.php'; ?>