<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
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

$categories = ['Ibu Hamil', '6-8 Bulan', '9-12 Bulan'];
$form = [
    'judul' => '',
    'ringkasan' => '',
    'kategori' => 'Ibu Hamil',
    'bahan' => '',
    'langkah' => '',
    'status' => 'draft',
];
$recipeId = null;
$errors = [];
$successMessage = is_string($_SESSION['recipe_success'] ?? null)
    ? $_SESSION['recipe_success']
    : null;
unset($_SESSION['recipe_success']);

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edit'])) {
    $recipeId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
    if ($recipeId === false || $recipeId < 1) {
        http_response_code(404);
        exit('Resep tidak ditemukan.');
    }

    $editStatement = db()->prepare(
        'SELECT id, judul, ringkasan, kategori, bahan, langkah, status
         FROM resep WHERE id = :id LIMIT 1'
    );
    $editStatement->execute(['id' => $recipeId]);
    $recipe = $editStatement->fetch();
    if (!$recipe) {
        http_response_code(404);
        exit('Resep tidak ditemukan.');
    }

    $form = [
        'judul' => $recipe['judul'],
        'ringkasan' => $recipe['ringkasan'],
        'kategori' => $recipe['kategori'],
        'bahan' => $recipe['bahan'],
        'langkah' => $recipe['langkah'],
        'status' => $recipe['status'],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $errors[] = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba kembali.';
    } else {
        $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
        $rawRecipeId = is_string($_POST['resep_id'] ?? null)
            ? trim($_POST['resep_id'])
            : '';

        if ($action === 'delete_recipe') {
            $deleteId = filter_var($rawRecipeId, FILTER_VALIDATE_INT);
            if ($deleteId === false || $deleteId < 1) {
                http_response_code(404);
                exit('Resep tidak ditemukan.');
            }

            $deleteStatement = db()->prepare('DELETE FROM resep WHERE id = :id');
            $deleteStatement->execute(['id' => $deleteId]);
            if ($deleteStatement->rowCount() !== 1) {
                http_response_code(404);
                exit('Resep tidak ditemukan.');
            }

            $_SESSION['recipe_success'] = 'Resep berhasil dihapus.';
            redirect_to('resep.php');
        }

        if ($action !== 'save_recipe') {
            http_response_code(400);
            exit('Aksi tidak valid.');
        }

        if ($rawRecipeId !== '') {
            $recipeId = filter_var($rawRecipeId, FILTER_VALIDATE_INT);
            if ($recipeId === false || $recipeId < 1) {
                http_response_code(404);
                exit('Resep tidak ditemukan.');
            }

            $existsStatement = db()->prepare('SELECT id FROM resep WHERE id = :id LIMIT 1');
            $existsStatement->execute(['id' => $recipeId]);
            if (!$existsStatement->fetch()) {
                http_response_code(404);
                exit('Resep tidak ditemukan.');
            }
        }

        foreach (array_keys($form) as $field) {
            $form[$field] = trim(
                is_string($_POST[$field] ?? null) ? $_POST[$field] : ''
            );
        }

        $titleLength = preg_match_all('/./us', $form['judul']);
        $summaryLength = preg_match_all('/./us', $form['ringkasan']);
        $ingredientsLength = preg_match_all('/./us', $form['bahan']);
        $stepsLength = preg_match_all('/./us', $form['langkah']);

        if ($form['judul'] === '' || $titleLength === false || $titleLength > 180) {
            $errors[] = 'Judul wajib diisi dan maksimal 180 karakter.';
        }
        if ($form['ringkasan'] === '' || $summaryLength === false || $summaryLength > 500) {
            $errors[] = 'Ringkasan wajib diisi dan maksimal 500 karakter.';
        }
        if ($form['bahan'] === '' || $ingredientsLength === false || $ingredientsLength > 30000) {
            $errors[] = 'Bahan wajib diisi dan maksimal 30.000 karakter.';
        }
        if ($form['langkah'] === '' || $stepsLength === false || $stepsLength > 50000) {
            $errors[] = 'Langkah wajib diisi dan maksimal 50.000 karakter.';
        }
        if (!in_array($form['kategori'], $categories, true)) {
            $errors[] = 'Pilih kategori resep yang tersedia.';
        }
        if (!in_array($form['status'], ['draft', 'published'], true)) {
            $errors[] = 'Pilih status resep yang tersedia.';
        }

        if (!$errors) {
            if ($recipeId !== null) {
                $updateStatement = db()->prepare(
                    'UPDATE resep
                     SET judul = :judul, ringkasan = :ringkasan, kategori = :kategori,
                         bahan = :bahan, langkah = :langkah, status = :status
                     WHERE id = :id'
                );
                $updateStatement->execute([
                    'judul' => $form['judul'],
                    'ringkasan' => $form['ringkasan'],
                    'kategori' => $form['kategori'],
                    'bahan' => $form['bahan'],
                    'langkah' => $form['langkah'],
                    'status' => $form['status'],
                    'id' => $recipeId,
                ]);
                $_SESSION['recipe_success'] = 'Resep berhasil diperbarui.';
            } else {
                $insertStatement = db()->prepare(
                    'INSERT INTO resep
                     (judul, ringkasan, kategori, bahan, langkah, status, created_by)
                     VALUES (:judul, :ringkasan, :kategori, :bahan, :langkah, :status, :created_by)'
                );
                $insertStatement->execute([
                    'judul' => $form['judul'],
                    'ringkasan' => $form['ringkasan'],
                    'kategori' => $form['kategori'],
                    'bahan' => $form['bahan'],
                    'langkah' => $form['langkah'],
                    'status' => $form['status'],
                    'created_by' => $_SESSION['user_id'],
                ]);
                $_SESSION['recipe_success'] = 'Resep berhasil ditambahkan.';
            }

            redirect_to('resep.php');
        }
    }
}

$recipes = db()->query(
    'SELECT id, judul, kategori, status, updated_at
     FROM resep
     ORDER BY updated_at DESC, id DESC'
)->fetchAll();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Resep | Mom&amp;Kids+</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=3">
    <link rel="stylesheet" href="../assets/css/auth.css?v=6">
</head>
<body class="auth-page">
    <?php render_admin_sidebar_start('resep', $currentUser['nama'], '../'); ?>

    <main class="management-main">
        <div class="management-heading">
            <span class="eyebrow">Konten MPASI</span>
            <h1>Kelola resep</h1>
            <p>Atur resep dan kategori yang tersedia untuk pengguna.</p>
        </div>

        <div class="management-layout article-admin-layout">
            <section class="management-card" id="form-resep">
                <h2><?php echo $recipeId === null ? 'Tambah resep' : 'Ubah resep'; ?></h2>
                <p class="management-help">Resep terbit dapat dilihat berdasarkan kategori.</p>

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

                <form method="post" action="resep.php" class="auth-form">
                    <input type="hidden" name="csrf_token" value="<?php echo escape_html(csrf_token()); ?>">
                    <input type="hidden" name="action" value="save_recipe">
                    <input type="hidden" name="resep_id" value="<?php echo $recipeId === null ? '' : (int) $recipeId; ?>">

                    <label for="judul">Nama resep</label>
                    <input id="judul" name="judul" type="text" maxlength="180" required
                        value="<?php echo escape_html($form['judul']); ?>">

                    <label for="ringkasan">Ringkasan</label>
                    <textarea id="ringkasan" name="ringkasan" rows="3" maxlength="500" required><?php
                        echo escape_html($form['ringkasan']);
                    ?></textarea>

                    <label for="kategori">Kategori usia</label>
                    <select id="kategori" name="kategori" required>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo escape_html($category); ?>"
                                <?php echo $form['kategori'] === $category ? 'selected' : ''; ?>>
                                <?php echo escape_html($category); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="bahan">Bahan</label>
                    <textarea id="bahan" name="bahan" rows="5" required><?php
                        echo escape_html($form['bahan']);
                    ?></textarea>

                    <label for="langkah">Langkah pembuatan</label>
                    <textarea id="langkah" name="langkah" rows="7" required><?php
                        echo escape_html($form['langkah']);
                    ?></textarea>

                    <label for="status">Status</label>
                    <select id="status" name="status" required>
                        <option value="draft" <?php echo $form['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="published" <?php echo $form['status'] === 'published' ? 'selected' : ''; ?>>Terbitkan</option>
                    </select>

                    <button class="button button-primary auth-submit" type="submit">
                        <?php echo $recipeId === null ? 'Simpan resep' : 'Perbarui resep'; ?>
                    </button>
                    <?php if ($recipeId !== null): ?>
                        <a class="cancel-link" href="resep.php">Batal mengubah</a>
                    <?php endif; ?>
                </form>
            </section>

            <section class="management-card">
                <div class="management-card-heading">
                    <div>
                        <h2>Semua resep</h2>
                        <p class="management-help"><?php echo count($recipes); ?> resep</p>
                    </div>
                </div>

                <?php if (!$recipes): ?>
                    <p class="empty-management">Belum ada resep.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="mothers-table">
                            <thead>
                                <tr>
                                    <th>Nama resep</th>
                                    <th>Kategori</th>
                                    <th>Status</th>
                                    <th>Diperbarui</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recipes as $recipe): ?>
                                    <tr>
                                        <td><?php echo escape_html($recipe['judul']); ?></td>
                                        <td><?php echo escape_html($recipe['kategori']); ?></td>
                                        <td><?php echo $recipe['status'] === 'published' ? 'Terbit' : 'Draft'; ?></td>
                                        <td><?php echo escape_html(date('d-m-Y', strtotime($recipe['updated_at']))); ?></td>
                                        <td>
                                            <div class="table-actions">
                                                <a class="action-link" href="resep.php?edit=<?php echo (int) $recipe['id']; ?>#form-resep">Ubah</a>
                                                <form method="post" action="resep.php"
                                                    data-confirm="Hapus resep ini?">
                                                    <input type="hidden" name="csrf_token" value="<?php echo escape_html(csrf_token()); ?>">
                                                    <input type="hidden" name="action" value="delete_recipe">
                                                    <input type="hidden" name="resep_id" value="<?php echo (int) $recipe['id']; ?>">
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
