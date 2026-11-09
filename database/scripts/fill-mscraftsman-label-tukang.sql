-- =============================================================================
-- Isi mscraftsman.label_tukang sesuai mapping pengrajin
--
-- Database third (mscraftsman): `u1128725_new-system-mahakarya`
-- Sesuaikan nama database jika environment berbeda.
--
-- Matching: LOWER(TRIM(mscraftsman.name)) = LOWER(TRIM(nama di mapping)).
-- Jika preview ada baris yang tidak ketemu, sesuaikan nama di CTE label_map
-- agar sama persis dengan kolom mscraftsman.name.
--
-- Cara pakai: jalankan preview → START TRANSACTION → UPDATE → COMMIT
-- =============================================================================

-- -----------------------------------------------------------------------------
-- A. PREVIEW (baris yang akan di-update)
-- -----------------------------------------------------------------------------
WITH label_map (craftsman_name, label_tukang) AS (
    SELECT 'Traiza', 'Finishing Junior' UNION ALL
    SELECT 'Rifqi Nuroji', 'Finishing Junior' UNION ALL
    SELECT 'M Zidane', 'Finishing Junior' UNION ALL
    SELECT 'Lukman', 'Finishing Junior' UNION ALL
    SELECT 'Encan Hidayat', 'Master & Finishing' UNION ALL
    SELECT 'ade rohimat', 'Master & Finishing' UNION ALL
    SELECT 'agus sopyan', 'Master & Finishing' UNION ALL
    SELECT 'Nurdin', 'Master R&D' UNION ALL
    SELECT 'edy winandar', 'Master R&D' UNION ALL
    SELECT 'Toni cahyadi', 'Master R&D' UNION ALL
    SELECT 'jajang', 'Master R&D' UNION ALL
    SELECT 'Yadi', 'Master & Reparasi' UNION ALL
    SELECT 'toni mulyana', 'Master & Reparasi' UNION ALL
    SELECT 'tana', 'Pengrajin Pasang Batu' UNION ALL
    SELECT 'ujang hendi', 'Pengrajin Pasang Batu' UNION ALL
    SELECT 'doni', 'Pengrajin Pasang Batu' UNION ALL
    SELECT 'rukma', 'Pengrajin Pasang Batu' UNION ALL
    SELECT 'deni', 'Pengrajin Pasang Batu' UNION ALL
    SELECT 'alfin', 'Pengrajin Poles Rangka' UNION ALL
    SELECT 'sepri', 'Pengrajin Poles BRJ' UNION ALL
    SELECT 'oding', 'Poles Chroom' UNION ALL
    SELECT 'agus', 'Casting' UNION ALL
    SELECT 'anto', 'Jewelcad' UNION ALL
    SELECT 'yandi', 'Jewelcad' UNION ALL
    SELECT 'ega', 'Jewelcad'
)
SELECT
    c.row_id,
    c.name AS craftsman_name,
    c.label_tukang AS label_sebelum,
    m.label_tukang AS label_sesudah
FROM `u1128725_new-system-mahakarya`.mscraftsman AS c
INNER JOIN label_map AS m
    ON LOWER(TRIM(c.name)) = LOWER(TRIM(m.craftsman_name))
WHERE c.is_deleted = 0
ORDER BY m.label_tukang, c.name;

-- -----------------------------------------------------------------------------
-- A2. PREVIEW — mapping tanpa pasangan di mscraftsman (perlu cek nama)
-- -----------------------------------------------------------------------------
WITH label_map (craftsman_name, label_tukang) AS (
    SELECT 'Traiza', 'Finishing Junior' UNION ALL
    SELECT 'Rifqi Nuroji', 'Finishing Junior' UNION ALL
    SELECT 'M Zidane', 'Finishing Junior' UNION ALL
    SELECT 'Lukman', 'Finishing Junior' UNION ALL
    SELECT 'Encan Hidayat', 'Master & Finishing' UNION ALL
    SELECT 'ade rohimat', 'Master & Finishing' UNION ALL
    SELECT 'agus sopyan', 'Master & Finishing' UNION ALL
    SELECT 'Nurdin', 'Master R&D' UNION ALL
    SELECT 'edy winandar', 'Master R&D' UNION ALL
    SELECT 'Toni cahyadi', 'Master R&D' UNION ALL
    SELECT 'jajang', 'Master R&D' UNION ALL
    SELECT 'Yadi', 'Master & Reparasi' UNION ALL
    SELECT 'toni mulyana', 'Master & Reparasi' UNION ALL
    SELECT 'tana', 'Pengrajin Pasang Batu' UNION ALL
    SELECT 'ujang hendi', 'Pengrajin Pasang Batu' UNION ALL
    SELECT 'doni', 'Pengrajin Pasang Batu' UNION ALL
    SELECT 'rukma', 'Pengrajin Pasang Batu' UNION ALL
    SELECT 'deni', 'Pengrajin Pasang Batu' UNION ALL
    SELECT 'alfin', 'Pengrajin Poles Rangka' UNION ALL
    SELECT 'sepri', 'Pengrajin Poles BRJ' UNION ALL
    SELECT 'oding', 'Poles Chroom' UNION ALL
    SELECT 'agus', 'Casting' UNION ALL
    SELECT 'anto', 'Jewelcad' UNION ALL
    SELECT 'yandi', 'Jewelcad' UNION ALL
    SELECT 'ega', 'Jewelcad'
)
SELECT
    m.craftsman_name,
    m.label_tukang,
    'Tidak ditemukan di mscraftsman (cek ejaan / kapitalisasi name)' AS keterangan
FROM label_map AS m
LEFT JOIN `u1128725_new-system-mahakarya`.mscraftsman AS c
    ON c.is_deleted = 0
   AND LOWER(TRIM(c.name)) = LOWER(TRIM(m.craftsman_name))
WHERE c.row_id IS NULL
ORDER BY m.label_tukang, m.craftsman_name;

-- =============================================================================
-- B. UPDATE
-- =============================================================================
START TRANSACTION;

UPDATE `u1128725_new-system-mahakarya`.mscraftsman AS c
INNER JOIN (
    SELECT 'Traiza' AS craftsman_name, 'Finishing Junior' AS label_tukang UNION ALL
    SELECT 'Rifqi Nuroji', 'Finishing Junior' UNION ALL
    SELECT 'M Zidane', 'Finishing Junior' UNION ALL
    SELECT 'Lukman', 'Finishing Junior' UNION ALL
    SELECT 'Encan Hidayat', 'Master & Finishing' UNION ALL
    SELECT 'ade rohimat', 'Master & Finishing' UNION ALL
    SELECT 'agus sopyan', 'Master & Finishing' UNION ALL
    SELECT 'Nurdin', 'Master R&D' UNION ALL
    SELECT 'edy winandar', 'Master R&D' UNION ALL
    SELECT 'Toni cahyadi', 'Master R&D' UNION ALL
    SELECT 'jajang', 'Master R&D' UNION ALL
    SELECT 'Yadi', 'Master & Reparasi' UNION ALL
    SELECT 'toni mulyana', 'Master & Reparasi' UNION ALL
    SELECT 'tana', 'Pengrajin Pasang Batu' UNION ALL
    SELECT 'ujang hendi', 'Pengrajin Pasang Batu' UNION ALL
    SELECT 'doni', 'Pengrajin Pasang Batu' UNION ALL
    SELECT 'rukma', 'Pengrajin Pasang Batu' UNION ALL
    SELECT 'deni', 'Pengrajin Pasang Batu' UNION ALL
    SELECT 'alfin', 'Pengrajin Poles Rangka' UNION ALL
    SELECT 'sepri', 'Pengrajin Poles BRJ' UNION ALL
    SELECT 'oding', 'Poles Chroom' UNION ALL
    SELECT 'agus', 'Casting' UNION ALL
    SELECT 'anto', 'Jewelcad' UNION ALL
    SELECT 'yandi', 'Jewelcad' UNION ALL
    SELECT 'ega', 'Jewelcad'
) AS m
    ON LOWER(TRIM(c.name)) = LOWER(TRIM(m.craftsman_name))
SET
    c.label_tukang = m.label_tukang,
    c.modified_date = NOW(),
    c.modified_by = 'sistem'
WHERE c.is_deleted = 0;

-- Cek jumlah baris ter-update, lalu:
-- COMMIT;
-- ROLLBACK;
