<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$currentUser = current_user();
if ($currentUser === null) {
    redirect_to('login.php');
}
$homeUrl = home_url_for_role($currentUser['role']);

$search = trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');
$categories = ['AI', 'ASI', 'Gizi', 'Kehamilan', 'MPASI', 'Stunting', 'Tumbuh Kembang'];
$category = trim(is_string($_GET['kategori'] ?? null) ? $_GET['kategori'] : '');
if ($category !== '' && !in_array($category, $categories, true)) {
    http_response_code(400);
    exit('Kategori artikel tidak valid.');
}

$articleCategory = static function (array $article): string {
    $text = strtolower($article['judul'] . ' ' . $article['ringkasan']);
    if (str_contains($text, 'mpasi')) {
        return 'MPASI';
    }
    if (str_contains($text, 'stunting')) {
        return 'Stunting';
    }
    if (str_contains($text, 'kehamil')) {
        return 'Kehamilan';
    }
    if (preg_match('/(^|[^a-z])asi([^a-z]|$)/', $text) === 1 || str_contains($text, 'menyusu')) {
        return 'ASI';
    }
    if (str_contains($text, 'posyandu') || str_contains($text, 'tumbuh kembang')) {
        return 'Tumbuh Kembang';
    }

    return 'Gizi';
};

$articlesSql = 'SELECT id, judul, ringkasan, gambar_path, created_at
                FROM artikel
                WHERE status = :status';
$parameters = ['status' => 'published'];

if ($search !== '') {
    $articlesSql .= ' AND (judul LIKE :judul_search OR ringkasan LIKE :ringkasan_search)';
    $parameters['judul_search'] = '%' . $search . '%';
    $parameters['ringkasan_search'] = '%' . $search . '%';
}

$articlesSql .= ' ORDER BY created_at DESC, id DESC';
$statement = db()->prepare($articlesSql);
$statement->execute($parameters);
$articles = $statement->fetchAll();
if ($category !== '') {
    $articles = array_values(array_filter(
        $articles,
        static fn (array $article): bool => $articleCategory($article) === $category
    ));
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Artikel Edukasi | Mom&amp;Kids+</title>
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
            <span class="eyebrow">Bacaan untuk keluarga</span>
            <h1>Artikel edukasi</h1>
            <p>Temukan panduan yang tepercaya dan mudah dipahami seputar kesehatan ibu, gizi, MPASI, serta tumbuh kembang anak.</p>
        </div>

        <form method="get" action="artikel.php" class="article-search" data-reveal>
            <label class="sr-only" for="q">Cari judul artikel</label>
            <input id="q" name="q" type="search" maxlength="180"
                placeholder="Cari artikel..." value="<?php echo escape_html($search); ?>">
            <button class="button button-primary" type="submit">Cari</button>
        </form>

        <nav class="category-filter article-category-filter" aria-label="Filter kategori artikel" data-reveal>
            <a class="<?php echo $category === '' ? 'is-active' : ''; ?>"
                href="artikel.php<?php echo $search !== '' ? '?q=' . rawurlencode($search) : ''; ?>"
                <?php echo $category === '' ? 'aria-current="page"' : ''; ?>>Semua</a>
            <?php foreach ($categories as $item): ?>
                <a class="<?php echo $category === $item ? 'is-active' : ''; ?>"
                    href="artikel.php?kategori=<?php echo rawurlencode($item); ?><?php echo $search !== '' ? '&q=' . rawurlencode($search) : ''; ?>"
                    <?php echo $category === $item ? 'aria-current="page"' : ''; ?>>
                    <?php echo escape_html($item); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <p class="article-result-count"><?php echo count($articles); ?> artikel<?php echo $category !== '' ? ' dalam ' . escape_html($category) : ''; ?></p>

        <?php if (!$articles): ?>
            <p class="empty-management">
                <?php echo $search === ''
                    ? 'Belum ada artikel yang dipublikasikan.'
                    : 'Tidak ada artikel yang cocok dengan pencarian.'; ?>
            </p>
        <?php else: ?>
            <div class="article-grid">
                <?php foreach ($articles as $article): ?>
                    <article class="article-card article-list-card" data-reveal>
                        <?php if ($article['gambar_path'] !== null): ?>
                            <a class="article-image" href="baca_artikel.php?id=<?php echo (int) $article['id']; ?>">
                                <img src="assets/img/<?php echo escape_html($article['gambar_path']); ?>"
                                    alt="" loading="lazy">
                            </a>
                        <?php endif; ?>
                        <div class="article-card-body">
                            <span class="recipe-category"><?php echo escape_html($articleCategory($article)); ?></span>
                            <span class="article-date"><?php echo escape_html(date('d-m-Y', strtotime($article['created_at']))); ?></span>
                            <h2><a href="baca_artikel.php?id=<?php echo (int) $article['id']; ?>">
                                <?php echo escape_html($article['judul']); ?>
                            </a></h2>
                            <p><?php echo escape_html($article['ringkasan']); ?></p>
                            <a class="action-link" href="baca_artikel.php?id=<?php echo (int) $article['id']; ?>">Baca artikel</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
    <script src="assets/js/app.js?v=3"></script>
</body>
</html>
