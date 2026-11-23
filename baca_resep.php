<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$currentUser = current_user();
if ($currentUser === null) {
    redirect_to('login.php');
}
$homeUrl = home_url_for_role($currentUser['role']);

$recipeId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($recipeId === false || $recipeId < 1) {
    http_response_code(404);
    exit('Resep tidak ditemukan.');
}

$statement = db()->prepare(
    'SELECT judul, ringkasan, kategori, bahan, langkah, gambar_path, created_at
     FROM resep
     WHERE id = :id AND status = :status
     LIMIT 1'
);
$statement->execute(['id' => $recipeId, 'status' => 'published']);
$recipe = $statement->fetch();

if (!$recipe) {
    http_response_code(404);
    exit('Resep tidak ditemukan.');
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape_html($recipe['judul']); ?> | Mom&amp;Kids+</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body class="auth-page">
    <header class="auth-header container">
        <a class="brand" href="<?php echo $homeUrl; ?>">
            <img class="brand-logo" src="assets/img/LOGO%20Mom%26Kids%2B-3.png" alt="Mom&amp;Kids+">
        </a>
        <a class="button button-small button-outline page-back" href="resep.php">
            <span aria-hidden="true">←</span> Kembali
        </a>
    </header>

    <main class="article-reader container recipe-reader">
        <article>
            <?php
                $recipeImage = $recipe['gambar_path'] !== null
                    ? 'assets/img/' . rawurlencode($recipe['gambar_path'])
                    : match ($recipe['kategori']) {
                        'Ibu Hamil' => 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&w=1200&q=85',
                        '6-8 Bulan' => 'https://images.unsplash.com/photo-1490645935967-10de6ba17061?auto=format&fit=crop&w=1200&q=85',
                        default => 'https://images.unsplash.com/photo-1547592180-85f173990554?auto=format&fit=crop&w=1200&q=85',
                    };
            ?>
            <img class="recipe-cover" src="<?php echo escape_html($recipeImage); ?>"
                alt="Inspirasi hidangan <?php echo escape_html($recipe['judul']); ?>" fetchpriority="high">
            <span class="recipe-category"><?php echo escape_html($recipe['kategori']); ?></span>
            <span class="article-date"><?php echo escape_html(date('d-m-Y', strtotime($recipe['created_at']))); ?></span>
            <h1><?php echo escape_html($recipe['judul']); ?></h1>
            <p class="article-lead"><?php echo escape_html($recipe['ringkasan']); ?></p>

            <section class="recipe-section">
                <h2>Bahan</h2>
                <div class="article-content"><?php echo nl2br(escape_html($recipe['bahan'])); ?></div>
            </section>

            <section class="recipe-section">
                <h2>Langkah pembuatan</h2>
                <div class="article-content"><?php echo nl2br(escape_html($recipe['langkah'])); ?></div>
            </section>

            <p class="recipe-note">
                Sesuaikan bahan dan tekstur makanan dengan kebutuhan keluarga. Untuk kondisi kesehatan
                khusus atau alergi, konsultasikan pilihan menu dengan tenaga kesehatan.
            </p>
        </article>
    </main>
    <script src="assets/js/app.js?v=3"></script>
</body>
</html>
