# 📦 Aplikasi Peminjaman Alat
Aplikasi web untuk peminjaman alat dengan 3 level user (Admin, Petugas, Peminjam).

## 🚀 Cara Install
1. Copy folder `peminjaman-alat` ke `htdocs` (XAMPP) atau `www` (Laragon).
2. Import `database/db_peminjaman_alat.sql` via phpMyAdmin.
3. Sesuaikan kredensial DB di `config/config.php` jika perlu.
4. Buka `http://localhost/peminjaman-alat/`.

## 🔑 Akun Demo
| Role     | Username  | Password     |
|----------|-----------|--------------|
| Admin    | admin     | admin123     |
| Petugas  | petugas   | petugas123   |
| Peminjam | peminjam  | peminjam123  |

Password otomatis di-hash (bcrypt) saat login pertama.

## ✨ Fitur
- **Admin**: CRUD user, alat, kategori, peminjam, pengembalian, log, laporan.
- **Petugas**: Persetujuan peminjaman, pantau pengembalian, CRUD alat/kategori, laporan.
- **Peminjam**: Lihat alat, ajukan peminjaman, riwayat, kembalikan alat.

## 🗄️ Database
- Trigger: update stok otomatis, log aktivitas, update status peminjaman.
- Function: `fn_hitung_denda`.
- Procedure: `sp_setujui_peminjaman`, `sp_kembalikan_alat`.
- View: `v_peminjaman_detail`.

## 📚 Dokumentasi
Lihat folder `docs/` untuk ERD, dokumentasi program, dan test case.

## 🔧 Update Fitur
### 1. Perbaikan notifikasi pengajuan peminjaman
Pesan sukses sekarang ditampilkan melalui sistem flash message pada `includes/header.php`.
Duplikasi pemanggilan `getFlash()` pada `pages/ajukan.php` dihapus agar pesan tidak langsung "dimakan" sebelum ditampilkan.
Logging aktivitas juga tidak lagi membatalkan peminjaman yang sudah berhasil disimpan.

### 2. Detail alat
Manajemen alat sekarang memiliki tombol **Detail** untuk admin dan peminjam. Detail menampilkan:
- Foto barang
- Kode alat dan nama alat
- Kategori
- Kondisi
- Stok dan stok tersedia
- Merek
- Nomor seri
- Tahun perolehan
- Lokasi
- Spesifikasi perangkat
- Keterangan

Admin juga dapat mengisi/mengubah data tersebut dari form Tambah/Edit Alat.

**Penting:** jalankan `database/update_detail_alat.sql` sekali sebelum menggunakan fitur detail alat.
