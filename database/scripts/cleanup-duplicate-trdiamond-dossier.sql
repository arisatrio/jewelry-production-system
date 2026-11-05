-- =============================================================================
-- Batu dossier (trdiamond) — deteksi & hapus duplikat
-- Database: koneksi "third" di aplikasi Laravel
--
-- Aturan: diamond_type = 'Dossier', is_deleted = 0
-- Duplikat: nomor certificate sama (tidak kosong)
-- Disarankan soft delete (is_deleted = 1), bukan DELETE fisik.
--
-- Cara pakai: jalankan diagnostik → preview → START TRANSACTION → UPDATE → verifikasi → COMMIT
-- =============================================================================

-- -----------------------------------------------------------------------------
-- A. DIAGNOSTIK — ringkasan sertifikat dobel
-- -----------------------------------------------------------------------------
SELECT
    certificate,
    COUNT(*) AS stone_count
FROM trdiamond
WHERE is_deleted = 0
  AND diamond_type = 'Dossier'
  AND certificate IS NOT NULL
  AND TRIM(certificate) <> ''
GROUP BY certificate
HAVING COUNT(*) > 1
ORDER BY stone_count DESC, certificate;

-- -----------------------------------------------------------------------------
-- A2. Detail semua baris duplikat (sertifikat sama)
-- -----------------------------------------------------------------------------
SELECT
    d.row_id,
    d.doc_no,
    d.certificate,
    d.entry_date,
    d.out_date,
    d.diamondmounting_id,
    d.is_used,
    d.created_by
FROM trdiamond AS d
INNER JOIN (
    SELECT certificate
    FROM trdiamond
    WHERE is_deleted = 0
      AND diamond_type = 'Dossier'
      AND certificate IS NOT NULL
      AND TRIM(certificate) <> ''
    GROUP BY certificate
    HAVING COUNT(*) > 1
) AS dup ON dup.certificate = d.certificate
WHERE d.is_deleted = 0
  AND d.diamond_type = 'Dossier'
ORDER BY d.certificate, d.entry_date DESC, d.row_id DESC;

-- =============================================================================
-- B. PEMBERSIHAN SEDERHANA (disarankan)
-- Per sertifikat: sisakan row_id TERKECIL; soft-delete duplikat yang:
--   out_date NULL, diamondmounting_id 0/NULL
-- =============================================================================

-- B1. Preview
SELECT d.row_id, d.doc_no, d.certificate, d.out_date, d.diamondmounting_id
FROM trdiamond AS d
INNER JOIN (
    SELECT certificate
    FROM trdiamond
    WHERE is_deleted = 0
      AND diamond_type = 'Dossier'
      AND certificate IS NOT NULL
      AND TRIM(certificate) <> ''
    GROUP BY certificate
    HAVING COUNT(*) > 1
) AS dup ON dup.certificate = d.certificate
WHERE d.is_deleted = 0
  AND d.diamond_type = 'Dossier'
  AND d.out_date IS NULL
  AND COALESCE(d.diamondmounting_id, 0) = 0
  AND d.row_id > (
      SELECT MIN(x.row_id)
      FROM trdiamond AS x
      WHERE x.is_deleted = 0
        AND x.diamond_type = 'Dossier'
        AND x.certificate = d.certificate
  );

-- B2 + B3. Eksekusi (temp table + UPDATE)
START TRANSACTION;

CREATE TEMPORARY TABLE tmp_soft_delete_dossier (
    row_id INT PRIMARY KEY
);

INSERT INTO tmp_soft_delete_dossier (row_id)
SELECT d.row_id
FROM trdiamond AS d
INNER JOIN (
    SELECT certificate
    FROM trdiamond
    WHERE is_deleted = 0
      AND diamond_type = 'Dossier'
      AND certificate IS NOT NULL
      AND TRIM(certificate) <> ''
    GROUP BY certificate
    HAVING COUNT(*) > 1
) AS dup ON dup.certificate = d.certificate
WHERE d.is_deleted = 0
  AND d.diamond_type = 'Dossier'
  AND d.out_date IS NULL
  AND COALESCE(d.diamondmounting_id, 0) = 0
  AND d.row_id > (
      SELECT MIN(x.row_id)
      FROM trdiamond AS x
      WHERE x.is_deleted = 0
        AND x.diamond_type = 'Dossier'
        AND x.certificate = d.certificate
  );

UPDATE trdiamond AS d
INNER JOIN tmp_soft_delete_dossier AS t ON t.row_id = d.row_id
SET
    d.is_deleted = 1,
    d.deleted_by = 'sistem',
    d.deleted_date = NOW();

DROP TEMPORARY TABLE tmp_soft_delete_dossier;

-- Cek jumlah baris ter-update, lalu:
-- COMMIT;
-- ROLLBACK;

-- -----------------------------------------------------------------------------
-- B4. Verifikasi — harus 0 baris
-- -----------------------------------------------------------------------------
SELECT certificate, COUNT(*) AS jumlah
FROM trdiamond
WHERE is_deleted = 0
  AND diamond_type = 'Dossier'
  AND certificate IS NOT NULL
  AND TRIM(certificate) <> ''
GROUP BY certificate
HAVING COUNT(*) > 1;

-- =============================================================================
-- C. VARIAN: duplikat cert + entry_date + created_by (is_used = 0 saja)
-- Filter tanggal aman (hindari '0000-00-00')
-- =============================================================================

-- C1. Preview
SELECT
    d.row_id,
    d.doc_no,
    d.certificate,
    d.entry_date,
    d.out_date,
    d.diamondmounting_id,
    d.is_used,
    d.created_by
FROM trdiamond AS d
INNER JOIN (
    SELECT certificate, entry_date, created_by
    FROM trdiamond
    WHERE is_deleted = 0
      AND diamond_type = 'Dossier'
      AND certificate IS NOT NULL
      AND TRIM(certificate) <> ''
      AND entry_date IS NOT NULL
      AND entry_date >= '1000-01-01'
    GROUP BY certificate, entry_date, created_by
    HAVING COUNT(*) > 1
       AND SUM(CASE WHEN out_date IS NOT NULL AND out_date >= '1000-01-01' THEN 1 ELSE 0 END) > 0
       AND SUM(CASE WHEN COALESCE(diamondmounting_id, 0) = 0 THEN 1 ELSE 0 END) > 0
) AS dup
    ON dup.certificate = d.certificate
   AND dup.entry_date = d.entry_date
   AND dup.created_by = d.created_by
WHERE d.is_deleted = 0
  AND d.diamond_type = 'Dossier'
  AND d.entry_date >= '1000-01-01'
  AND COALESCE(d.is_used, 0) = 0
ORDER BY d.certificate, d.entry_date, d.row_id;

-- C2. Soft delete (temp table)
START TRANSACTION;

CREATE TEMPORARY TABLE tmp_soft_delete_dossier (
    row_id INT PRIMARY KEY
);

INSERT INTO tmp_soft_delete_dossier (row_id)
SELECT d.row_id
FROM trdiamond AS d
INNER JOIN (
    SELECT certificate, entry_date, created_by
    FROM trdiamond
    WHERE is_deleted = 0
      AND diamond_type = 'Dossier'
      AND certificate IS NOT NULL
      AND TRIM(certificate) <> ''
      AND entry_date IS NOT NULL
      AND entry_date >= '1000-01-01'
    GROUP BY certificate, entry_date, created_by
    HAVING COUNT(*) > 1
       AND SUM(CASE WHEN out_date IS NOT NULL AND out_date >= '1000-01-01' THEN 1 ELSE 0 END) > 0
       AND SUM(CASE WHEN COALESCE(diamondmounting_id, 0) = 0 THEN 1 ELSE 0 END) > 0
) AS dup
    ON dup.certificate = d.certificate
   AND dup.entry_date = d.entry_date
   AND dup.created_by = d.created_by
WHERE d.is_deleted = 0
  AND d.diamond_type = 'Dossier'
  AND d.entry_date >= '1000-01-01'
  AND COALESCE(d.is_used, 0) = 0;

UPDATE trdiamond AS d
INNER JOIN tmp_soft_delete_dossier AS t ON t.row_id = d.row_id
SET
    d.is_deleted = 1,
    d.deleted_by = 'sistem',
    d.deleted_date = NOW();

DROP TEMPORARY TABLE tmp_soft_delete_dossier;

-- COMMIT;
-- ROLLBACK;

-- -----------------------------------------------------------------------------
-- C3. (Opsional) Baris entry_date rusak
-- -----------------------------------------------------------------------------
SELECT row_id, doc_no, certificate, entry_date, out_date
FROM trdiamond
WHERE is_deleted = 0
  AND diamond_type = 'Dossier'
  AND (entry_date IS NULL OR entry_date < '1000-01-01');
