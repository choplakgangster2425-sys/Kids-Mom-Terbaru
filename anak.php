<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin_layout.php';
require_login();

$currentUser = current_user();
if (!$currentUser) {
    redirect_to('login.php');
}

$isAdmin = $currentUser['role'] === 'admin';
$userId = (int) $_SESSION['user_id'];
$mothers = $isAdmin
    ? db()->query(
        'SELECT id, nama FROM users WHERE role = \'user\' ORDER BY nama ASC'
    )->fetchAll()
    : [];

$childForm = [
    'nama' => '',
    'tanggal_lahir' => '',
    'jenis_kelamin' => 'L',
    'user_id' => '',
];
$editingId = null;
$editingChild = null;
$errors = [];
$successMessage = is_string($_SESSION['children_success'] ?? null)
    ? $_SESSION['children_success']
    : null;
unset($_SESSION['children_success']);

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edit'])) {
    $editingId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
    if ($editingId === false || $editingId < 1) {
        http_response_code(404);
        exit('Data anak tidak ditemukan.');
    }

    $editSql = 'SELECT a.id, a.user_id, a.nama, a.tanggal_lahir, a.jenis_kelamin, u.nama AS nama_ibu
                FROM anak a JOIN users u ON u.id = a.user_id
                WHERE a.id = :id';
    $editParameters = ['id' => $editingId];
    if (!$isAdmin) {
        $editSql .= ' AND a.user_id = :user_id';
        $editParameters['user_id'] = $userId;
    }

    $editStatement = db()->prepare($editSql);
    $editStatement->execute($editParameters);
    $editingChild = $editStatement->fetch();

    if (!$editingChild) {
        http_response_code(404);
        exit('Data anak tidak ditemukan.');
    }

    $childForm = [
        'nama' => $editingChild['nama'],
        'tanggal_lahir' => is_valid_non_future_date($editingChild['tanggal_lahir'], 2000)
            ? $editingChild['tanggal_lahir']
            : '',
        'jenis_kelamin' => $editingChild['jenis_kelamin'],
        'user_id' => (string) $editingChild['user_id'],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $errors[] = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba kembali.';
    } else {
        $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
        $rawChildId = is_string($_POST['anak_id'] ?? null) ? trim($_POST['anak_id']) : '';

        if ($action === 'delete_child') {
            $childIdToDelete = filter_var($rawChildId, FILTER_VALIDATE_INT);
            if ($childIdToDelete === false || $childIdToDelete < 1) {
                http_response_code(404);
                exit('Data anak tidak ditemukan.');
            }

            $deleteSql = 'DELETE FROM anak WHERE id = :id';
            $deleteParameters = ['id' => $childIdToDelete];
            if (!$isAdmin) {
                $deleteSql .= ' AND user_id = :user_id';
                $deleteParameters['user_id'] = $userId;
            }

            $deleteStatement = db()->prepare($deleteSql);
            $deleteStatement->execute($deleteParameters);
            if ($deleteStatement->rowCount() !== 1) {
                http_response_code(404);
                exit('Data anak tidak ditemukan.');
            }

            $_SESSION['children_success'] = 'Data anak dan riwayat pengukurannya berhasil dihapus.';
            redirect_to('anak.php');
        }

        if ($action !== 'save_child') {
            http_response_code(400);
            exit('Aksi tidak valid.');
        }

        foreach (array_keys($childForm) as $field) {
            $childForm[$field] = trim(
                is_string($_POST[$field] ?? null) ? $_POST[$field] : ''
            );
        }

        if ($rawChildId !== '') {
            $editingId = filter_var($rawChildId, FILTER_VALIDATE_INT);
            if ($editingId === false || $editingId < 1) {
                http_response_code(404);
                exit('Data anak tidak ditemukan.');
            }

            $editSql = 'SELECT a.id, a.user_id, a.nama, a.tanggal_lahir, a.jenis_kelamin, u.nama AS nama_ibu
                        FROM anak a JOIN users u ON u.id = a.user_id
                        WHERE a.id = :id';
            $editParameters = ['id' => $editingId];
            if (!$isAdmin) {
                $editSql .= ' AND a.user_id = :user_id';
                $editParameters['user_id'] = $userId;
            }

            $editStatement = db()->prepare($editSql);
            $editStatement->execute($editParameters);
            $editingChild = $editStatement->fetch();
            if (!$editingChild) {
                http_response_code(404);
                exit('Data anak tidak ditemukan.');
            }
        } elseif ($isAdmin && $childForm['user_id'] !== '') {
            $childForm['user_id'] = (string) filter_var(
                $childForm['user_id'],
                FILTER_VALIDATE_INT
            );
        }

        $nameLength = preg_match_all('/./us', $childForm['nama']);
        if ($childForm['nama'] === '' || $nameLength === false || $nameLength > 120) {
            $errors[] = 'Nama anak wajib diisi dan maksimal 120 karakter.';
        }

        if (!is_valid_non_future_date($childForm['tanggal_lahir'], 2000)) {
            $errors[] = 'Masukkan tanggal lahir yang valid (minimal tahun 2000 dan tidak di masa depan).';
        }

        if (!in_array($childForm['jenis_kelamin'], ['L', 'P'], true)) {
            $errors[] = 'Pilih jenis kelamin yang tersedia.';
        }

        $ownerId = $userId;
        if ($isAdmin && $editingId === null) {
            $ownerId = filter_var($childForm['user_id'], FILTER_VALIDATE_INT);
            if ($ownerId === false || $ownerId < 1) {
                $errors[] = 'Pilih akun Ibu pemilik data anak.';
            } else {
                $ownerStatement = db()->prepare(
                    'SELECT id FROM users WHERE id = :id AND role = :role LIMIT 1'
                );
                $ownerStatement->execute(['id' => $ownerId, 'role' => 'user']);
                if (!$ownerStatement->fetch()) {
                    $errors[] = 'Akun Ibu yang dipilih tidak ditemukan.';
                }
            }
        }

        if (!$errors) {
            if ($editingId !== null) {
                $updateStatement = db()->prepare(
                    'UPDATE anak
                     SET nama = :nama, tanggal_lahir = :tanggal_lahir, jenis_kelamin = :jenis_kelamin
                     WHERE id = :id'
                );
                $updateStatement->execute([
                    'nama' => $childForm['nama'],
                    'tanggal_lahir' => $childForm['tanggal_lahir'],
                    'jenis_kelamin' => $childForm['jenis_kelamin'],
                    'id' => $editingId,
                ]);
                $_SESSION['children_success'] = 'Data anak berhasil diperbarui.';
            } else {
                $insertStatement = db()->prepare(
                    'INSERT INTO anak (user_id, nama, tanggal_lahir, jenis_kelamin)
                     VALUES (:user_id, :nama, :tanggal_lahir, :jenis_kelamin)'
                );
                $insertStatement->execute([
                    'user_id' => $ownerId,
                    'nama' => $childForm['nama'],
                    'tanggal_lahir' => $childForm['tanggal_lahir'],
                    'jenis_kelamin' => $childForm['jenis_kelamin'],
                ]);
                $_SESSION['children_success'] = 'Data anak berhasil ditambahkan.';
            }

            redirect_to('anak.php');
        }
    }
}

$listSql = 'SELECT a.id, a.nama, a.tanggal_lahir, a.jenis_kelamin, u.nama AS nama_ibu
            FROM anak a JOIN users u ON u.id = a.user_id';
$listParameters = [];
if (!$isAdmin) {
    $listSql .= ' WHERE a.user_id = :user_id';
    $listParameters['user_id'] = $userId;
}
$listSql .= ' ORDER BY a.tanggal_lahir DESC, a.nama ASC';
$listStatement = db()->prepare($listSql);
$listStatement->execute($listParameters);
$children = $listStatement->fetchAll();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Anak | Mom&amp;Kids+</title>
    <link rel="stylesheet" href="assets/css/style.css?v=3">
    <link rel="stylesheet" href="assets/css/auth.css?v=6">
</head>
<body class="auth-page">
    <?php if ($isAdmin): ?>
        <?php render_admin_sidebar_start('anak', $currentUser['nama']); ?>
    <?php else: ?>
        <header class="auth-header container">
            <a class="brand" href="index.php">
                <img class="brand-logo" src="assets/img/LOGO%20Mom%26Kids%2B-3.png" alt="Mom&amp;Kids+">
            </a>
            <div class="profile-header-actions">
                <a class="button button-small button-outline page-back" href="index.php">
                    <span aria-hidden="true">←</span> Kembali
                </a>
                <form method="post" action="logout.php">
                    <input type="hidden" name="csrf_token" value="<?php echo escape_html(csrf_token()); ?>">
                    <button class="button button-small button-outline" type="submit">Keluar</button>
                </form>
            </div>
        </header>
    <?php endif; ?>

    <main class="management-main<?php echo $isAdmin ? '' : ' container'; ?>">
        <div class="management-heading">
            <span class="eyebrow">Buku KIA digital</span>
            <h1>Data anak</h1>
            <p><?php echo $isAdmin
                ? 'Kelola data anak dari seluruh akun Ibu.'
                : 'Kelola profil anak dan buka catatan pengukurannya.'; ?></p>
        </div>

        <div class="management-layout">
            <section class="management-card" id="form-anak">
                <h2><?php echo $editingId === null ? 'Tambah anak' : 'Ubah data anak'; ?></h2>
                <p class="management-help">Lengkapi identitas dasar anak.</p>

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

                <form method="post" action="anak.php" class="auth-form">
                    <input type="hidden" name="csrf_token" value="<?php echo escape_html(csrf_token()); ?>">
                    <input type="hidden" name="action" value="save_child">
                    <input type="hidden" name="anak_id" value="<?php echo $editingId === null ? '' : (int) $editingId; ?>">

                    <?php if ($isAdmin && $editingId === null): ?>
                        <label for="user_id">Ibu pemilik data</label>
                        <select id="user_id" name="user_id" required>
                            <option value="">Pilih akun Ibu</option>
                            <?php foreach ($mothers as $mother): ?>
                                <option value="<?php echo (int) $mother['id']; ?>"
                                    <?php echo $childForm['user_id'] === (string) $mother['id'] ? 'selected' : ''; ?>>
                                    <?php echo escape_html($mother['nama']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$mothers): ?>
                            <p class="management-help">Belum ada akun Ibu untuk dipilih.</p>
                        <?php endif; ?>
                    <?php elseif ($isAdmin && $editingChild): ?>
                        <p class="owner-label">Data milik: <strong><?php echo escape_html($editingChild['nama_ibu']); ?></strong></p>
                    <?php endif; ?>

                    <label for="nama">Nama anak</label>
                    <input id="nama" name="nama" type="text" maxlength="120" required
                        value="<?php echo escape_html($childForm['nama']); ?>">

                    <label for="tanggal_lahir">Tanggal lahir</label>
                    <input id="tanggal_lahir" name="tanggal_lahir" type="date"
                        min="2000-01-01" max="<?php echo date('Y-m-d'); ?>"
                        required value="<?php echo escape_html($childForm['tanggal_lahir']); ?>">

                    <label for="jenis_kelamin">Jenis kelamin</label>
                    <select id="jenis_kelamin" name="jenis_kelamin" required>
                        <option value="L" <?php echo $childForm['jenis_kelamin'] === 'L' ? 'selected' : ''; ?>>Laki-laki</option>
                        <option value="P" <?php echo $childForm['jenis_kelamin'] === 'P' ? 'selected' : ''; ?>>Perempuan</option>
                    </select>

                    <button class="button button-primary auth-submit" type="submit">
                        <?php echo $editingId === null ? 'Simpan data anak' : 'Perbarui data anak'; ?>
                    </button>
                    <?php if ($editingId !== null): ?>
                        <a class="cancel-link" href="anak.php">Batal mengubah</a>
                    <?php endif; ?>
                </form>
            </section>

            <section class="management-card">
                <div class="management-card-heading">
                    <div>
                        <h2>Daftar anak</h2>
                        <p class="management-help"><?php echo count($children); ?> data anak</p>
                    </div>
                </div>

                <?php if (!$children): ?>
                    <p class="empty-management">Belum ada data anak. Tambahkan data pertama melalui formulir.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="mothers-table">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <?php if ($isAdmin): ?><th>Ibu</th><?php endif; ?>
                                    <th>Tanggal lahir</th>
                                    <th>Jenis kelamin</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($children as $child): ?>
                                    <tr>
                                        <td><?php echo escape_html($child['nama']); ?></td>
                                        <?php if ($isAdmin): ?><td><?php echo escape_html($child['nama_ibu']); ?></td><?php endif; ?>
                                        <td><?php echo escape_html(date('d-m-Y', strtotime($child['tanggal_lahir']))); ?></td>
                                        <td><?php echo $child['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan'; ?></td>
                                        <td>
                                            <div class="table-actions">
                                                <a class="action-link" href="anak.php?edit=<?php echo (int) $child['id']; ?>#form-anak">Ubah</a>
                                                <a class="action-link" href="riwayat_ukur.php?anak_id=<?php echo (int) $child['id']; ?>">Pengukuran</a>
                                                <form method="post" action="anak.php"
                                                    data-confirm="Hapus data anak ini? Semua riwayat pengukurannya ikut terhapus.">
                                                    <input type="hidden" name="csrf_token" value="<?php echo escape_html(csrf_token()); ?>">
                                                    <input type="hidden" name="action" value="delete_child">
                                                    <input type="hidden" name="anak_id" value="<?php echo (int) $child['id']; ?>">
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
    <?php if ($isAdmin): ?>
        <?php render_admin_sidebar_end(); ?>
    <?php endif; ?>
    <script src="assets/js/app.js?v=6"></script>
</body>
</html>
