<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/articles.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_login();

$currentUser = current_user();
if (!$currentUser) {
    redirect_to('../login.php');
}

if ($currentUser['role'] !== 'admin') {
    http_response_code(403);
    exit('Halaman ini hanya tersedia untuk Admin.');
}

$form = [
    'judul' => '',
    'ringkasan' => '',
    'konten' => '',
    'status' => 'draft',
];
$articleId = null;
$editingArticle = null;
$errors = [];
$successMessage = is_string($_SESSION['article_success'] ?? null)
    ? $_SESSION['article_success']
    : null;
unset($_SESSION['article_success']);

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edit'])) {
    $articleId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
    if ($articleId === false || $articleId < 1) {
        http_response_code(404);
        exit('Artikel tidak ditemukan.');
    }

    $editStatement = db()->prepare(
        'SELECT id, judul, ringkasan, konten, gambar_path, status
         FROM artikel WHERE id = :id LIMIT 1'
    );
    $editStatement->execute(['id' => $articleId]);
    $editingArticle = $editStatement->fetch();
    if (!$editingArticle) {
        http_response_code(404);
        exit('Artikel tidak ditemukan.');
    }

    $form = [
        'judul' => $editingArticle['judul'],
        'ringkasan' => $editingArticle['ringkasan'],
        'konten' => $editingArticle['konten'],
        'status' => $editingArticle['status'],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $errors[] = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba kembali.';
    } else {
        $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
        $rawArticleId = is_string($_POST['artikel_id'] ?? null)
            ? trim($_POST['artikel_id'])
            : '';

        if ($action === 'delete_article') {
            $deleteId = filter_var($rawArticleId, FILTER_VALIDATE_INT);
            if ($deleteId === false || $deleteId < 1) {
                http_response_code(404);
                exit('Artikel tidak ditemukan.');
            }

            $deleteLookup = db()->prepare(
                'SELECT gambar_path FROM artikel WHERE id = :id LIMIT 1'
            );
            $deleteLookup->execute(['id' => $deleteId]);
            $articleToDelete = $deleteLookup->fetch();
            if (!$articleToDelete) {
                http_response_code(404);
                exit('Artikel tidak ditemukan.');
            }

            $deleteStatement = db()->prepare('DELETE FROM artikel WHERE id = :id');
            $deleteStatement->execute(['id' => $deleteId]);
            delete_article_image($articleToDelete['gambar_path']);
            $_SESSION['article_success'] = 'Artikel berhasil dihapus.';
            redirect_to('artikel.php');
        }

        if ($action !== 'save_article') {
            http_response_code(400);
            exit('Aksi tidak valid.');
        }

        if ($rawArticleId !== '') {
            $articleId = filter_var($rawArticleId, FILTER_VALIDATE_INT);
            if ($articleId === false || $articleId < 1) {
                http_response_code(404);
                exit('Artikel tidak ditemukan.');
            }

            $editStatement = db()->prepare(
                'SELECT id, judul, ringkasan, konten, gambar_path, status
                 FROM artikel WHERE id = :id LIMIT 1'
            );
            $editStatement->execute(['id' => $articleId]);
            $editingArticle = $editStatement->fetch();
            if (!$editingArticle) {
                http_response_code(404);
                exit('Artikel tidak ditemukan.');
            }
        }

        foreach (array_keys($form) as $field) {
            $form[$field] = trim(
                is_string($_POST[$field] ?? null) ? $_POST[$field] : ''
            );
        }

        $titleLength = preg_match_all('/./us', $form['judul']);
        $summaryLength = preg_match_all('/./us', $form['ringkasan']);
        if ($form['judul'] === '' || $titleLength === false || $titleLength > 180) {
            $errors[] = 'Judul wajib diisi dan maksimal 180 karakter.';
        }
        if ($form['ringkasan'] === '' || $summaryLength === false || $summaryLength > 500) {
            $errors[] = 'Ringkasan wajib diisi dan maksimal 500 karakter.';
        }
        if ($form['konten'] === '') {
            $errors[] = 'Konten artikel wajib diisi.';
        }
        if (!in_array($form['status'], ['draft', 'published'], true)) {
            $errors[] = 'Pilih status artikel yang tersedia.';
        }

        $uploadedImage = null;
        if (!$errors && isset($_FILES['gambar']) && $_FILES['gambar']['error'] !== UPLOAD_ERR_NO_FILE) {
            try {
                $uploadedImage = upload_article_image($_FILES['gambar']);
            } catch (InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        if (!$errors) {
            $removeCurrentImage = isset($_POST['hapus_gambar'])
                && $_POST['hapus_gambar'] === '1';
            $currentImage = $editingArticle['gambar_path'] ?? null;
            $imagePath = $uploadedImage
                ?? ($removeCurrentImage ? null : $currentImage);
            $slug = article_slug($form['judul']);

            try {
                if ($articleId !== null) {
                    $updateStatement = db()->prepare(
                        'UPDATE artikel
                         SET judul = :judul, slug = :slug, ringkasan = :ringkasan,
                             konten = :konten, gambar_path = :gambar_path, status = :status
                         WHERE id = :id'
                    );
                    $updateStatement->execute([
                        'judul' => $form['judul'],
                        'slug' => $slug,
                        'ringkasan' => $form['ringkasan'],
                        'konten' => $form['konten'],
                        'gambar_path' => $imagePath,
                        'status' => $form['status'],
                        'id' => $articleId,
                    ]);
                    $_SESSION['article_success'] = 'Artikel berhasil diperbarui.';
                } else {
                    $insertStatement = db()->prepare(
                        'INSERT INTO artikel
                         (judul, slug, ringkasan, konten, gambar_path, status, created_by)
                         VALUES (:judul, :slug, :ringkasan, :konten, :gambar_path, :status, :created_by)'
                    );
                    $insertStatement->execute([
                        'judul' => $form['judul'],
                        'slug' => $slug,
                        'ringkasan' => $form['ringkasan'],
                        'konten' => $form['konten'],
                        'gambar_path' => $imagePath,
                        'status' => $form['status'],
                        'created_by' => $_SESSION['user_id'],
                    ]);
                    $_SESSION['article_success'] = 'Artikel berhasil ditambahkan.';
                }
            } catch (Throwable $exception) {
                if ($uploadedImage !== null) {
                    delete_article_image($uploadedImage);
                }
                throw $exception;
            }

            if ($currentImage !== null && $currentImage !== $imagePath) {
                delete_article_image($currentImage);
            }

            redirect_to('artikel.php');
        }
    }
}

$articles = db()->query(
    'SELECT id, judul, status, created_at, updated_at
     FROM artikel
     ORDER BY updated_at DESC, id DESC'
)->fetchAll();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Artikel | Mom&amp;Kids+</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=3">
    <link rel="stylesheet" href="../assets/css/auth.css?v=6">
</head>
<body class="auth-page">
    <?php render_admin_sidebar_start('artikel', $currentUser['nama'], '../'); ?>

    <main class="management-main">
        <div class="management-heading">
            <span class="eyebrow">Konten edukasi</span>
            <h1>Kelola artikel</h1>
            <p>Artikel berstatus terbit dapat dibaca dan dicari oleh pengguna.</p>
        </div>

        <div class="management-layout article-admin-layout">
            <section class="management-card" id="form-artikel">
                <h2><?php echo $articleId === null ? 'Tambah artikel' : 'Ubah artikel'; ?></h2>
                <p class="management-help">Konten ditampilkan sebagai teks biasa.</p>

                <?php if ($successMessage !== null): ?>
                    <div class="form-success" role="status"><?php echo escape_html($successMessage); ?></div>
                <?php endif; ?>

                <?php if ($errors): ?>
                    <div class="form-errors" role="alert">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo escape_html($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="artikel.php" enctype="multipart/form-data" class="auth-form">
                    <input type="hidden" name="csrf_token" value="<?php echo escape_html(csrf_token()); ?>">
                    <input type="hidden" name="action" value="save_article">
                    <input type="hidden" name="artikel_id" value="<?php echo $articleId === null ? '' : (int) $articleId; ?>">

                    <label for="judul">Judul</label>
                    <input id="judul" name="judul" type="text" maxlength="180" required
                        value="<?php echo escape_html($form['judul']); ?>">

                    <label for="ringkasan">Ringkasan</label>
                    <textarea id="ringkasan" name="ringkasan" rows="3" maxlength="500" required><?php
                        echo escape_html($form['ringkasan']);
                    ?></textarea>

                    <label for="konten">Konten</label>
                    <textarea id="konten" name="konten" rows="9" required><?php
                        echo escape_html($form['konten']);
                    ?></textarea>

                    <label for="gambar">Gambar (JPG, PNG, WebP; maksimal 5 MB)</label>
                    <input id="gambar" name="gambar" type="file" accept="image/jpeg,image/png,image/webp">

                    <?php if ($editingArticle && $editingArticle['gambar_path'] !== null): ?>
                        <img class="admin-image-preview"
                            src="../assets/img/<?php echo escape_html($editingArticle['gambar_path']); ?>"
                            alt="Gambar artikel saat ini">
                        <label class="checkbox-label">
                            <input type="checkbox" name="hapus_gambar" value="1">
                            Hapus gambar saat ini
                        </label>
                    <?php endif; ?>

                    <label for="status">Status</label>
                    <select id="status" name="status" required>
                        <option value="draft" <?php echo $form['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="published" <?php echo $form['status'] === 'published' ? 'selected' : ''; ?>>Terbitkan</option>
                    </select>

                    <button class="button button-primary auth-submit" type="submit">
                        <?php echo $articleId === null ? 'Simpan artikel' : 'Perbarui artikel'; ?>
                    </button>
                    <?php if ($articleId !== null): ?>
                        <a class="cancel-link" href="artikel.php">Batal mengubah</a>
                    <?php endif; ?>
                </form>
            </section>

            <section class="management-card">
                <div class="management-card-heading">
                    <div>
                        <h2>Semua artikel</h2>
                        <p class="management-help"><?php echo count($articles); ?> artikel</p>
                    </div>
                </div>

                <?php if (!$articles): ?>
                    <p class="empty-management">Belum ada artikel.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="mothers-table">
                            <thead>
                                <tr>
                                    <th>Judul</th>
                                    <th>Status</th>
                                    <th>Diperbarui</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($articles as $article): ?>
                                    <tr>
                                        <td><?php echo escape_html($article['judul']); ?></td>
                                        <td><?php echo $article['status'] === 'published' ? 'Terbit' : 'Draft'; ?></td>
                                        <td><?php echo escape_html(date('d-m-Y', strtotime($article['updated_at']))); ?></td>
                                        <td>
                                            <div class="table-actions">
                                                <a class="action-link" href="artikel.php?edit=<?php echo (int) $article['id']; ?>#form-artikel">Ubah</a>
                                                <form method="post" action="artikel.php"
                                                    data-confirm="Hapus artikel ini?">
                                                    <input type="hidden" name="csrf_token" value="<?php echo escape_html(csrf_token()); ?>">
                                                    <input type="hidden" name="action" value="delete_article">
                                                    <input type="hidden" name="artikel_id" value="<?php echo (int) $article['id']; ?>">
                                                    <button class="action-link action-danger" type="submit">Hapus</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
    <?php render_admin_sidebar_end(); ?>
    <script src="../assets/js/app.js?v=6"></script>
</body>
</html>
