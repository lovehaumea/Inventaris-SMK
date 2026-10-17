-- ============================================================
-- MIGRASI FITUR DETAIL ALAT
-- Jalankan sekali di database peminjaman_alat
-- ============================================================

ALTER TABLE alat
    ADD COLUMN merk VARCHAR(100) NULL AFTER kondisi,
    ADD COLUMN nomor_seri VARCHAR(150) NULL AFTER merk,
    ADD COLUMN tahun_perolehan YEAR NULL AFTER nomor_seri,
    ADD COLUMN lokasi VARCHAR(150) NULL AFTER tahun_perolehan,
    ADD COLUMN spesifikasi TEXT NULL AFTER lokasi,
    ADD COLUMN foto_alat VARCHAR(255) NULL AFTER spesifikasi;
