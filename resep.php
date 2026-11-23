<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$currentUser = current_user();
if ($currentUser === null) {
    redirect_to('login.php');
}
$homeUrl = home_url_for_role($currentUser['role']);

$categories = ['Ibu Hamil', '6-8 Bulan', '9-12 Bulan'];
$category = trim(is_string($_GET['kategori'] ?? null) ? $_GET['kategori'] : '');

if ($category !== '' && !in_array($category, $categories, true)) {
    http_response_code(400);
    exit('Kategori resep tidak valid.');
}

$recipesSql = 'SELECT id, judul, ringkasan, kategori, gambar_path, created_at
               FROM resep
               WHERE status = :status';
$parameters = ['status' => 'published'];

if ($category !== '') {
    $recipesSql .= ' AND kategori = :kategori';
    $parameters['kategori'] = $category;
}

$recipesSql .= ' ORDER BY created_at DESC, id DESC';
$statement = db()->prepare($recipesSql);
$statement->execute($parameters);
$recipes = $statement->fetchAll();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resep MPASI Sehat | Mom&amp;Kids+</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body class="auth-page">
    <header class="auth-header container">
        <a class="brand" href="<?php echo $homeUrl; ?>">
            <img class="brand-logo" src="assets/img/LOGO%20Mom%26Kids%2B-3.png" alt="Mom&amp;Kids+">
        </a>
        <div class="profile-header-actions">
            <a class="button button-small button-outline page-back" href="<?php echo $homeUrl; ?>">
                <span aria-hidden="true">←</span> Kembali
            </a>
            <form method="post" action="logout.php">
                <input type="hidden" name="csrf_token" value="<?php echo escape_html(csrf_token()); ?>">
                <button class="button button-small button-outline" type="submit">Keluar</button>
            </form>
        </div>
    </header>

    <main class="management-main container">
        <div class="management-heading" data-reveal>
            <span class="eyebrow">Inspirasi menu sehat</span>
            <h1>Katalog resep</h1>
            <p>Ide menu sederhana dan bernutrisi untuk menemani masa kehamilan hingga setiap tahap makan si kecil.</p>
        </div>

        <nav class="category-filter" aria-label="Filter kategori resep">
            <a class="<?php echo $category === '' ? 'is-active' : ''; ?>"
                href="resep.php" <?php echo $category === '' ? 'aria-current="page"' : ''; ?>>Semua</a>
            <?php foreach ($categories as $item): ?>
                <a class="<?php echo $category === $item ? 'is-active' : ''; ?>"
                    href="resep.php?kategori=<?php echo rawurlencode($item); ?>"
                    <?php echo $category === $item ? 'aria-current="page"' : ''; ?>>
                    <?php echo escape_html($item); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if (!$recipes): ?>
            <p class="empty-management">
                <?php echo $category === ''
                    ? 'Belum ada resep yang dipublikasikan.'
                    : 'Belum ada resep pada kategori ini.'; ?>
            </p>
        <?php else: ?>
            <div class="article-grid recipe-grid">
                <?php foreach ($recipes as $recipe): ?>
                    <article class="article-card recipe-card" data-reveal>
                        <?php
                            $recipeImage = $recipe['gambar_path'] !== null
                                ? 'assets/img/' . rawurlencode($recipe['gambar_path'])
                                : match ($recipe['kategori']) {
                                    'Ibu Hamil' => 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&w=960&q=85',
                                    '6-8 Bulan' => 'https://images.unsplash.com/photo-1490645935967-10de6ba17061?auto=format&fit=crop&w=960&q=85',
                                    default => 'https://images.unsplash.com/photo-1547592180-85f173990554?auto=format&fit=crop&w=960&q=85',
                                };
                        ?>
                        <a class="article-image recipe-image" href="baca_resep.php?id=<?php echo (int) $recipe['id']; ?>">
                            <img src="<?php echo escape_html($recipeImage); ?>"
                                alt="Inspirasi hidangan <?php echo escape_html($recipe['judul']); ?>" loading="lazy">
                        </a>
                        <div class="article-card-body">
                            <span class="recipe-category"><?php echo escape_html($recipe['kategori']); ?></span>
                            <span class="article-date"><?php echo escape_html(date('d-m-Y', strtotime($recipe['created_at']))); ?></span>
                            <h2><a href="baca_resep.php?id=<?php echo (int) $recipe['id']; ?>">
                                <?php echo escape_html($recipe['judul']); ?>
                            </a></h2>
                            <p><?php echo escape_html($recipe['ringkasan']); ?></p>
                            <a class="action-link" href="baca_resep.php?id=<?php echo (int) $recipe['id']; ?>">Lihat resep</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
    <script src="assets/js/app.js?v=3"></script>
</body>
</html>
