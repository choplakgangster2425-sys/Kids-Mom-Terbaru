-- Mom&Kids+ All-in-One Database (Skema Lengkap Modul 1-9 + Data Awal)
-- Cukup impor file ini SATU KALI di TablePlus / phpMyAdmin.
-- Aman dijalankan ulang: tidak menimpa tabel dan tidak menduplikasi data contoh.

CREATE DATABASE IF NOT EXISTS momkids_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE momkids_db;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS profil_ibu (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    usia_tahun TINYINT UNSIGNED NULL,
    golongan_darah ENUM('A', 'B', 'AB', 'O', 'Tidak diketahui') NOT NULL DEFAULT 'Tidak diketahui',
    usia_kehamilan_minggu TINYINT UNSIGNED NULL,
    jumlah_anak TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_profil_ibu_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS anak (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    nama VARCHAR(120) NOT NULL,
    tanggal_lahir DATE NOT NULL,
    jenis_kelamin ENUM('L', 'P') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_anak_user (user_id),
    CONSTRAINT fk_anak_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS riwayat_ukur (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    anak_id INT UNSIGNED NOT NULL,
    tanggal_ukur DATE NOT NULL,
    berat_kg DECIMAL(5, 2) NOT NULL,
    tinggi_cm DECIMAL(5, 2) NOT NULL,
    catatan VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_riwayat_ukur_anak_tanggal (anak_id, tanggal_ukur),
    CONSTRAINT fk_riwayat_ukur_anak
        FOREIGN KEY (anak_id) REFERENCES anak (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS riwayat_cek_gizi (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    anak_id INT UNSIGNED NULL,
    umur_bulan SMALLINT UNSIGNED NOT NULL,
    berat_kg DECIMAL(5, 2) NOT NULL,
    tinggi_cm DECIMAL(5, 2) NOT NULL,
    status ENUM('Gizi Baik', 'Kurang Gizi', 'Indikasi Stunting', 'Perlu Penilaian') NOT NULL,
    saran TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_riwayat_cek_gizi_user (user_id, created_at),
    INDEX idx_riwayat_cek_gizi_anak (anak_id),
    CONSTRAINT fk_riwayat_cek_gizi_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_riwayat_cek_gizi_anak
        FOREIGN KEY (anak_id) REFERENCES anak (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS artikel (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    judul VARCHAR(180) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    ringkasan VARCHAR(500) NOT NULL,
    kategori ENUM('AI', 'ASI', 'Gizi', 'Kehamilan', 'MPASI', 'Stunting', 'Tumbuh Kembang') NOT NULL DEFAULT 'Gizi',
    konten MEDIUMTEXT NOT NULL,
    gambar_path VARCHAR(255) NULL,
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_artikel_status_tanggal (status, created_at),
    CONSTRAINT fk_artikel_created_by
        FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS resep (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    judul VARCHAR(180) NOT NULL,
    ringkasan VARCHAR(500) NOT NULL,
    kategori ENUM('Ibu Hamil', '6-8 Bulan', '9-12 Bulan') NOT NULL,
    bahan TEXT NOT NULL,
    langkah MEDIUMTEXT NOT NULL,
    gambar_path VARCHAR(255) NULL,
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_resep_kategori_status (kategori, status),
    CONSTRAINT fk_resep_created_by
        FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS jadwal_posyandu (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tanggal DATE NOT NULL,
    tempat VARCHAR(255) NOT NULL,
    kegiatan VARCHAR(180) NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_jadwal_posyandu_tanggal (tanggal),
    CONSTRAINT fk_jadwal_posyandu_created_by
        FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pendaftaran_posyandu (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    jadwal_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    status ENUM('Terdaftar', 'Hadir', 'Tidak Hadir') NOT NULL DEFAULT 'Terdaftar',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pendaftaran_posyandu_user_jadwal (user_id, jadwal_id),
    INDEX idx_pendaftaran_posyandu_jadwal (jadwal_id),
    CONSTRAINT fk_pendaftaran_posyandu_jadwal
        FOREIGN KEY (jadwal_id) REFERENCES jadwal_posyandu (id) ON DELETE CASCADE,
    CONSTRAINT fk_pendaftaran_posyandu_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS konsultasi (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    pertanyaan TEXT NOT NULL,
    jawaban TEXT NULL,
    status ENUM('Menunggu', 'Dijawab') NOT NULL DEFAULT 'Menunggu',
    answered_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_konsultasi_user_tanggal (user_id, created_at),
    INDEX idx_konsultasi_status_tanggal (status, created_at),
    CONSTRAINT fk_konsultasi_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_konsultasi_answered_by
        FOREIGN KEY (answered_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- DATA AWAL: JADWAL POSYANDU (5 jadwal mendatang)
-- =========================================================
INSERT INTO jadwal_posyandu (tanggal, tempat, kegiatan, created_by)
SELECT DATE_ADD(CURRENT_DATE, INTERVAL 5 DAY),
       'Balai RW 03 - Posyandu Melati',
       'Penimbangan Balita & Pengukuran Tinggi Badan Rutin',
       NULL
WHERE NOT EXISTS (
    SELECT 1 FROM jadwal_posyandu
    WHERE kegiatan = 'Penimbangan Balita & Pengukuran Tinggi Badan Rutin'
      AND tempat = 'Balai RW 03 - Posyandu Melati'
);

INSERT INTO jadwal_posyandu (tanggal, tempat, kegiatan, created_by)
SELECT DATE_ADD(CURRENT_DATE, INTERVAL 12 DAY),
       'Posyandu Mawar - Kelurahan Sukamaju',
       'Imunisasi Dasar Lengkap & Pemberian Vitamin A',
       NULL
WHERE NOT EXISTS (
    SELECT 1 FROM jadwal_posyandu
    WHERE kegiatan = 'Imunisasi Dasar Lengkap & Pemberian Vitamin A'
      AND tempat = 'Posyandu Mawar - Kelurahan Sukamaju'
);

INSERT INTO jadwal_posyandu (tanggal, tempat, kegiatan, created_by)
SELECT DATE_ADD(CURRENT_DATE, INTERVAL 19 DAY),
       'Puskesmas Pembantu Wilayah Timur',
       'Kelas Ibu Hamil & Pemeriksaan Kehamilan Rutin',
       NULL
WHERE NOT EXISTS (
    SELECT 1 FROM jadwal_posyandu
    WHERE kegiatan = 'Kelas Ibu Hamil & Pemeriksaan Kehamilan Rutin'
      AND tempat = 'Puskesmas Pembantu Wilayah Timur'
);

INSERT INTO jadwal_posyandu (tanggal, tempat, kegiatan, created_by)
SELECT DATE_ADD(CURRENT_DATE, INTERVAL 26 DAY),
       'Balai Warga RT 02 - Posyandu Anggrek',
       'Penyuluhan MPASI Gizi Seimbang & Pencegahan Stunting',
       NULL
WHERE NOT EXISTS (
    SELECT 1 FROM jadwal_posyandu
    WHERE kegiatan = 'Penyuluhan MPASI Gizi Seimbang & Pencegahan Stunting'
      AND tempat = 'Balai Warga RT 02 - Posyandu Anggrek'
);

INSERT INTO jadwal_posyandu (tanggal, tempat, kegiatan, created_by)
SELECT DATE_ADD(CURRENT_DATE, INTERVAL 33 DAY),
       'Posyandu Kenanga - Balai Desa',
       'Pemantauan Tumbuh Kembang & Konseling Gizi Anak',
       NULL
WHERE NOT EXISTS (
    SELECT 1 FROM jadwal_posyandu
    WHERE kegiatan = 'Pemantauan Tumbuh Kembang & Konseling Gizi Anak'
      AND tempat = 'Posyandu Kenanga - Balai Desa'
);

-- =========================================================
-- DATA AWAL: ARTIKEL EDUKASI (5 artikel published)
-- =========================================================
INSERT IGNORE INTO artikel (judul, slug, ringkasan, kategori, konten, gambar_path, status, created_by)
VALUES
(
    'Mengenal 1000 Hari Pertama Kehidupan (HPK) untuk Cegah Stunting',
    'mengenal-1000-hari-pertama-kehidupan-hpk',
    'Periode sejak awal kehamilan hingga anak berusia 2 tahun adalah masa emas pertumbuhan otak dan fisik yang menentukan kesehatan jangka panjang.',
    'Stunting',
    '1000 Hari Pertama Kehidupan (HPK) dimulai sejak hari pertama kehamilan (270 hari di dalam kandungan) hingga anak mencapai usia 2 tahun (730 hari). Pada masa ini, pertumbuhan otak dan organ vital berlangsung sangat pesat.

Untuk mendukung tumbuh kembang optimal dan mencegah stunting, ada beberapa langkah penting yang perlu diperhatikan:
1. Penuhi gizi seimbang sejak kehamilan, termasuk konsumsi tablet tambah darah sesuai anjuran tenaga kesehatan.
2. Berikan Inisiasi Menyusu Dini (IMD) dan ASI eksklusif selama 6 bulan pertama.
3. Mulai MPASI bergizi lengkap (karbohidrat, protein hewani, lemak sehat, serta sayur/buah secukupnya) saat bayi berusia 6 bulan.
4. Pantau berat dan panjang/tinggi badan secara rutin setiap bulan di Posyandu atau fasilitas kesehatan.
5. Lengkapi imunisasi dasar dan jaga kebersihan lingkungan agar anak terhindar dari infeksi berulang.

Jika grafik pertumbuhan anak melandai atau berada di bawah garis normal, segera konsultasikan dengan bidan atau dokter agar dapat ditangani sejak dini.',
    NULL,
    'published',
    NULL
),
(
    'Panduan Memulai MPASI Pertama untuk Bayi Usia 6 Bulan',
    'panduan-memulai-mpasi-usia-6-bulan',
    'Kenali tanda kesiapan makan bayi, tekstur yang tepat, serta prinsip menu lengkap agar si kecil lahap dan nutrisinya tercukupi.',
    'MPASI',
    'Saat memasuki usia 6 bulan, ASI saja sudah tidak lagi mencukupi seluruh kebutuhan energi dan zat besi bayi. Inilah saat yang tepat untuk memperkenalkan Makanan Pendamping ASI (MPASI).

Tanda bayi siap menerima MPASI:
- Mampu menahan kepala dengan tegak dan duduk dengan bantuan.
- Menunjukkan ketertarikan saat melihat orang lain makan.
- Refleks menjulurkan lidah (extrusion reflex) mulai berkurang.

Prinsip dasar MPASI usia 6–8 bulan:
- Tekstur: bubur kental (puree/lumatan halus) yang tidak mudah jatuh saat sendok dimiringkan, lalu naik tekstur secara bertahap.
- Komposisi: langsung kenalkan menu lengkap berisi karbohidrat (nasi/kentang), protein hewani (telur, hati ayam, ikan, daging), lemak tambahan (minyak zaitun, santan, mentega tawar), serta sedikit sayur atau buah.
- Frekuensi: 2–3 kali makan utama dan 1–2 kali camilan sehat per hari, tetap didampingi ASI.

Hindari pemberian madu sebelum usia 1 tahun dan pastikan kebersihan tangan serta peralatan makan sebelum menyiapkan makanan.',
    NULL,
    'published',
    NULL
),
(
    'Pentingnya Rutin Menimbang dan Mengukur Tinggi Anak di Posyandu',
    'pentingnya-pemantauan-berat-dan-tinggi-di-posyandu',
    'Pengukuran rutin setiap bulan membantu mendeteksi gangguan pertumbuhan lebih awal sebelum berkembang menjadi kurang gizi atau stunting.',
    'Tumbuh Kembang',
    'Membawa balita ke Posyandu setiap bulan bukan sekadar mencatat angka berat badan, melainkan cara efektif untuk melihat tren pertumbuhan anak dari waktu ke waktu.

Mengapa pemantauan rutin sangat penting?
1. Deteksi dini weight faltering: berat badan yang tidak naik sesuai target dalam 1–2 bulan bisa segera dievaluasi pola makannya.
2. Evaluasi panjang dan tinggi badan: anak di bawah 24 bulan diukur terlentang (panjang badan), sedangkan usia 24 bulan ke atas diukur berdiri (tinggi badan).
3. Akses layanan kesehatan terpadu: ibu juga bisa mendapatkan vitamin A, imunisasi, serta konseling langsung dari kader dan bidan.

Simpan setiap hasil pengukuran di Buku KIA maupun fitur Buku KIA Digital di Mom&Kids+ agar riwayat tumbuh kembang anak terdokumentasi dengan rapi.',
    NULL,
    'published',
    NULL
),
(
    'Nutrisi Penting untuk Ibu Hamil dari Trimester 1 hingga 3',
    'nutrisi-penting-untuk-ibu-hamil-trimester-1-hingga-3',
    'Kebutuhan zat gizi ibu hamil meningkat di setiap trimester untuk mendukung perkembangan janin sekaligus menjaga stamina ibu.',
    'Kehamilan',
    'Selama kehamilan, tubuh ibu membutuhkan asupan energi dan mikronutrien tambahan agar janin berkembang sehat.

Zat gizi utama yang perlu diperhatikan:
- Asam folat: sangat penting terutama pada trimester pertama untuk membantu mencegah cacat tabung saraf. Sumbernya antara lain bayam, brokoli, alpukat, dan kacang-kacangan.
- Zat besi: membantu pembentukan sel darah merah dan mencegah anemia pada ibu hamil. Sumber terbaik berasal dari daging merah, hati ayam, ikan, dan sayuran hijau.
- Protein: berperan membentuk jaringan tubuh dan otak janin; penuhi dari telur, ikan, ayam, tahu, tempe, dan susu.
- Kalsium dan Vitamin D: mendukung pembentukan tulang serta gigi janin sekaligus menjaga kepadatan tulang ibu.
- Cairan cukup: minum air putih minimal 8–10 gelas per hari untuk membantu sirkulasi darah dan mencegah sembelit.

Selalu konsultasikan suplemen kehamilan dengan bidan atau dokter kandungan saat pemeriksaan ANC rutin.',
    NULL,
    'published',
    NULL
),
(
    'Tanda Bahaya Tumbuh Kembang Balita yang Perlu Diwaspadai',
    'tanda-bahaya-tumbuh-kembang-balita-yang-perlu-diwaspadai',
    'Kenali sinyal awal keterlambatan pertumbuhan dan perkembangan agar orang tua dapat segera berkonsultasi dengan tenaga kesehatan.',
    'Tumbuh Kembang',
    'Setiap anak memiliki kecepatan tumbuh kembang yang unik, namun ada beberapa tanda peringatan (red flags) yang sebaiknya tidak diabaikan oleh orang tua.

Tanda yang perlu segera dikonsultasikan ke bidan atau dokter anak:
- Berat badan tidak naik selama 2 bulan berturut-turut atau justru turun drastis.
- Anak tampak sangat lemas, tidak aktif bermain, atau menolak makan dan minum dalam waktu lama.
- Pada usia 6 bulan belum mampu mengangkat kepala dengan mantap saat tengkurap.
- Pada usia 12 bulan belum dapat duduk mandiri atau belum merespons saat dipanggil namanya.
- Pada usia 18 bulan belum mampu berjalan sendiri atau belum mengucapkan kata bermakna.

Semakin cepat tanda bahaya dikenali, semakin besar peluang intervensi dini memberikan hasil yang optimal bagi masa depan anak.',
    NULL,
    'published',
    NULL
);

-- =========================================================
-- DATA AWAL: RESEP MPASI & IBU HAMIL (6 resep published)
-- =========================================================
INSERT INTO resep (judul, ringkasan, kategori, bahan, langkah, gambar_path, status, created_by)
SELECT 'Bubur Saring Hati Ayam & Bayam',
       'MPASI tinggi zat besi dan protein hewani dengan tekstur lembut untuk mendukung pertumbuhan bayi usia 6–8 bulan.',
       '6-8 Bulan',
       '- 3 sdm beras putih, cuci bersih
- 50 gram hati ayam segar, cuci bersih lalu rendam air perasan jeruk nipis sebentar
- 10 lembar daun bayam muda, cincang halus
- 1 siung bawang putih & 1 siung bawang merah, geprek
- 1 lembar daun salam
- 300 ml air atau kaldu ayam tawar
- 1 sdt minyak zaitun atau minyak kelapa untuk lemak tambahan',
       '1. Rebus beras, air/kaldu, bawang putih, bawang merah, dan daun salam hingga menjadi bubur lembut.
2. Masukkan hati ayam, masak dengan api kecil hingga hati benar-benar matang dan empuk.
3. Masukkan daun bayam cincang, aduk sebentar sekitar 2 menit hingga layu, lalu matikan api.
4. Buang daun salam dan bawang geprek, kemudian saring bubur menggunakan saringan kawat halus.
5. Sajikan hangat dengan tambahan 1 sdt minyak zaitun. Atur kekentalan agar tidak terlalu encer.',
       NULL,
       'published',
       NULL
WHERE NOT EXISTS (
    SELECT 1 FROM resep WHERE judul = 'Bubur Saring Hati Ayam & Bayam' AND kategori = '6-8 Bulan'
);

INSERT INTO resep (judul, ringkasan, kategori, bahan, langkah, gambar_path, status, created_by)
SELECT 'Puree Ikan Salmon, Kentang, & Brokoli',
       'Menu MPASI kaya omega-3 untuk perkembangan otak bayi dengan rasa gurih alami tanpa tambahan gula garam berlebih.',
       '6-8 Bulan',
       '- 60 gram kentang, kupas dan potong dadu kecil
- 40 gram fillet ikan salmon (atau ikan kembung tanpa duri)
- 3 kuntum kecil brokoli, cuci bersih
- 1 siung bawang putih, cincang halus
- 1 sdt unsalted butter (mentega tawar)
- 100 ml air matang hangat atau kaldu tawar',
       '1. Kukus kentang, ikan salmon, dan bawang putih selama 15 menit hingga empuk dan matang sempurna.
2. Masukkan brokoli di 5 menit terakhir agar warnanya tetap hijau segar dan nutrisinya terjaga.
3. Haluskan semua bahan yang sudah dikukus menggunakan saringan atau blender bersama sedikit air hangat/kaldu hingga mencapai tekstur bubur kental.
4. Aduk bersama 1 sdt unsalted butter selagi hangat, lalu sajikan segera.',
       NULL,
       'published',
       NULL
WHERE NOT EXISTS (
    SELECT 1 FROM resep WHERE judul = 'Puree Ikan Salmon, Kentang, & Brokoli' AND kategori = '6-8 Bulan'
);

INSERT INTO resep (judul, ringkasan, kategori, bahan, langkah, gambar_path, status, created_by)
SELECT 'Nasi Tim Daging Sapi Cincang & Wortel',
       'Menu tekstur cincang halus bergizi lengkap untuk melatih kemampuan mengunyah bayi usia 9–12 bulan.',
       '9-12 Bulan',
       '- 4 sdm nasi putih lembek
- 50 gram daging sapi giling tanpa lemak
- 2 sdm wortel parut atau cincang halus
- 1 siung bawang putih & 1 butir bawang merah, cincang halus
- 1/2 buah tomat ukuran kecil, buang biji dan cincang
- 250 ml kaldu sapi atau air matang
- 1 sdt minyak canola atau minyak goreng bersih untuk menumis',
       '1. Panaskan minyak, tumis bawang putih dan bawang merah hingga harum.
2. Masukkan daging sapi giling, aduk hingga berubah warna dan matang merata.
3. Tambahkan wortel, tomat, nasi, dan kaldu sapi. Masak dengan api kecil sambil sesekali diaduk hingga air menyusut dan nasi menjadi tim lembut.
4. Sesuaikan tekstur cincangan dengan kemampuan mengunyah anak, lalu sajikan selagi hangat.',
       NULL,
       'published',
       NULL
WHERE NOT EXISTS (
    SELECT 1 FROM resep WHERE judul = 'Nasi Tim Daging Sapi Cincang & Wortel' AND kategori = '9-12 Bulan'
);

INSERT INTO resep (judul, ringkasan, kategori, bahan, langkah, gambar_path, status, created_by)
SELECT 'Perkedel Tahu Telur Puyuh Panggang',
       'Camilan atau lauk finger food lembut yang mudah digenggam bayi 9–12 bulan, kaya protein nabati dan hewani.',
       '9-12 Bulan',
       '- 100 gram tahu putih, haluskan dengan garpu
- 2 butir telur puyuh (atau 1 butir telur ayam ukuran kecil)
- 1 sdm wortel parut halus
- 1 batang daun bawang, iris tipis
- 1 siung bawang putih, haluskan
- 1 sdm tepung terigu atau tepung oat sebagai pengikat
- 1 sdt margarin/minyak untuk mengoles teflon',
       '1. Campurkan tahu halus, telur, wortel parut, daun bawang, bawang putih halus, dan tepung dalam wadah hingga rata.
2. Bentuk adonan menjadi bulatan pipih kecil seukuran genggaman jari anak.
3. Oles tipis wajan anti lengket dengan minyak/margarin, panggang dengan api kecil sambil ditutup hingga kedua sisi matang kecokelatan.
4. Dinginkan sebentar hingga hangat sebelum disajikan sebagai lauk atau finger food.',
       NULL,
       'published',
       NULL
WHERE NOT EXISTS (
    SELECT 1 FROM resep WHERE judul = 'Perkedel Tahu Telur Puyuh Panggang' AND kategori = '9-12 Bulan'
);

INSERT INTO resep (judul, ringkasan, kategori, bahan, langkah, gambar_path, status, created_by)
SELECT 'Sup Ikan Kembung Kuah Bening Daun Kemangi',
       'Hidangan segar tinggi protein dan omega-3 yang mudah dicerna serta membantu mengurangi rasa mual pada ibu hamil.',
       'Ibu Hamil',
       '- 2 ekor ikan kembung segar, bersihkan dan potong dua
- 1 buah jeruk nipis untuk marinasi
- 1 buah wortel, iris bulat
- 1 buah tomat merah, potong wedges
- 1 genggam daun kemangi segar
- 3 siung bawang merah & 2 siung bawang putih, iris tipis
- 2 cm jahe & 1 batang serai, memarkan
- 750 ml air, garam dan lada secukupnya',
       '1. Lumuri ikan kembung dengan air perasan jeruk nipis selama 10 menit, lalu bilas bersih.
2. Didihkan air bersama bawang merah, bawang putih, jahe, dan serai hingga harum.
3. Masukkan wortel, masak hingga setengah empuk, lalu masukkan ikan kembung dan masak hingga ikan matang sempurna.
4. Tambahkan tomat, garam, dan sedikit lada. Matikan api lalu masukkan daun kemangi agar aromanya segar.
5. Sajikan hangat bersama nasi putih.',
       NULL,
       'published',
       NULL
WHERE NOT EXISTS (
    SELECT 1 FROM resep WHERE judul = 'Sup Ikan Kembung Kuah Bening Daun Kemangi' AND kategori = 'Ibu Hamil'
);

INSERT INTO resep (judul, ringkasan, kategori, bahan, langkah, gambar_path, status, created_by)
SELECT 'Tumis Bayam Tempe Tahu Bawang Putih',
       'Menu harian praktis kaya asam folat, zat besi nabati, dan protein untuk mendukung stamina dan perkembangan janin.',
       'Ibu Hamil',
       '- 1 ikat bayam segar, petik daunnya dan cuci bersih
- 100 gram tempe, potong dadu kecil
- 100 gram tahu putih, potong dadu kecil
- 3 siung bawang putih & 3 siung bawang merah, iris tipis
- 1/2 buah tomat, potong dadu
- 100 ml air matang
- 1 sdm minyak goreng, sedikit garam dan kaldu jamur secukupnya',
       '1. Panaskan minyak, tumis bawang merah dan bawang putih hingga harum.
2. Masukkan potongan tempe dan tahu, aduk perlahan hingga permukaan sedikit kecokelatan.
3. Tuang air matang dan masukkan tomat, masak sekitar 3 menit hingga bumbu meresap.
4. Masukkan daun bayam, bumbui dengan garam dan kaldu secukupnya, lalu aduk cepat 1–2 menit hingga bayam layu.
5. Angkat segera agar vitamin dan warna hijau bayam tetap terjaga.',
       NULL,
       'published',
       NULL
WHERE NOT EXISTS (
    SELECT 1 FROM resep WHERE judul = 'Tumis Bayam Tempe Tahu Bawang Putih' AND kategori = 'Ibu Hamil'
);
