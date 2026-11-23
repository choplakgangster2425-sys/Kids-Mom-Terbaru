<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$currentUser = current_user();
$isLoggedIn = $currentUser !== null;
$isAdmin = $isLoggedIn && $currentUser['role'] === 'admin';
$featureHref = static function (string $target) use ($isLoggedIn): string {
    return $isLoggedIn ? $target : 'login.php';
};

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#fff8f8">
    <meta name="description" content="Mom&Kids+ mendampingi ibu memantau tumbuh kembang anak dan menemukan informasi kesehatan keluarga.">
    <title>Mom&Kids+ | Temani Tumbuh Kembang Si Kecil</title>
    <link rel="stylesheet" href="assets/css/style.css?v=4">
</head>
<body>
    <header class="site-header">
        <div class="container header-content">
            <a class="brand" href="#beranda" aria-label="Mom&Kids+, beranda">
                <img class="brand-logo" src="assets/img/LOGO%20Mom%26Kids%2B-3.png" alt="Mom&amp;Kids+">
            </a>

            <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="main-nav">
                <span class="sr-only">Buka menu</span>
                <span></span>
                <span></span>
                <span></span>
            </button>

            <nav class="main-nav<?php echo $isLoggedIn ? ' main-nav-auth' : ''; ?>" id="main-nav" aria-label="Navigasi utama">
                <?php if (!$isLoggedIn): ?>
                    <a href="#fitur">Fitur</a>
                    <a href="#tentang">Tentang</a>
                    <a href="login.php">Artikel</a>
                    <a href="login.php">Resep</a>
                    <a class="button button-small button-outline" href="login.php">Masuk</a>
                <?php elseif ($isAdmin): ?>
                    <a href="anak.php">Data Anak</a>
                    <a href="cek_gizi.php">Cek Gizi</a>
                    <a href="artikel.php">Artikel</a>
                    <a href="resep.php">Resep</a>
                    <span class="nav-greeting">Hai Admin, <?php echo escape_html($currentUser['nama']); ?></span>
                    <a class="button button-small button-primary" href="dashboard.php">Dashboard Admin</a>
                    <form class="nav-logout-form" method="post" action="logout.php">
                        <input type="hidden" name="csrf_token" value="<?php echo escape_html(csrf_token()); ?>">
                        <button class="button button-small button-outline" type="submit">Keluar</button>
                    </form>
                <?php else: ?>
                    <a href="profile.php">Profil</a>
                    <a href="anak.php">Data Anak</a>
                    <a href="cek_gizi.php">Cek Gizi</a>
                    <a href="posyandu.php">Posyandu</a>
                    <a href="konsultasi.php">Tanya Bidan</a>
                    <a href="artikel.php">Artikel</a>
                    <a href="resep.php">Resep</a>
                    <span class="nav-greeting">Hai Ibu, <?php echo escape_html($currentUser['nama']); ?></span>
                    <form class="nav-logout-form" method="post" action="logout.php">
                        <input type="hidden" name="csrf_token" value="<?php echo escape_html(csrf_token()); ?>">
                        <button class="button button-small button-outline" type="submit">Keluar</button>
                    </form>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main>
        <section class="hero container" id="beranda">
            <div class="hero-copy">
                <span class="eyebrow"><span class="eyebrow-dot"></span> Ruang tumbuh untuk ibu dan anak</span>
                <h1>Temani setiap langkah <span>tumbuh kembang</span> si kecil.</h1>
                <p class="hero-description">
                    Catat perjalanan tumbuh kembang anak, pantau gizinya, dan temukan
                    bacaan serta resep sehat untuk keluarga.
                </p>
                <div class="hero-actions">
                    <a class="button button-primary" href="#fitur">Jelajahi fitur <span aria-hidden="true">→</span></a>
                    <a class="text-link" href="#tentang">Kenali Mom&amp;Kids+</a>
                </div>
                <div class="hero-note">
                    <span class="note-icon" aria-hidden="true">♡</span>
                    <span>Dibuat untuk menemani perjalanan ibu dan buah hati.</span>
                </div>
            </div>

            <div class="hero-art hero-art-image">
                <img src="assets/img/family-illustration.svg" alt="Ilustrasi keluarga yang bahagia, ibu dan ayah memeluk anak mereka">
            </div>
        </section>

        <section class="feature-section" id="fitur">
            <div class="container">
                <div class="section-heading">
                    <span class="eyebrow">Dukungan untuk keluarga</span>
                    <h2>Semua yang ibu butuhkan, <span>dalam satu tempat.</span></h2>
                    <p>Fitur Mom&amp;Kids+ dirancang untuk membuat perjalanan merawat keluarga terasa lebih mudah.</p>
                </div>

                <div class="feature-grid">
                    <article class="feature-card" data-reveal>
                        <span class="feature-icon icon-pink" aria-hidden="true">♡</span>
                        <h3>Profil Ibu</h3>
                        <p>Simpan informasi dasar ibu dan kelola profil keluarga dengan praktis.</p>
                        <a class="feature-action" href="<?php echo $featureHref($isAdmin ? 'admin/ibu.php' : 'profile.php'); ?>">Kelola profil</a>
                    </article>
                    <article class="feature-card" data-reveal>
                        <span class="feature-icon icon-peach" aria-hidden="true">⌁</span>
                        <h3>Buku KIA Digital</h3>
                        <p>Catat data anak dan pantau riwayat pengukuran tumbuh kembangnya.</p>
                        <a class="feature-action" href="<?php echo $featureHref('anak.php'); ?>">Buka data anak</a>
                    </article>
                    <article class="feature-card" data-reveal>
                        <span class="feature-icon icon-lilac" aria-hidden="true">♡</span>
                        <h3>Pantau Gizi</h3>
                        <p>Lihat indikasi status gizi anak dari data usia, berat, dan tinggi badan.</p>
                        <a class="feature-action" href="<?php echo $featureHref('cek_gizi.php'); ?>">Mulai cek gizi</a>
                    </article>
                    <article class="feature-card" data-reveal>
                        <span class="feature-icon icon-yellow" aria-hidden="true">✿</span>
                        <h3>Artikel &amp; Resep</h3>
                        <p>Temukan bacaan kesehatan dan inspirasi menu MPASI sesuai usia.</p>
                        <a class="feature-action" href="<?php echo $featureHref('artikel.php'); ?>">Baca artikel</a>
                        <a class="feature-action" href="<?php echo $featureHref('resep.php'); ?>">Lihat resep</a>
                    </article>
                    <article class="feature-card" data-reveal>
                        <span class="feature-icon icon-pink" aria-hidden="true">♡</span>
                        <h3>Jadwal Posyandu</h3>
                        <p>Lihat kegiatan Posyandu mendatang dan daftar kehadiran dengan mudah.</p>
                        <a class="feature-action" href="<?php echo $featureHref($isAdmin ? 'admin/posyandu.php' : 'posyandu.php'); ?>">Lihat jadwal</a>
                    </article>
                    <article class="feature-card" data-reveal>
                        <span class="feature-icon icon-lilac" aria-hidden="true">?</span>
                        <h3>Tanya Bidan</h3>
                        <p>Kirim pertanyaan dan pantau balasan Admin/Bidan melalui konsultasi.</p>
                        <a class="feature-action" href="<?php echo $featureHref($isAdmin ? 'admin/konsultasi.php' : 'konsultasi.php'); ?>">Kirim pertanyaan</a>
                    </article>
                </div>
            </div>
        </section>

        <section class="about-section container" id="tentang" data-reveal>
            <div class="about-badge" aria-hidden="true"><span>Mom<br>&amp; Kids</span><strong>+</strong></div>
            <div>
                <span class="eyebrow">Tentang Mom&amp;Kids+</span>
                <h2>Merawat tumbuh kembang, <span>dimulai dari perhatian.</span></h2>
                <p>
                    Mom&amp;Kids+ adalah ruang digital untuk membantu ibu mencatat perjalanan
                    tumbuh kembang anak dan mengakses informasi keluarga dalam satu tempat. Mulai dari
                    menyimpan data keluarga, melihat catatan pengukuran, hingga menemukan inspirasi
                    makanan sehat dan edukasi yang mudah dipahami, semuanya dirancang untuk menemani
                    ibu mengambil langkah kecil yang berarti setiap hari. Informasi di Mom&amp;Kids+
                    melengkapi, bukan menggantikan, saran dari bidan atau tenaga kesehatan.
                </p>
                <a class="button button-small button-outline about-action" href="#fitur">Jelajahi dukungan keluarga</a>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container footer-content">
            <a class="brand footer-brand" href="#beranda">
                <img class="brand-logo" src="assets/img/LOGO%20Mom%26Kids%2B-3.png" alt="Mom&amp;Kids+">
            </a>
            <div class="footer-message">
                <strong>Temani tumbuhnya, jaga sehatnya.</strong>
                <p>Ruang praktis untuk catatan tumbuh kembang, edukasi keluarga, dan inspirasi menu sehat.</p>
            </div>
            <nav class="footer-links" aria-label="Tautan cepat">
                <a href="#fitur">Fitur</a>
                <a href="#tentang">Tentang kami</a>
                <a href="<?php echo $featureHref('artikel.php'); ?>">Artikel</a>
                <a href="<?php echo $featureHref('resep.php'); ?>">Resep sehat</a>
            </nav>
            <span class="copyright">&copy; <?php echo date('Y'); ?> Mom&amp;Kids+. Dibuat dengan perhatian untuk ibu dan anak.</span>
        </div>
    </footer>

    <script src="assets/js/app.js?v=3"></script>
</body>
</html>
