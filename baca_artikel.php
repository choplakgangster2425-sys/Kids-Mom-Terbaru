<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$currentUser = current_user();
if ($currentUser === null) {
    redirect_to('login.php');
}
$homeUrl = home_url_for_role($currentUser['role']);

$articleId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($articleId === false || $articleId < 1) {
    http_response_code(404);
    exit('Artikel tidak ditemukan.');
}

$statement = db()->prepare(
    'SELECT judul, ringkasan, konten, gambar_path, created_at
     FROM artikel
     WHERE id = :id AND status = :status
     LIMIT 1'
);
$statement->execute(['id' => $articleId, 'status' => 'published']);
$article = $statement->fetch();

if (!$article) {
    http_response_code(404);
    exit('Artikel tidak ditemukan.');
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape_html($article['judul']); ?> | Mom&amp;Kids+</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body class="auth-page">
    <header class="auth-header container">
        <a class="brand" href="<?php echo $homeUrl; ?>">
            <img class="brand-logo" src="assets/img/LOGO%20Mom%26Kids%2B-3.png" alt="Mom&amp;Kids+">
        </a>
        <a class="button button-small button-outline page-back" href="artikel.php">
            <span aria-hidden="true">←</span> Kembali
        </a>
    </header>

    <main class="article-reader container">
        <article>
            <?php if ($article['gambar_path'] !== null): ?>
                <img class="article-cover" src="assets/img/<?php echo escape_html($article['gambar_path']); ?>"
                    alt="" fetchpriority="high">
            <?php endif; ?>
            <span class="article-date"><?php echo escape_html(date('d-m-Y', strtotime($article['created_at']))); ?></span>
            <h1><?php echo escape_html($article['judul']); ?></h1>
            <p class="article-lead"><?php echo escape_html($article['ringkasan']); ?></p>
            <div class="article-content"><?php echo nl2br(escape_html($article['konten'])); ?></div>
        </article>
    </main>
    <script src="assets/js/app.js?v=3"></script>
</body>
</html>
