<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_login();

$userId = (int) $_SESSION['user_id'];
$currentUser = current_user();
if (!$currentUser) {
    redirect_to('../login.php');
}

if ($currentUser['role'] !== 'admin') {
    http_response_code(403);
    exit('Halaman ini hanya tersedia untuk Admin/Bidan.');
}

$errorsById = [];
$submittedAnswers = [];
$submittedStatuses = [];
$successMessage = is_string($_SESSION['consultation_admin_success'] ?? null)
    ? $_SESSION['consultation_admin_success']
    : null;
unset($_SESSION['consultation_admin_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $errorsById[0] = ['Sesi formulir tidak valid. Muat ulang halaman lalu coba kembali.'];
    } else {
        $consultationId = filter_var(
            is_string($_POST['konsultasi_id'] ?? null)
                ? $_POST['konsultasi_id']
                : null,
            FILTER_VALIDATE_INT
        );
        $answer = trim(
            is_string($_POST['jawaban'] ?? null) ? $_POST['jawaban'] : ''
        );
        $status = is_string($_POST['status'] ?? null) ? $_POST['status'] : '';
        $answerLength = preg_match_all('/./us', $answer);

        if ($consultationId === false || $consultationId < 1) {
            http_response_code(404);
            exit('Pertanyaan tidak ditemukan.');
        }

        $submittedAnswers[$consultationId] = $answer;
        $submittedStatuses[$consultationId] = $status;
        $errors = [];

        $existsStatement = db()->prepare(
            'SELECT id FROM konsultasi WHERE id = :id LIMIT 1'
        );
        $existsStatement->execute(['id' => $consultationId]);
        if (!$existsStatement->fetch()) {
            http_response_code(404);
            exit('Pertanyaan tidak ditemukan.');
        }

        if (!in_array($status, ['Menunggu', 'Dijawab'], true)) {
            $errors[] = 'Pilih status konsultasi yang tersedia.';
        }
        if ($answerLength === false || $answerLength > 10000) {
            $errors[] = 'Balasan maksimal 10.000 karakter.';
        }
        if ($status === 'Dijawab' && $answer === '') {
            $errors[] = 'Isi balasan sebelum mengubah status menjadi Dijawab.';
        }

        if ($errors) {
            $errorsById[$consultationId] = $errors;
        } else {
            $updateStatement = db()->prepare(
                'UPDATE konsultasi
                 SET jawaban = :jawaban, status = :status, answered_by = :answered_by
                 WHERE id = :id'
            );
            $updateStatement->execute([
                'jawaban' => $answer === '' ? null : $answer,
                'status' => $status,
                'answered_by' => $status === 'Dijawab' ? $userId : null,
                'id' => $consultationId,
            ]);
            $_SESSION['consultation_admin_success'] = 'Balasan konsultasi berhasil disimpan.';
            redirect_to('konsultasi.php');
        }
    }
}

$consultations = db()->query(
    'SELECT k.id, k.pertanyaan, k.jawaban, k.status, k.created_at, k.updated_at,
            u.nama, u.email
     FROM konsultasi k
     JOIN users u ON u.id = k.user_id
     ORDER BY CASE WHEN k.status = \'Menunggu\' THEN 0 ELSE 1 END,
              k.updated_at DESC, k.id DESC'
)->fetchAll();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Konsultasi | Mom&amp;Kids+</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=3">
    <link rel="stylesheet" href="../assets/css/auth.css?v=6">
</head>
<body class="auth-page">
    <?php render_admin_sidebar_start('konsultasi', $currentUser['nama'], '../'); ?>

    <main class="management-main">
        <div class="management-heading">
            <span class="eyebrow">Layanan tanya jawab</span>
            <h1>Konsultasi Ibu</h1>
            <p>Baca pertanyaan, tulis balasan, dan perbarui status konsultasi.</p>
        </div>

        <?php if ($successMessage !== null): ?>
            <div class="form-success" role="status"><?php echo escape_html($successMessage); ?></div>
        <?php endif; ?>

        <?php if (isset($errorsById[0])): ?>
            <div class="form-errors" role="alert">
                <?php foreach ($errorsById[0] as $error): ?>
                    <p><?php echo escape_html($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!$consultations): ?>
            <p class="empty-management">Belum ada pertanyaan dari Ibu.</p>
        <?php else: ?>
            <div class="consultation-list">
                <?php foreach ($consultations as $consultation): ?>
                    <article class="management-card consultation-item">
                        <div class="management-card-heading">
                            <div>
                                <h2><?php echo escape_html($consultation['nama']); ?></h2>
                                <p class="management-help">
                                    <?php echo escape_html($consultation['email']); ?>
                                    · <?php echo escape_html(date('d-m-Y H:i', strtotime($consultation['created_at']))); ?>
                                </p>
                            </div>
                            <span class="status-badge status-<?php echo $consultation['status'] === 'Dijawab' ? 'answered' : 'waiting'; ?>">
                                <?php echo escape_html($consultation['status']); ?>
                            </span>
                        </div>

                        <h3>Pertanyaan</h3>
                        <div class="article-content"><?php echo nl2br(escape_html($consultation['pertanyaan'])); ?></div>

                        <?php if (isset($errorsById[$consultation['id']])): ?>
                            <div class="form-errors" role="alert">
                                <?php foreach ($errorsById[$consultation['id']] as $error): ?>
                                    <p><?php echo escape_html($error); ?></p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="konsultasi.php" class="auth-form">
                            <input type="hidden" name="csrf_token" value="<?php echo escape_html(csrf_token()); ?>">
                            <input type="hidden" name="konsultasi_id" value="<?php echo (int) $consultation['id']; ?>">
                            <label for="jawaban-<?php echo (int) $consultation['id']; ?>">Balasan</label>
                            <textarea id="jawaban-<?php echo (int) $consultation['id']; ?>"
                                name="jawaban" rows="4" maxlength="10000"><?php
                                echo escape_html(
                                    $submittedAnswers[$consultation['id']]
                                        ?? $consultation['jawaban']
                                        ?? ''
                                );
                            ?></textarea>
                            <?php $selectedStatus = $submittedStatuses[$consultation['id']] ?? $consultation['status']; ?>
                            <label for="status-<?php echo (int) $consultation['id']; ?>">Status</label>
                            <select id="status-<?php echo (int) $consultation['id']; ?>" name="status" required>
                                <option value="Menunggu" <?php echo $selectedStatus === 'Menunggu' ? 'selected' : ''; ?>>Menunggu</option>
                                <option value="Dijawab" <?php echo $selectedStatus === 'Dijawab' ? 'selected' : ''; ?>>Dijawab</option>
                            </select>
                            <button class="button button-primary button-small" type="submit">Simpan balasan</button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
    <?php render_admin_sidebar_end(); ?>
    <script src="../assets/js/app.js?v=6"></script>
</body>
</html>
