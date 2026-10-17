-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 15, 2026 at 01:53 AM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `peminjaman_alat`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_kembalikan_alat` (IN `p_id_peminjaman` INT, IN `p_tanggal_kembali` DATE, IN `p_kondisi_alat` ENUM('baik','rusak ringan','rusak berat'), IN `p_id_petugas` INT, IN `p_keterangan` TEXT)   BEGIN
    DECLARE v_tgl_rencana DATE;
    DECLARE v_denda DECIMAL(10,2);
    DECLARE v_denda_per_hari DECIMAL(10,2) DEFAULT 5000;
    START TRANSACTION;
    SELECT tanggal_kembali_rencana INTO v_tgl_rencana FROM peminjaman WHERE id = p_id_peminjaman;
    SET v_denda = fn_hitung_denda(v_tgl_rencana, p_tanggal_kembali, v_denda_per_hari);
    INSERT INTO pengembalian (id_peminjaman, tanggal_kembali, denda, kondisi_alat, id_petugas, keterangan)
    VALUES (p_id_peminjaman, p_tanggal_kembali, v_denda, p_kondisi_alat, p_id_petugas, p_keterangan);
    UPDATE peminjaman SET status = 'dikembalikan', tanggal_kembali_actual = p_tanggal_kembali, denda = v_denda WHERE id = p_id_peminjaman;
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_login` (IN `p_username` VARCHAR(50))   BEGIN
    SELECT * FROM users WHERE username = p_username AND status = 'aktif';
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_pinjam_alat` (IN `p_kode_pinjam` VARCHAR(50), IN `p_id_user` INT, IN `p_id_alat` INT, IN `p_jumlah` INT, IN `p_tanggal_pinjam` DATE, IN `p_tanggal_kembali_rencana` DATE, IN `p_keterangan` TEXT)   BEGIN
    DECLARE v_stok_tersedia INT;

    START TRANSACTION;

    SELECT stok_tersedia INTO v_stok_tersedia
    FROM alat
    WHERE id = p_id_alat
    FOR UPDATE;

    IF v_stok_tersedia >= p_jumlah THEN
        INSERT INTO peminjaman (
            kode_pinjam,
            id_user,
            id_alat,
            jumlah,
            tanggal_pinjam,
            tanggal_kembali_rencana,
            status,
            keterangan
        )
        VALUES (
            p_kode_pinjam,
            p_id_user,
            p_id_alat,
            p_jumlah,
            p_tanggal_pinjam,
            p_tanggal_kembali_rencana,
            'menunggu',
            p_keterangan
        );

        COMMIT;
    ELSE
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stok tidak mencukupi';
    END IF;
END$$

--
-- Functions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_hitung_denda` (`tgl_rencana` DATE, `tgl_actual` DATE, `denda_per_hari` DECIMAL(10,2)) RETURNS DECIMAL(10,2) DETERMINISTIC BEGIN
    DECLARE hari_terlambat INT;
    DECLARE total_denda DECIMAL(10,2);
    SET hari_terlambat = DATEDIFF(tgl_actual, tgl_rencana);
    IF hari_terlambat > 0 THEN
        SET total_denda = hari_terlambat * denda_per_hari;
    ELSE
        SET total_denda = 0;
    END IF;
    RETURN total_denda;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `alat`
--

CREATE TABLE `alat` (
  `id` int NOT NULL,
  `kode_alat` varchar(20) NOT NULL,
  `nama_alat` varchar(100) NOT NULL,
  `id_kategori` int DEFAULT NULL,
  `stok` int NOT NULL DEFAULT '0',
  `stok_tersedia` int NOT NULL DEFAULT '0',
  `stok_rusak_ringan` int NOT NULL DEFAULT '0',
  `stok_rusak_berat` int NOT NULL DEFAULT '0',
  `kondisi` enum('baik','rusak ringan','rusak berat') DEFAULT 'baik',
  `merk` varchar(100) DEFAULT NULL,
  `nomor_seri` varchar(150) DEFAULT NULL,
  `tahun_perolehan` year DEFAULT NULL,
  `lokasi` varchar(150) DEFAULT NULL,
  `spesifikasi` text,
  `foto_alat` varchar(255) DEFAULT NULL,
  `keterangan` text,
  `gambar` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `alat`
--

INSERT INTO `alat` (`id`, `kode_alat`, `nama_alat`, `id_kategori`, `stok`, `stok_tersedia`, `stok_rusak_ringan`, `stok_rusak_berat`, `kondisi`, `merk`, `nomor_seri`, `tahun_perolehan`, `lokasi`, `spesifikasi`, `foto_alat`, `keterangan`, `gambar`) VALUES
(1, 'ELK001', 'Multimeter', 1, 3, 3, 0, 0, 'baik', '', '', NULL, '', '', 'uploads/alat/alat_4dac87d13b178ee4.jpg', '', NULL),
(2, 'KOM001', 'Laptop ASUS', 2, 15, 12, 1, 0, 'baik', '', '', NULL, '', '', 'uploads/alat/alat_e3f0526e997ecb92.jpg', '', NULL),
(4, 'TV001', 'TV Merah Putih', 1, 1, 1, 0, 0, 'baik', '', '', NULL, '', '', 'uploads/alat/alat_6ff4dc1607f84e38.jpg', '', NULL),
(5, 'PRY001', 'Proyektor', 1, 4, 4, 0, 0, 'baik', '', '', NULL, '', '', 'uploads/alat/alat_5b38e72ece229eff.webp', '', NULL),
(6, 'KYB001', 'Keyboard', 1, 10, 10, 0, 0, 'baik', '', '', NULL, '', '', 'uploads/alat/alat_5b128278c3cb6b07.jpg', '', NULL),
(8, 'LYP005', 'Layar proyektor', 4, 2, 2, 0, 0, 'baik', '', '', NULL, '', '', 'uploads/alat/alat_7695e43cb0dbc21a.webp', '', NULL),
(9, 'KOM002', 'Laptop acer travelmate', 2, 19, 13, 1, 0, 'baik', 'Acer', '', NULL, 'Lab Pemrograman', 'Inter core i7, ram 8/512 gb, warna hitam', 'uploads/alat/alat_ab636336ced0358f.jpg', '', NULL),
(10, 'PCK21100', 'PC Acer i7', 2, 8, 8, 0, 0, 'baik', 'Acer', '', 2025, 'Lab Pemrograman', 'Intel Core i7 H series', 'uploads/alat/alat_a39f71e284213f66.jpg', '', NULL),
(11, 'MNT52308', 'Monitor Acer', 2, 8, 8, 0, 0, 'baik', 'Acer', '', NULL, 'Lab Pemrograman', 'Layar QHD 2K 120hz 100% SRGb', 'uploads/alat/alat_fd27e2725d8caa80.jpg', '', NULL),
(12, 'MRK4030', 'Mikrotik RouterBoard', 3, 9, 9, 0, 0, 'baik', '-', '', 2022, 'Lab Network', '', 'uploads/alat/alat_de77d00c2a15a3eb.jpg', '', NULL),
(13, 'MJP76112', 'Meja', 4, 7, 7, 0, 0, 'baik', '-', '', NULL, '', '', 'uploads/alat/alat_901c76ea77d7b3d5.jpg', '', NULL),
(14, 'KMR875', 'Kamera Canon', 1, 5, 5, 0, 0, 'baik', 'Canon', '0988-0022', 2022, 'Lab DKV', '', 'uploads/alat/alat_4290a119a0d382a0.jpg', '', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `kategori`
--

CREATE TABLE `kategori` (
  `id` int NOT NULL,
  `nama_kategori` varchar(50) NOT NULL,
  `deskripsi` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `kategori`
--

INSERT INTO `kategori` (`id`, `nama_kategori`, `deskripsi`) VALUES
(1, 'Elektronik', 'Alat elektronik'),
(2, 'Komputer/Laptop', 'Perangkat komputer'),
(3, 'Jaringan', 'Alat jaringan'),
(4, 'Inventaris ruangan', 'meja, kursi, dll'),
(5, 'ATK', 'alat tulis');

-- --------------------------------------------------------

--
-- Table structure for table `log_aktivitas`
--

CREATE TABLE `log_aktivitas` (
  `id` int NOT NULL,
  `id_user` int DEFAULT NULL,
  `aktivitas` varchar(255) NOT NULL,
  `waktu` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `log_aktivitas`
--

INSERT INTO `log_aktivitas` (`id`, `id_user`, `aktivitas`, `waktu`, `ip_address`) VALUES
(1, 3, 'Logout dari sistem', '2026-09-17 03:18:56', '::1'),
(2, 1, 'Login ke sistem', '2026-09-17 03:24:26', '::1'),
(3, 1, 'Logout dari sistem', '2026-09-17 03:26:08', '::1'),
(4, 2, 'Login ke sistem', '2026-09-17 03:26:23', '::1'),
(5, 2, 'Logout dari sistem', '2026-09-17 03:26:34', '::1'),
(6, 3, 'Login ke sistem', '2026-09-17 03:26:40', '::1'),
(7, 3, 'Logout dari sistem', '2026-09-17 03:26:57', '::1'),
(8, 1, 'Login ke sistem', '2026-09-17 03:27:12', '::1'),
(9, 1, 'Logout dari sistem', '2026-09-17 03:48:41', '::1'),
(10, 2, 'Login ke sistem', '2026-09-17 03:48:48', '::1'),
(11, 2, 'Logout dari sistem', '2026-09-17 03:48:52', '::1'),
(12, 1, 'Login ke sistem', '2026-09-17 03:49:03', '::1'),
(13, 1, 'Menambah kategori: Inventaris ruangan', '2026-09-17 04:53:21', '::1'),
(14, 1, 'Mengedit kategori ID: 4', '2026-09-17 04:53:45', '::1'),
(15, 1, 'Menambah kategori: ATK', '2026-09-17 04:54:02', '::1'),
(16, 1, 'Menghapus alat ID: 3', '2026-09-17 04:56:44', '::1'),
(17, 1, 'Menambah alat: TV Merah Putih', '2026-09-17 04:57:10', '::1'),
(18, 1, 'Menambah alat: Proyektor', '2026-09-17 04:57:35', '::1'),
(19, 1, 'Menambah alat: Keyboard', '2026-09-17 04:57:54', '::1'),
(20, 1, 'Logout dari sistem', '2026-09-17 05:00:12', '::1'),
(21, 3, 'Login ke sistem', '2026-09-17 05:00:18', '::1'),
(22, 3, 'Mengajukan peminjaman: PJM-20260917-E0B1B', '2026-09-17 05:00:43', '::1'),
(23, 3, 'Logout dari sistem', '2026-09-17 05:00:49', '::1'),
(24, 1, 'Login ke sistem', '2026-09-17 05:00:59', '::1'),
(25, 1, 'Logout dari sistem', '2026-09-17 05:01:10', '::1'),
(26, 2, 'Login ke sistem', '2026-09-17 05:01:17', '::1'),
(27, 2, 'Menyetujui peminjaman ID: 1', '2026-09-17 05:01:23', '::1'),
(28, 2, 'Menandai dipinjam ID: 1', '2026-09-17 05:01:25', '::1'),
(29, 2, 'Logout dari sistem', '2026-09-17 05:01:42', '::1'),
(30, 3, 'Login ke sistem', '2026-09-17 05:01:51', '::1'),
(31, 3, 'Logout dari sistem', '2026-09-17 05:02:00', '::1'),
(32, 2, 'Login ke sistem', '2026-09-17 05:02:06', '::1'),
(33, 2, 'Mengembalikan alat ID Peminjaman: 1', '2026-09-17 05:03:32', '::1'),
(34, 2, 'Logout dari sistem', '2026-09-17 05:03:50', '::1'),
(35, 4, 'Registrasi akun baru sebagai peminjam', '2026-09-17 05:11:32', '::1'),
(36, 4, 'Login ke sistem', '2026-09-17 05:11:37', '::1'),
(37, 4, 'Logout dari sistem', '2026-09-17 05:11:53', '::1'),
(38, 5, 'Registrasi akun baru sebagai peminjam', '2026-09-17 05:12:18', '::1'),
(39, 5, 'Login ke sistem', '2026-09-17 05:12:28', '::1'),
(40, 5, 'Logout dari sistem', '2026-09-17 05:20:48', '::1'),
(41, 4, 'Login ke sistem', '2026-09-17 05:20:58', '::1'),
(42, 4, 'Logout dari sistem', '2026-09-17 05:24:48', '::1'),
(43, 1, 'Login ke sistem', '2026-09-17 05:24:54', '::1'),
(44, 1, 'Logout dari sistem', '2026-09-17 05:25:33', '::1'),
(45, 6, 'Registrasi akun baru sebagai peminjam', '2026-09-17 05:25:51', '::1'),
(46, 6, 'Login ke sistem', '2026-09-17 05:25:59', '::1'),
(47, 6, 'Mengajukan peminjaman: PJM-20260917-9D457', '2026-09-17 05:26:13', '::1'),
(48, 6, 'Logout dari sistem', '2026-09-17 05:26:16', '::1'),
(49, 4, 'Login ke sistem', '2026-09-17 05:26:22', '::1'),
(50, 4, 'Mengajukan peminjaman: PJM-20260917-A9C64', '2026-09-17 05:26:34', '::1'),
(51, 4, 'Logout dari sistem', '2026-09-17 05:26:40', '::1'),
(52, 2, 'Login ke sistem', '2026-09-17 05:26:46', '::1'),
(53, 2, 'Menyetujui peminjaman ID: 3', '2026-09-17 05:26:52', '::1'),
(54, 2, 'Menandai dipinjam ID: 3', '2026-09-17 05:26:59', '::1'),
(55, 2, 'Logout dari sistem', '2026-09-17 05:27:27', '::1'),
(56, 2, 'Login ke sistem', '2026-09-17 05:27:52', '::1'),
(57, 2, 'Logout dari sistem', '2026-09-17 05:27:59', '::1'),
(58, 1, 'Login ke sistem', '2026-09-21 23:32:36', '::1'),
(59, 1, 'Logout dari sistem', '2026-09-21 23:35:10', '::1'),
(60, 2, 'Login ke sistem', '2026-09-21 23:52:34', '::1'),
(61, 2, 'Logout dari sistem', '2026-09-21 23:57:48', '::1'),
(62, 1, 'Login ke sistem', '2026-09-22 01:37:02', '::1'),
(63, 1, 'Login ke sistem', '2026-09-24 01:36:10', '::1'),
(64, 1, 'Menyetujui peminjaman ID: 2', '2026-09-24 01:37:38', '::1'),
(65, 1, 'Menandai dipinjam ID: 2', '2026-09-24 01:37:42', '::1'),
(66, 2, 'Login ke sistem', '2026-09-24 01:38:37', '::1'),
(67, 3, 'Login ke sistem', '2026-09-24 01:39:33', '::1'),
(68, 3, 'Mengajukan peminjaman: PJM-20260924-905AD', '2026-09-24 01:39:59', '::1'),
(69, 2, 'Menyetujui peminjaman ID: 4', '2026-09-24 01:40:10', '::1'),
(70, 2, 'Menandai dipinjam ID: 4', '2026-09-24 01:40:13', '::1'),
(71, 2, 'Mengembalikan alat ID Peminjaman: 4', '2026-09-24 01:41:03', '::1'),
(72, 3, 'Mengajukan peminjaman: PJM-20260924-94ED4', '2026-09-24 01:42:22', '::1'),
(73, 2, 'Menyetujui peminjaman ID: 5', '2026-09-24 01:42:36', '::1'),
(74, 2, 'Menandai dipinjam ID: 5', '2026-09-24 01:42:37', '::1'),
(75, 2, 'Mengembalikan alat ID Peminjaman: 5', '2026-09-24 01:43:06', '::1'),
(76, 3, 'Mengajukan peminjaman: PJM-20260924-6D9AA', '2026-09-24 01:46:37', '::1'),
(77, 3, 'Mengajukan peminjaman: PJM-20260924-94C14', '2026-09-24 01:57:38', '::1'),
(78, 2, 'Menyetujui peminjaman ID: 6', '2026-09-24 01:57:46', '::1'),
(79, 2, 'Menyetujui peminjaman ID: 7', '2026-09-24 01:57:48', '::1'),
(80, 2, 'Menandai dipinjam ID: 7', '2026-09-24 01:57:49', '::1'),
(81, 2, 'Menandai dipinjam ID: 6', '2026-09-24 01:57:50', '::1'),
(82, 2, 'Mengembalikan alat ID Peminjaman: 7', '2026-09-24 01:57:55', '::1'),
(83, 2, 'Mengembalikan alat ID Peminjaman: 6', '2026-09-24 01:57:59', '::1'),
(84, 2, 'Mengembalikan alat ID Peminjaman: 3', '2026-09-24 01:58:02', '::1'),
(85, 2, 'Mengembalikan alat ID Peminjaman: 2', '2026-09-24 01:58:04', '::1'),
(86, 1, 'Mengedit alat ID: 6', '2026-09-24 02:14:17', '::1'),
(87, 1, 'Mengedit alat ID: 6', '2026-09-24 02:14:22', '::1'),
(88, 1, 'Logout dari sistem', '2026-09-24 02:15:39', '::1'),
(89, 1, 'Login ke sistem', '2026-09-24 02:15:47', '::1'),
(90, 1, 'Mengedit alat ID: 6', '2026-09-24 02:17:18', '::1'),
(91, 1, 'Mengedit alat ID: 5', '2026-09-24 02:17:23', '::1'),
(92, 1, 'Mengedit alat ID: 2', '2026-09-24 02:17:32', '::1'),
(93, 1, 'Mengedit alat ID: 1', '2026-09-24 02:17:42', '::1'),
(94, 1, 'Mengedit alat ID: 1', '2026-09-24 02:18:00', '::1'),
(95, 1, 'Menambah alat: MJ002', '2026-09-24 02:18:39', '::1'),
(96, 1, 'Menambah alat: Layar proyektor', '2026-09-24 02:19:19', '::1'),
(97, 3, 'Mengajukan peminjaman: PJM-20260924-AEA9E', '2026-09-24 02:28:52', '::1'),
(98, 2, 'Logout dari sistem', '2026-09-24 02:50:59', '::1'),
(99, 2, 'Login ke sistem', '2026-09-24 02:51:03', '::1'),
(100, 3, 'Mengajukan peminjaman: PJM-20260924-A348B', '2026-09-24 02:51:37', '::1'),
(101, 2, 'Menolak peminjaman ID: 9', '2026-09-24 03:07:57', '::1'),
(102, 2, 'Menyetujui peminjaman ID: 8', '2026-09-24 03:07:59', '::1'),
(103, 1, 'Mengedit alat ID: 2', '2026-09-24 03:25:49', '::1'),
(104, 1, 'Mengedit kategori ID: 2', '2026-09-24 03:27:22', '::1'),
(105, 1, 'Menambah alat: Laptop acer travelmate', '2026-09-24 03:32:33', '::1'),
(106, 1, 'Mengedit alat ID: 6', '2026-09-24 03:35:42', '::1'),
(107, 1, 'Mengedit alat ID: 8', '2026-09-24 03:35:52', '::1'),
(108, 1, 'Menghapus alat ID: 7', '2026-09-24 03:36:02', '::1'),
(109, 1, 'Mengedit alat ID: 5', '2026-09-24 03:36:10', '::1'),
(110, 1, 'Mengedit alat ID: 4', '2026-09-24 03:38:46', '::1'),
(111, 1, 'Mengedit alat ID: 1', '2026-09-24 03:39:07', '::1'),
(112, 1, 'Login ke sistem', '2026-10-06 01:37:14', '::1'),
(113, 2, 'Login ke sistem', '2026-10-06 01:38:13', '::1'),
(114, 3, 'Login ke sistem', '2026-10-06 01:38:33', '::1'),
(115, 3, 'Logout dari sistem', '2026-10-06 02:02:59', '::1'),
(116, 3, 'Login ke sistem', '2026-10-06 02:03:05', '::1'),
(117, 3, 'Login ke sistem', '2026-10-07 13:46:38', '::1'),
(118, 3, 'Mengajukan peminjaman: PJM-20261007-DF5C6', '2026-10-07 13:56:58', '::1'),
(119, 1, 'Login ke sistem', '2026-10-07 13:58:18', '::1'),
(120, 1, 'Menyetujui peminjaman ID: 10', '2026-10-07 13:58:34', '::1'),
(121, 2, 'Login ke sistem', '2026-10-07 13:59:12', '::1'),
(122, 3, 'Mengajukan peminjaman: PJM-20261007-288BB', '2026-10-07 14:00:57', '::1'),
(123, 2, 'Menyetujui peminjaman ID: 11', '2026-10-07 14:01:20', '::1'),
(124, 3, 'Mengajukan peminjaman: PJM-20261007-D9FD9', '2026-10-07 14:06:00', '::1'),
(125, 2, 'Menyetujui peminjaman ID: 12', '2026-10-07 14:06:11', '::1'),
(126, 1, 'Mengedit alat ID: 4', '2026-10-07 14:08:29', '::1'),
(127, 1, 'Mengedit alat ID: 4', '2026-10-07 14:08:36', '::1'),
(128, 3, 'Login ke sistem', '2026-10-07 14:20:25', '::1'),
(129, 3, 'Mengajukan peminjaman: PJM-20261007-88CCE', '2026-10-07 14:20:46', '::1'),
(130, 2, 'Menyetujui peminjaman ID: 13', '2026-10-07 14:21:10', '::1'),
(131, 2, 'Menyerahkan alat ID: 13', '2026-10-07 14:21:16', '::1'),
(132, 2, 'Menyerahkan alat ID: 8', '2026-10-07 14:21:53', '::1'),
(135, 3, 'Mengajukan peminjaman: PJM-20261007-089EA', '2026-10-07 14:23:55', '::1'),
(136, 2, 'Menyetujui peminjaman ID: 14', '2026-10-07 14:24:06', '::1'),
(137, 2, 'Menyerahkan alat ID: 14', '2026-10-07 14:25:55', '::1'),
(138, 1, 'Mengembalikan alat ID Peminjaman: 14', '2026-10-07 14:26:32', '::1'),
(139, 3, 'Mengajukan peminjaman: PJM-20261007-B537D', '2026-10-07 14:37:52', '::1'),
(140, 2, 'Menolak peminjaman ID: 15', '2026-10-07 14:38:27', '::1'),
(141, 3, 'Mengajukan peminjaman: PJM-20261007-421DC', '2026-10-07 14:55:30', '::1'),
(142, 1, 'Logout dari sistem', '2026-10-07 15:22:34', '::1'),
(143, 1, 'Login ke sistem', '2026-10-07 15:22:44', '::1'),
(144, 1, 'Menolak peminjaman ID: 16. Alasan: Sedang perbaikan', '2026-10-07 15:32:01', '::1'),
(145, 1, 'Mengedit alat ID: 9', '2026-10-07 15:33:26', '::1'),
(146, 1, 'Mengedit alat ID: 2', '2026-10-07 15:33:34', '::1'),
(147, 3, 'Mengajukan peminjaman: PJM-20261007-30EBB', '2026-10-07 15:50:17', '::1'),
(148, 2, 'Menyetujui peminjaman ID: 17', '2026-10-07 15:51:13', '::1'),
(149, 2, 'Menyerahkan alat ID: 17', '2026-10-07 15:51:26', '::1'),
(150, 1, 'Login ke sistem', '2026-10-08 02:08:16', '::1'),
(151, 1, 'Login ke sistem', '2026-10-08 05:22:21', '::1'),
(152, 1, 'Menambah alat: PC Acer i7', '2026-10-08 06:03:52', '::1'),
(153, 1, 'Menambah alat: Monitor Acer', '2026-10-08 06:05:03', '::1'),
(154, 1, 'Menambah alat: Mikrotik RouterBoard', '2026-10-08 06:06:38', '::1'),
(155, 1, 'Menambah alat: Meja', '2026-10-08 06:09:13', '::1'),
(156, 1, 'Login ke sistem', '2026-10-12 23:37:53', '::1'),
(157, 3, 'Login ke sistem', '2026-10-12 23:42:29', '::1'),
(158, 2, 'Login ke sistem', '2026-10-12 23:50:13', '::1'),
(159, 1, 'Login ke sistem', '2026-10-13 00:26:43', '::1'),
(160, 3, 'Logout dari sistem', '2026-10-13 00:37:48', '::1'),
(161, 7, 'Registrasi akun baru sebagai peminjam', '2026-10-13 00:39:04', '::1'),
(162, 7, 'Login ke sistem', '2026-10-13 00:39:09', '::1'),
(163, 7, 'Mengajukan peminjaman: PJM-20261013-78325', '2026-10-13 00:43:02', '::1'),
(164, 7, 'Logout dari sistem', '2026-10-13 00:43:31', '::1'),
(165, 2, 'Menyetujui peminjaman ID: 18', '2026-10-13 00:44:10', '::1'),
(166, 2, 'Menyerahkan alat ID: 18', '2026-10-13 00:44:19', '::1'),
(167, 2, 'Logout dari sistem', '2026-10-13 00:44:43', '::1'),
(168, 7, 'Login ke sistem', '2026-10-13 00:44:55', '::1'),
(169, 7, 'Logout dari sistem', '2026-10-13 00:45:18', '::1'),
(170, 2, 'Login ke sistem', '2026-10-13 00:45:32', '::1'),
(171, 2, 'Mengembalikan alat ID Peminjaman: 18', '2026-10-13 00:45:56', '::1'),
(172, 2, 'Logout dari sistem', '2026-10-13 00:46:17', '::1'),
(173, 1, 'Login ke sistem', '2026-10-13 00:46:21', '::1'),
(174, 1, 'Logout dari sistem', '2026-10-13 00:47:30', '::1'),
(175, 7, 'Login ke sistem', '2026-10-13 00:47:37', '::1'),
(176, 7, 'Logout dari sistem', '2026-10-13 00:47:55', '::1'),
(177, 3, 'Login ke sistem', '2026-10-13 00:48:57', '::1'),
(178, 1, 'Logout dari sistem', '2026-10-13 00:57:03', '::1'),
(179, 2, 'Login ke sistem', '2026-10-13 00:57:09', '::1'),
(180, 3, 'Login ke sistem', '2026-10-13 01:00:40', '::1'),
(181, 2, 'Logout dari sistem', '2026-10-13 01:07:51', '::1'),
(182, 1, 'Login ke sistem', '2026-10-13 01:07:55', '::1'),
(183, 1, 'Logout dari sistem', '2026-10-13 01:53:11', '::1'),
(184, 2, 'Login ke sistem', '2026-10-13 01:53:16', '::1'),
(185, 2, 'Mengembalikan alat ID Peminjaman: 17 | Kondisi: rusak ringan', '2026-10-13 01:53:44', '::1'),
(186, 2, 'Logout dari sistem', '2026-10-13 01:53:50', '::1'),
(187, 1, 'Login ke sistem', '2026-10-13 01:53:54', '::1'),
(188, 3, 'Login ke sistem', '2026-10-13 02:00:16', '::1'),
(189, 3, 'Mengajukan peminjaman: PJM-20261013-43152', '2026-10-13 02:04:12', '::1'),
(190, 1, 'Menyetujui peminjaman ID: 19', '2026-10-13 02:04:29', '::1'),
(191, 1, 'Menyerahkan alat ID: 19', '2026-10-13 02:04:35', '::1'),
(192, 1, 'Mengembalikan alat ID Peminjaman: 19 | Kondisi: rusak berat', '2026-10-13 02:04:57', '::1'),
(193, 1, 'Aksi stok rusak: perbaiki pada alat ID 10, jumlah 2', '2026-10-13 02:05:12', '::1'),
(194, 1, 'Aksi stok rusak: buang pada alat ID 9, jumlah 1', '2026-10-13 02:05:23', '::1'),
(195, 3, 'Logout dari sistem', '2026-10-13 02:47:31', '::1'),
(196, 2, 'Login ke sistem', '2026-10-13 02:47:39', '::1'),
(197, 3, 'Mengajukan peminjaman: PJM-20261013-B5C38', '2026-10-13 02:50:30', '::1'),
(198, 1, 'Menolak peminjaman ID: 20. Alasan: barang lagi dipakai', '2026-10-13 02:50:57', '::1'),
(199, 3, 'Mengajukan peminjaman: PJM-20261013-6C102', '2026-10-13 02:59:46', '::1'),
(200, 1, 'Menyetujui peminjaman ID: 21', '2026-10-13 03:05:56', '::1'),
(201, 1, 'Mengembalikan alat ID Peminjaman: 21 | Kondisi: rusak ringan', '2026-10-13 03:08:34', '::1'),
(202, 1, 'Login ke sistem', '2026-10-15 01:39:56', '::1'),
(203, 1, 'Menambah alat: Kamera Canon', '2026-10-15 01:46:10', '::1'),
(204, 1, 'Mengedit alat ID: 14', '2026-10-15 01:51:47', '::1');

-- --------------------------------------------------------

--
-- Table structure for table `peminjaman`
--

CREATE TABLE `peminjaman` (
  `id` int NOT NULL,
  `kode_pinjam` varchar(20) NOT NULL,
  `id_user` int NOT NULL,
  `id_alat` int NOT NULL,
  `jumlah` int NOT NULL,
  `tanggal_pinjam` date NOT NULL,
  `tanggal_kembali_rencana` date NOT NULL,
  `foto_peminjaman` varchar(255) DEFAULT NULL,
  `tanggal_kembali_actual` date DEFAULT NULL,
  `status` enum('menunggu','disetujui','dipinjam','dikembalikan','ditolak') DEFAULT 'menunggu',
  `id_petugas` int DEFAULT NULL,
  `alasan_ditolak` text,
  `denda` decimal(10,2) DEFAULT '0.00',
  `keterangan` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `peminjaman`
--

INSERT INTO `peminjaman` (`id`, `kode_pinjam`, `id_user`, `id_alat`, `jumlah`, `tanggal_pinjam`, `tanggal_kembali_rencana`, `foto_peminjaman`, `tanggal_kembali_actual`, `status`, `id_petugas`, `alasan_ditolak`, `denda`, `keterangan`, `created_at`) VALUES
(1, 'PJM-20260917-E0B1B', 3, 2, 1, '2026-09-17', '2026-09-19', NULL, '2026-09-17', 'dikembalikan', 2, NULL, '0.00', NULL, '2026-09-17 05:00:43'),
(2, 'PJM-20260917-9D457', 6, 5, 1, '2026-09-16', '2026-09-18', NULL, '2026-09-24', 'dikembalikan', 1, NULL, '30000.00', NULL, '2026-09-17 05:26:13'),
(3, 'PJM-20260917-A9C64', 4, 6, 2, '2026-09-17', '2026-09-19', NULL, '2026-09-24', 'dikembalikan', 2, NULL, '25000.00', NULL, '2026-09-17 05:26:34'),
(4, 'PJM-20260924-905AD', 3, 6, 2, '2026-09-24', '2026-09-26', NULL, '2026-09-24', 'dikembalikan', 2, NULL, '0.00', NULL, '2026-09-24 01:39:59'),
(5, 'PJM-20260924-94ED4', 3, 2, 1, '2026-09-22', '2026-09-23', NULL, '2026-09-24', 'dikembalikan', 2, NULL, '5000.00', NULL, '2026-09-24 01:42:22'),
(6, 'PJM-20260924-6D9AA', 3, 5, 1, '2026-09-23', '2026-09-24', NULL, '2026-09-24', 'dikembalikan', 2, NULL, '0.00', NULL, '2026-09-24 01:46:37'),
(7, 'PJM-20260924-94C14', 3, 2, 5, '2026-09-20', '2026-09-21', NULL, '2026-09-24', 'dikembalikan', 2, NULL, '15000.00', NULL, '2026-09-24 01:57:38'),
(8, 'PJM-20260924-AEA9E', 3, 2, 2, '2026-09-24', '2026-09-25', NULL, '2026-10-07', 'dikembalikan', 2, NULL, '60000.00', NULL, '2026-09-24 02:28:52'),
(13, 'PJM-20261007-88CCE', 3, 2, 2, '2026-10-07', '2026-10-09', 'uploads/peminjaman/PJM-20261007-88CCE_38b4bb4458.jpg', '2026-10-07', 'dikembalikan', 2, NULL, '0.00', NULL, '2026-10-07 14:20:46'),
(14, 'PJM-20261007-089EA', 3, 6, 1, '2026-10-07', '2026-10-08', 'uploads/peminjaman/PJM-20261007-089EA_188cdd2ea9.jpg', '2026-10-07', 'dikembalikan', 2, NULL, '0.00', NULL, '2026-10-07 14:23:55'),
(15, 'PJM-20261007-B537D', 3, 9, 1, '2026-10-07', '2026-10-08', 'uploads/peminjaman/PJM-20261007-B537D_de8dd8b9d7.jpg', NULL, 'ditolak', 2, NULL, '0.00', NULL, '2026-10-07 14:37:52'),
(16, 'PJM-20261007-421DC', 3, 5, 1, '2026-10-07', '2026-10-08', 'uploads/peminjaman/PJM-20261007-421DC_84ee5b342f.jpg', NULL, 'ditolak', 1, 'Sedang perbaikan', '0.00', NULL, '2026-10-07 14:55:30'),
(17, 'PJM-20261007-30EBB', 3, 9, 2, '2026-10-07', '2026-10-09', 'uploads/peminjaman/PJM-20261007-30EBB_73147f00f8.jpg', '2026-10-13', 'dikembalikan', 2, NULL, '20000.00', NULL, '2026-10-07 15:50:17'),
(18, 'PJM-20261013-78325', 7, 11, 5, '2026-10-13', '2026-10-14', 'uploads/peminjaman/PJM-20261013-78325_bd9dac1707.jpg', '2026-10-20', 'dikembalikan', 2, NULL, '30000.00', NULL, '2026-10-13 00:43:02'),
(19, 'PJM-20261013-43152', 3, 10, 2, '2026-10-13', '2026-10-14', 'uploads/peminjaman/PJM-20261013-43152_09b154312f.jpg', '2026-10-13', 'dikembalikan', 1, NULL, '0.00', NULL, '2026-10-13 02:04:12'),
(20, 'PJM-20261013-B5C38', 3, 9, 1, '2026-10-13', '2026-10-14', 'uploads/peminjaman/PJM-20261013-B5C38_43e965ec04.jpg', NULL, 'ditolak', 1, 'barang lagi dipakai', '0.00', NULL, '2026-10-13 02:50:30'),
(21, 'PJM-20261013-6C102', 3, 2, 1, '2026-10-13', '2026-10-15', 'uploads/peminjaman/PJM-20261013-6C102_ea7a27eecf.jpg', '2026-10-13', 'dikembalikan', 1, NULL, '0.00', 'Mau di pake praktik', '2026-10-13 02:59:46');

--
-- Triggers `peminjaman`
--
DELIMITER $$
CREATE TRIGGER `trg_peminjaman_update_stok` AFTER UPDATE ON `peminjaman` FOR EACH ROW BEGIN
    IF NEW.status = 'dipinjam' AND OLD.status != 'dipinjam' THEN
        UPDATE alat SET stok_tersedia = stok_tersedia - NEW.jumlah WHERE id = NEW.id_alat;
    END IF;
    IF NEW.status = 'dikembalikan' AND OLD.status != 'dikembalikan' THEN
        UPDATE alat SET stok_tersedia = stok_tersedia + NEW.jumlah WHERE id = NEW.id_alat;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `pengembalian`
--

CREATE TABLE `pengembalian` (
  `id` int NOT NULL,
  `id_peminjaman` int NOT NULL,
  `tanggal_kembali` date NOT NULL,
  `denda` decimal(10,2) DEFAULT '0.00',
  `kondisi_alat` enum('baik','rusak ringan','rusak berat') DEFAULT 'baik',
  `id_petugas` int DEFAULT NULL,
  `keterangan` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `pengembalian`
--

INSERT INTO `pengembalian` (`id`, `id_peminjaman`, `tanggal_kembali`, `denda`, `kondisi_alat`, `id_petugas`, `keterangan`, `created_at`) VALUES
(1, 1, '2026-09-17', '0.00', 'baik', 2, '', '2026-09-17 05:03:32'),
(2, 4, '2026-09-24', '0.00', 'rusak berat', 2, 'qwerty', '2026-09-24 01:41:03'),
(3, 5, '2026-09-24', '5000.00', 'baik', 2, '', '2026-09-24 01:43:06'),
(4, 7, '2026-09-24', '15000.00', 'baik', 2, '', '2026-09-24 01:57:55'),
(5, 6, '2026-09-24', '0.00', 'rusak ringan', 2, '', '2026-09-24 01:57:59'),
(6, 3, '2026-09-24', '25000.00', 'rusak ringan', 2, '', '2026-09-24 01:58:02'),
(7, 2, '2026-09-24', '30000.00', 'baik', 2, '', '2026-09-24 01:58:04'),
(8, 8, '2026-10-07', '60000.00', 'baik', 2, '', '2026-10-07 14:22:09'),
(9, 13, '2026-10-07', '0.00', 'baik', 2, '', '2026-10-07 14:22:16'),
(10, 14, '2026-10-07', '0.00', 'rusak ringan', 1, 'mohon untuk dijaga kedepannya', '2026-10-07 14:26:32'),
(11, 18, '2026-10-20', '30000.00', 'rusak berat', 2, '', '2026-10-13 00:45:56'),
(12, 17, '2026-10-13', '20000.00', 'rusak ringan', 2, 'Oke', '2026-10-13 01:53:44'),
(13, 19, '2026-10-13', '0.00', 'rusak berat', 1, '', '2026-10-13 02:04:57'),
(14, 21, '2026-10-13', '0.00', 'rusak ringan', 1, '', '2026-10-13 03:08:34');

-- --------------------------------------------------------

--
-- Table structure for table `tindakan_rusak`
--

CREATE TABLE `tindakan_rusak` (
  `id` int NOT NULL,
  `id_alat` int NOT NULL,
  `aksi` varchar(30) NOT NULL,
  `dari_kondisi` varchar(30) NOT NULL,
  `jumlah` int NOT NULL,
  `keterangan` text,
  `id_admin` int DEFAULT NULL,
  `tanggal` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `tindakan_rusak`
--

INSERT INTO `tindakan_rusak` (`id`, `id_alat`, `aksi`, `dari_kondisi`, `jumlah`, `keterangan`, `id_admin`, `tanggal`) VALUES
(1, 10, 'perbaiki', 'rusak_berat', 2, '', 1, '2026-10-13 10:05:12'),
(2, 9, 'buang', 'rusak_ringan', 1, '', 1, '2026-10-13 10:05:23');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `role` enum('admin','petugas','peminjam') NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `no_telp` varchar(15) DEFAULT NULL,
  `alamat` text,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `nama_lengkap`, `role`, `email`, `no_telp`, `alamat`, `status`, `created_at`) VALUES
(1, 'admin', '$2y$10$Fs.tHh65D/A4HLtELkKMP.pifqAjibKG2c3d2AAFCyMHRDEdFIYaK', 'Administrator', 'admin', NULL, NULL, NULL, 'aktif', '2026-09-17 03:05:14'),
(2, 'petugas', '$2y$10$Fs.tHh65D/A4HLtELkKMP.pifqAjibKG2c3d2AAFCyMHRDEdFIYaK', 'Petugas Lab', 'petugas', NULL, NULL, NULL, 'aktif', '2026-09-17 03:05:14'),
(3, 'peminjam', '$2y$10$Fs.tHh65D/A4HLtELkKMP.pifqAjibKG2c3d2AAFCyMHRDEdFIYaK', 'Siswa Peminjam', 'peminjam', NULL, NULL, NULL, 'aktif', '2026-09-17 03:05:14'),
(4, 'ekky', '$2y$10$rjEd1pUrxn7gvFcY3z3y1O.nZUBpNbRZ3R1muLukW8rhGaTcVJTGq', 'Rezky R', 'peminjam', 'rezkyrachmat27@gmail.com', '087863427273', 'PSR', 'aktif', '2026-09-17 05:11:32'),
(5, 'budi', '$2y$10$kFzVUSl2eaDYwfgt4xYTHOaITLU5cV9Mos2JXoJdp4Bwo8RYBhfGG', 'Budi Baik', 'peminjam', '', '', '', 'aktif', '2026-09-17 05:12:18'),
(6, 'repan', '$2y$10$3aAPikcfjIpzT0uZ/rJTROQZyQFz9Vsms9ezBhQxrCPkHlqjUDRfa', 'Muh Revan', 'peminjam', '', '', '', 'aktif', '2026-09-17 05:25:51'),
(7, 'dayat', '$2y$10$wu5TQgcikfpg9R7nnCJEhOlpecCrrFXDGiEdpjJ/D7nMy4WnM/sjy', 'dayat', 'peminjam', 'day@g.com', '22321312', 'qweqweq', 'aktif', '2026-10-13 00:39:04');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `alat`
--
ALTER TABLE `alat`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_alat` (`kode_alat`),
  ADD KEY `id_kategori` (`id_kategori`);

--
-- Indexes for table `kategori`
--
ALTER TABLE `kategori`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_user` (`id_user`);

--
-- Indexes for table `peminjaman`
--
ALTER TABLE `peminjaman`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_pinjam` (`kode_pinjam`),
  ADD KEY `id_user` (`id_user`),
  ADD KEY `id_alat` (`id_alat`),
  ADD KEY `id_petugas` (`id_petugas`);

--
-- Indexes for table `pengembalian`
--
ALTER TABLE `pengembalian`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_peminjaman` (`id_peminjaman`),
  ADD KEY `id_petugas` (`id_petugas`);

--
-- Indexes for table `tindakan_rusak`
--
ALTER TABLE `tindakan_rusak`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_id_alat` (`id_alat`),
  ADD KEY `idx_tanggal` (`tanggal`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `alat`
--
ALTER TABLE `alat`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `kategori`
--
ALTER TABLE `kategori`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=205;

--
-- AUTO_INCREMENT for table `peminjaman`
--
ALTER TABLE `peminjaman`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `pengembalian`
--
ALTER TABLE `pengembalian`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `tindakan_rusak`
--
ALTER TABLE `tindakan_rusak`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `alat`
--
ALTER TABLE `alat`
  ADD CONSTRAINT `alat_ibfk_1` FOREIGN KEY (`id_kategori`) REFERENCES `kategori` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  ADD CONSTRAINT `log_aktivitas_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `peminjaman`
--
ALTER TABLE `peminjaman`
  ADD CONSTRAINT `peminjaman_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `peminjaman_ibfk_2` FOREIGN KEY (`id_alat`) REFERENCES `alat` (`id`),
  ADD CONSTRAINT `peminjaman_ibfk_3` FOREIGN KEY (`id_petugas`) REFERENCES `users` (`id`);

--
-- Constraints for table `pengembalian`
--
ALTER TABLE `pengembalian`
  ADD CONSTRAINT `pengembalian_ibfk_1` FOREIGN KEY (`id_peminjaman`) REFERENCES `peminjaman` (`id`),
  ADD CONSTRAINT `pengembalian_ibfk_2` FOREIGN KEY (`id_petugas`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
