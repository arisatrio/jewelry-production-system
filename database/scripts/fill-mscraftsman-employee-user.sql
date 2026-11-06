-- =============================================================================
-- Isi mscraftsman.employee_id dan mscraftsman.user_id
--
-- Database utama (employee, users): mahakarya
-- Database third (mscraftsman): `u1128725_new-system-mahakarya`
-- Sesuaikan kedua nama itu jika environment berbeda.
--
-- Aturan: employee.nama_lengkap LIKE %mscraftsman.name%,
-- pegawai is_deleted = 0. user_id diisi dari users.employee_id yang sama
-- dan is_deleted = 0.
--
-- Cara pakai: jalankan preview → START TRANSACTION → UPDATE → COMMIT
-- =============================================================================

-- -----------------------------------------------------------------------------
-- A. PREVIEW
-- -----------------------------------------------------------------------------
SELECT
    c.row_id,
    c.name AS craftsman_name,
    e.id AS employee_id,
    e.nama_lengkap,
    u.id AS user_id,
    u.name AS user_name
FROM `u1128725_new-system-mahakarya`.mscraftsman AS c
INNER JOIN mahakarya.employee AS e
    ON TRIM(c.name) = TRIM(e.nama_lengkap)
   AND e.is_deleted = 0
LEFT JOIN mahakarya.users AS u
    ON u.employee_id = e.id
   AND u.is_deleted = 0
WHERE c.name IS NOT NULL
  AND TRIM(c.name) <> ''
ORDER BY c.name, c.row_id;

-- =============================================================================
-- B. UPDATE
-- =============================================================================
START TRANSACTION;

UPDATE `u1128725_new-system-mahakarya`.mscraftsman AS c
INNER JOIN mahakarya.employee AS e
    ON TRIM(c.name) = TRIM(e.nama_lengkap)
   AND e.is_deleted = 0
LEFT JOIN mahakarya.users AS u
    ON u.employee_id = e.id
   AND u.is_deleted = 0
SET
    c.employee_id = e.id,
    c.user_id = u.id,
    c.modified_date = NOW(),
    c.modified_by = 'sistem'
WHERE c.name IS NOT NULL
  AND TRIM(c.name) <> '';

-- Cek jumlah baris ter-update, lalu:
-- COMMIT;
-- ROLLBACK;
