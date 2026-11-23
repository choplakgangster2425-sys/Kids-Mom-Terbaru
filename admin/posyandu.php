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

$form = [
    'tanggal' => date('Y-m-d', strtotime('+7 days')),
    'tempat' => '',
    'kegiatan' => '',
];
$editingId = null;
$errors = [];
$successMessage = is_string($_SESSION['posyandu_admin_success'] ?? null)
    ? $_SESSION['posyandu_admin_success']
    : null;
unset($_SESSION['posyandu_admin_success']);

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edit'])) {
    $editingId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
    if ($editingId === false || $editingId < 1) {
        http_response_code(404);
        exit('Jadwal Posyandu tidak ditemukan.');
    }

    $editStatement = db()->prepare(
        'SELECT id, tanggal, tempat, kegiatan
         FROM jadwal_posyandu WHERE id = :id LIMIT 1'
    );
    $editStatement->execute(['id' => $editingId]);
    $schedule = $editStatement->fetch();
    if (!$schedule) {
        http_response_code(404);
        exit('Jadwal Posyandu tidak ditemukan.');
    }

    $form = [
        'tanggal' => $schedule['tanggal'],
        'tempat' => $schedule['tempat'],
        'kegiatan' => $schedule['kegiatan'],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $errors[] = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba kembali.';
    } else {
        $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
        $rawScheduleId = is_string($_POST['jadwal_id'] ?? null)
            ? trim($_POST['jadwal_id'])
            : '';

        if ($action === 'delete_schedule') {
            $deleteId = filter_var($rawScheduleId, FILTER_VALIDATE_INT);
            if ($deleteId === false || $deleteId < 1) {
                http_response_code(404);
                exit('Jadwal Posyandu tidak ditemukan.');
            }

            $deleteStatement = db()->prepare(
                'DELETE FROM jadwal_posyandu WHERE id = :id'
            );
            $deleteStatement->execute(['id' => $deleteId]);
            if ($deleteStatement->rowCount() !== 1) {
                http_response_code(404);
                exit('Jadwal Posyandu tidak ditemukan.');
            }

            $_SESSION['posyandu_admin_success'] = 'Jadwal dan pendaftarannya berhasil dihapus.';
            redirect_to('posyandu.php');
        }

        if ($action !== 'save_schedule') {
            http_response_code(400);
            exit('Aksi tidak valid.');
        }

        if ($rawScheduleId !== '') {
            $editingId = filter_var($rawScheduleId, FILTER_VALIDATE_INT);
            if ($editingId === false || $editingId < 1) {
                http_response_code(404);
                exit('Jadwal Posyandu tidak ditemukan.');
            }

            $existsStatement = db()->prepare(
                'SELECT id FROM jadwal_posyandu WHERE id = :id LIMIT 1'
            );
            $existsStatement->execute(['id' => $editingId]);
            if (!$existsStatement->fetch()) {
                http_response_code(404);
                exit('Jadwal Posyandu tidak ditemukan.');
            }
        }

        foreach (array_keys($form) as $field) {
            $form[$field] = trim(
                is_string($_POST[$field] ?? null) ? $_POST[$field] : ''
            );
        }

        $validDate = preg_match('/^\d{4}-\d{2}-\d{2}$/D', $form['tanggal']) === 1;
        if ($validDate) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $form['tanggal']);
            $dateErrors = DateTimeImmutable::getLastErrors();
            $year = $date !== false ? (int) $date->format('Y') : 0;
            $validDate = $date !== false
                && ($dateErrors === false
                    || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0))
                && $date->format('Y-m-d') === $form['tanggal']
                && $year >= 2020
                && $year <= 2100;
        }
        $placeLength = preg_match_all('/./us', $form['tempat']);
        $activityLength = preg_match_all('/./us', $form['kegiatan']);

        if (!$validDate) {
            $errors[] = 'Pilih tanggal kegiatan yang valid (tahun 2020–2100).';
        }
        if ($form['tempat'] === '' || $placeLength === false || $placeLength > 255) {
            $errors[] = 'Tempat wajib diisi dan maksimal 255 karakter.';
        }
        if ($form['kegiatan'] === '' || $activityLength === false || $activityLength > 180) {
            $errors[] = 'Nama kegiatan wajib diisi dan maksimal 180 karakter.';
        }

        if (!$errors) {
            if ($editingId !== null) {
                $updateStatement = db()->prepare(
                    'UPDATE jadwal_posyandu
                     SET tanggal = :tanggal, tempat = :tempat, kegiatan = :kegiatan
                     WHERE id = :id'
                );
                $updateStatement->execute([
                    'tanggal' => $form['tanggal'],
                    'tempat' => $form['tempat'],
                    'kegiatan' => $form['kegiatan'],
                    'id' => $editingId,
                ]);
                $_SESSION['posyandu_admin_success'] = 'Jadwal Posyandu berhasil diperbarui.';
            } else {
                $insertStatement = db()->prepare(
                    'INSERT INTO jadwal_posyandu (tanggal, tempat, kegiatan, created_by)
                     VALUES (:tanggal, :tempat, :kegiatan, :created_by)'
                );
                $insertStatement->execute([
                    'tanggal' => $form['tanggal'],
                    'tempat' => $form['tempat'],
                    'kegiatan' => $form['kegiatan'],
                    'created_by' => $_SESSION['user_id'],
                ]);
                $_SESSION['posyandu_admin_success'] = 'Jadwal Posyandu berhasil ditambahkan.';
            }

            redirect_to('posyandu.php');
        }
    }
}

$schedules = db()->query(
    'SELECT j.id, j.tanggal, j.tempat, j.kegiatan, COUNT(p.id) AS jumlah_pendaftar
     FROM jadwal_posyandu j
     LEFT JOIN pendaftaran_posyandu p ON p.jadwal_id = j.id
     GROUP BY j.id
     ORDER BY j.tanggal DESC, j.id DESC'
)->fetchAll();

$participants = [];
$viewSchedule = null;
if (isset($_GET['lihat'])) {
    $viewScheduleId = filter_var($_GET['lihat'], FILTER_VALIDATE_INT);
    if ($viewScheduleId === false || $viewScheduleId < 1) {
        http_response_code(404);
        exit('Jadwal Posyandu tidak ditemukan.');
    }

    $viewStatement = db()->prepare(
        'SELECT id, tanggal, tempat, kegiatan
         FROM jadwal_posyandu WHERE id = :id LIMIT 1'
    );
    $viewStatement->execute(['id' => $viewScheduleId]);
    $viewSchedule = $viewStatement->fetch();
    if (!$viewSchedule) {
        http_response_code(404);
        exit('Jadwal Posyandu tidak ditemukan.');
    }

    $participantStatement = db()->prepare(
        'SELECT u.nama, u.email, p.status, p.created_at
         FROM pendaftaran_posyandu p
         JOIN users u ON u.id = p.user_id
         WHERE p.jadwal_id = :jadwal_id
         ORDER BY p.created_at ASC, p.id ASC'
    );
    $participantStatement->execute(['jadwal_id' => $viewScheduleId]);
    $participants = $participantStatement->fetchAll();
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Posyandu | Mom&amp;Kids+</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=3">
    <link rel="stylesheet" href="../assets/css/auth.css?v=6">
</head>
<body class="auth-page">
    <?php render_admin_sidebar_start('posyandu', $currentUser['nama'], '../'); ?>

    <main class="management-main">
        <div class="management-heading">
            <span class="eyebrow">Admin Posyandu</span>
            <h1>Kelola jadwal Posyandu</h1>
            <p>Buat jadwal kegiatan dan lihat Ibu yang mendaftar.</p>
        </div>

        <?php if ($successMessage !== null): ?>
            <div class="form-success" role="status"><?php echo escape_html($successMessage); ?></div>
        <?php endif; ?>

        <div class="management-layout article-admin-layout">
            <section class="management-card" id="form-jadwal">
                <h2><?php echo $editingId === null ? 'Tambah jadwal' : 'Ubah jadwal'; ?></h2>
                <p class="management-help">Isi tanggal, tempat, dan kegiatan Posyandu.</p>

                <?php if ($errors): ?>
                    <div class="form-errors" role="alert">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo escape_html($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="posyandu.php" class="auth-form">
                    <input type="hidden" name="csrf_token" value="<?php echo escape_html(csrf_token()); ?>">
                    <input type="hidden" name="action" value="save_schedule">
                    <input type="hidden" name="jadwal_id"
                        value="<?php echo $editingId === null ? '' : (int) $editingId; ?>">

                    <label for="tanggal">Tanggal</label>
                    <input id="tanggal" name="tanggal" type="date"
                        min="2020-01-01" max="2100-12-31" required
                        value="<?php echo escape_html($form['tanggal']); ?>">

                    <label for="tempat">Tempat</label>
                    <input id="tempat" name="tempat" type="text" maxlength="255" required
                        value="<?php echo escape_html($form['tempat']); ?>">

                    <label for="kegiatan">Kegiatan</label>
                    <input id="kegiatan" name="kegiatan" type="text" maxlength="180" required
                        value="<?php echo escape_html($form['kegiatan']); ?>">

                    <button class="button button-primary auth-submit" type="submit">
                        <?php echo $editingId === null ? 'Simpan jadwal' : 'Perbarui jadwal'; ?>
                    </button>
                    <?php if ($editingId !== null): ?>
                        <a class="cancel-link" href="posyandu.php">Batal mengubah</a>
                    <?php endif; ?>
                </form>
            </section>

            <section class="management-card">
                <div class="management-card-heading">
                    <div>
                        <h2>Semua jadwal</h2>
                        <p class="management-help"><?php echo count($schedules); ?> jadwal</p>
                    </div>
                </div>

                <?php if (!$schedules): ?>
                    <p class="empty-management">Belum ada jadwal Posyandu.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="mothers-table">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Kegiatan</th>
                                    <th>Tempat</th>
                                    <th>Pendaftar</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($schedules as $schedule): ?>
                                    <tr>
                                        <td><?php echo escape_html(date('d-m-Y', strtotime($schedule['tanggal']))); ?></td>
                                        <td><?php echo escape_html($schedule['kegiatan']); ?></td>
                                        <td><?php echo escape_html($schedule['tempat']); ?></td>
                                        <td>
                                            <a class="action-link" href="posyandu.php?lihat=<?php echo (int) $schedule['id']; ?>">
                                                <?php echo (int) $schedule['jumlah_pendaftar']; ?> peserta
                                            </a>
                                        </td>
                                        <td>
                                            <div class="table-actions">
                                                <a class="action-link"
                                                    href="posyandu.php?edit=<?php echo (int) $schedule['id']; ?>#form-jadwal">Ubah</a>
                                                <form method="post" action="posyandu.php"
                                                    data-confirm="Hapus jadwal ini beserta semua pendaftar?">
                                                    <input type="hidden" name="csrf_token" value="<?php echo escape_html(csrf_token()); ?>">
                                                    <input type="hidden" name="action" value="delete_schedule">
                                                    <input type="hidden" name="jadwal_id" value="<?php echo (int) $schedule['id']; ?>">
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

        <?php if ($viewSchedule): ?>
            <section class="management-card nutrition-history">
                <div class="management-card-heading">
                    <div>
                        <h2>Peserta: <?php echo escape_html($viewSchedule['kegiatan']); ?></h2>
                        <p class="management-help">
                            <?php echo escape_html(date('d-m-Y', strtotime($viewSchedule['tanggal']))); ?>
                            · <?php echo escape_html($viewSchedule['tempat']); ?>
                        </p>
                    </div>
                </div>
                <?php if (!$participants): ?>
                    <p class="empty-management">Belum ada peserta terdaftar.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="mothers-table">
                            <thead>
                                <tr><th>Nama Ibu</th><th>Email</th><th>Status</th><th>Terdaftar</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($participants as $participant): ?>
                                    <tr>
                                        <td><?php echo escape_html($participant['nama']); ?></td>
                                        <td><?php echo escape_html($participant['email']); ?></td>
                                        <td><?php echo escape_html($participant['status']); ?></td>
                                        <td><?php echo escape_html(date('d-m-Y H:i', strtotime($participant['created_at']))); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
    <?php render_admin_sidebar_end(); ?>
    <script src="../assets/js/app.js?v=6"></script>
</body>
</html>
