USE momkids_db;

SET @artikel_kategori_baru := (
    SELECT COUNT(*) = 0
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'artikel'
      AND COLUMN_NAME = 'kategori'
);

SET @artikel_tambah_kategori_sql := IF(
    @artikel_kategori_baru,
    'ALTER TABLE artikel
     ADD COLUMN kategori ENUM(''AI'', ''ASI'', ''Gizi'', ''Kehamilan'', ''MPASI'', ''Stunting'', ''Tumbuh Kembang'')
     NOT NULL DEFAULT ''Gizi'' AFTER ringkasan',
    'SELECT ''Kolom kategori artikel sudah tersedia.'' AS status_migrasi'
);

PREPARE artikel_tambah_kategori_stmt FROM @artikel_tambah_kategori_sql;
EXECUTE artikel_tambah_kategori_stmt;
DEALLOCATE PREPARE artikel_tambah_kategori_stmt;

UPDATE artikel
SET kategori = CASE
    WHEN LOWER(judul) LIKE '%mpasi%' THEN 'MPASI'
    WHEN LOWER(judul) LIKE '%stunting%' THEN 'Stunting'
    WHEN LOWER(judul) LIKE '%hamil%'
      OR LOWER(judul) LIKE '%kehamil%' THEN 'Kehamilan'
    WHEN LOWER(judul) LIKE '% asi %'
      OR LOWER(judul) LIKE 'asi %'
      OR LOWER(judul) LIKE '% asi' THEN 'ASI'
    WHEN LOWER(judul) LIKE '%posyandu%'
      OR LOWER(judul) LIKE '%tumbuh%' THEN 'Tumbuh Kembang'
    ELSE 'Gizi'
END
WHERE @artikel_kategori_baru = 1;
