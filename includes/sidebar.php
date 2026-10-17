<?php
$baseUrl = '/peminjaman-alat/pages/';
?>

<button type="button" id="sidebarToggle" class="mobile-menu-toggle" aria-label="Buka menu" aria-expanded="false">
    <i class="fas fa-bars"></i>
</button>
<div id="sidebarOverlay" class="sidebar-overlay" aria-hidden="true"></div>

<div class="sidebar" id="sidebar">

    <!-- HEADER -->
    <div class="sidebar-header">
        <img 
            src="<?= BASE_URL ?>assets/img/logo.svg" 
            alt="Logo" 
            width="70" 
            height="70"
        >

        <h5 class="mt-3 mb-0">SMK Peminjaman</h5>
        <small>Alat Praktik</small>
    </div>


    <!-- MENU -->
    <ul class="nav flex-column">

        <!-- ========================= -->
        <!-- DASHBOARD -->
        <!-- ========================= -->
        <li class="nav-item">
            <a 
                href="<?= BASE_URL ?>pages/dashboard.php"
                class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>"
            >
                <i class="fas fa-tachometer-alt me-2"></i>
                Dashboard
            </a>
        </li>


        <!-- ========================= -->
        <!-- MENU ADMIN -->
        <!-- ========================= -->
        <?php if (hasRole('admin')): ?>

            <li class="nav-item">
                <a 
                    href="<?= BASE_URL ?>pages/users.php"
                    class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>"
                >
                    <i class="fas fa-users me-2"></i>
                    Manajemen User
                </a>
            </li>

            <li class="nav-item">
                <a 
                    href="<?= BASE_URL ?>pages/kategori.php"
                    class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'kategori.php' ? 'active' : '' ?>"
                >
                    <i class="fas fa-tags me-2"></i>
                    Kategori
                </a>
            </li>

            <li class="nav-item">
                <a 
                    href="<?= BASE_URL ?>pages/alat.php"
                    class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'alat.php' ? 'active' : '' ?>"
                >
                    <i class="fas fa-tools me-2"></i>
                    Manajemen Alat
                </a>
            </li>

        <?php endif; ?>


        <!-- ========================= -->
        <!-- ADMIN & PETUGAS -->
        <!-- ========================= -->
        <?php if (hasRole(['admin', 'petugas'])): ?>

            <li class="nav-item">
                <a 
                    href="<?= BASE_URL ?>pages/peminjaman.php"
                    class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'peminjaman.php' ? 'active' : '' ?>"
                >
                    <i class="fas fa-hand-holding me-2"></i>
                    Peminjaman
                </a>
            </li>

            <li class="nav-item">
                <a 
                    href="<?= BASE_URL ?>pages/pengembalian.php"
                    class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'pengembalian.php' ? 'active' : '' ?>"
                >
                    <i class="fas fa-undo me-2"></i>
                    Pengembalian
                </a>
            </li>

            <li class="nav-item">
                <a 
                    href="<?= BASE_URL ?>pages/laporan.php"
                    class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'laporan.php' ? 'active' : '' ?>"
                >
                    <i class="fas fa-print me-2"></i>
                    Laporan
                </a>
            </li>

        <?php endif; ?>


        <!-- ========================= -->
        <!-- LOG AKTIVITAS ADMIN -->
        <!-- ========================= -->
        <?php if (hasRole('admin')): ?>

            <li class="nav-item">
                <a 
                    href="<?= BASE_URL ?>pages/log.php"
                    class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'log.php' ? 'active' : '' ?>"
                >
                    <i class="fas fa-history me-2"></i>
                    Log Aktivitas
                </a>
            </li>

        <?php endif; ?>


        <!-- ========================= -->
        <!-- MENU PEMINJAM -->
        <!-- ========================= -->
        <?php if (hasRole('peminjam')): ?>

            <!-- Daftar Alat -->
            <li class="nav-item">
                <a 
                    href="<?= BASE_URL ?>pages/alat.php"
                    class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'alat.php' ? 'active' : '' ?>"
                >
                    <i class="fas fa-list me-2"></i>
                    Daftar Alat
                </a>
            </li>


            <!-- Ajukan Pinjam -->
            <li class="nav-item">
                <a 
                    href="<?= BASE_URL ?>pages/ajukan.php"
                    class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'ajukan.php' ? 'active' : '' ?>"
                >
                    <i class="fas fa-plus-circle me-2"></i>
                    Ajukan Pinjam
                </a>
            </li>


            <!-- Riwayat Pinjam -->
            <li class="nav-item">
                <a 
                    href="<?= BASE_URL ?>pages/riwayat.php"
                    class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'riwayat.php' ? 'active' : '' ?>"
                >
                    <i class="fas fa-history me-2"></i>
                    Riwayat Pinjam
                </a>
            </li>


            <!-- Kembalikan Alat -->
            <li class="nav-item">
                <a 
                    href="<?= BASE_URL ?>pages/pengembalian.php"
                    class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'pengembalian.php' ? 'active' : '' ?>"
                >
                    <i class="fas fa-undo me-2"></i>
                    Kembalikan Alat
                </a>
            </li>


            <!-- Profil -->
            <li class="nav-item">
                <a 
                    href="<?= BASE_URL ?>pages/profil.php"
                    class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'profil.php' ? 'active' : '' ?>"
                >
                    <i class="fas fa-user-circle me-2"></i>
                    Profil Saya
                </a>
            </li>

        <?php endif; ?>

    </ul>


    <!-- ========================= -->
    <!-- LOGOUT -->
    <!-- ========================= -->
    <div class="sidebar-footer">

        <a 
            href="<?= BASE_URL ?>logout.php"
            class="btn btn-sm w-100"
        >
            <i class="fas fa-sign-out-alt me-1"></i>
            Logout
        </a>

    </div>

</div>