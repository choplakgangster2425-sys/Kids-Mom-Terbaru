<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin_layout.php';
require_login();

$userId = (int) $_SESSION['user_id'];
$currentUser = current_user();
if (!$currentUser) {
    redirect_to('login.php');
}

$isAdmin = $currentUser['role'] === 'admin';
$rawChildId = $_GET['anak_id'] ?? $_POST['anak_id'] ?? null;
$childId = filter_var($rawChildId, FILTER_VALIDATE_INT);

if ($childId === false || $childId < 1) {
    http_response_code(404);
    exit('Data anak tidak ditemukan.');
}

$childStatement = db()->prepare(
    'SELECT a.id, a.user_id, a.nama, a.tanggal_lahir, u.nama AS nama_ibu
     FROM anak a
     JOIN users u ON u.id = a.user_id
     WHERE a.id = :id
     LIMIT 1'
);
$childStatement->execute(['id' => $childId]);
$child = $childStatement->fetch();

if (!$child || (!$isAdmin && (int) $child['user_id'] !== $userId)) {
    http_response_code(404);
    exit('Data anak tidak ditemukan.');
}

$measurementForm = [
    'tanggal_ukur' => date('Y-m-d'),
    'berat_kg' => '',
    'tinggi_cm' => '',
    'catatan' => '',
];
$editingMeasurementId = null;
$errors = [];
$successMessage = is_string($_SESSION['measurement_success'] ?? null)
    ? $_SESSION['measurement_success']
    : null;
unset($_SESSION['measurement_success']);

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edit'])) {
    $editingMeasurementId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
    if ($editingMeasurementId === false || $editingMeasurementId < 1) {
        http_response_code(404);
        exit('Riwayat pengukuran tidak ditemukan.');
    }

    $editStatement = db()->prepare(
        'SELECT id, tanggal_ukur, berat_kg, tinggi_cm, catatan
         FROM riwayat_ukur
         WHERE id = :id AND anak_id = :anak_id
         LIMIT 1'
    );
    $editStatement->execute([
        'id' => $editingMeasurementId,
        'anak_id' => $childId,
    ]);
    $measurement = $editStatement->fetch();

    if (!$measurement) {
        http_response_code(404);
        exit('Riwayat pengukuran tidak ditemukan.');
    }

    $measurementForm = [
        'tanggal_ukur' => $measurement['tanggal_ukur'],
        'berat_kg' => (string) $measurement['berat_kg'],
        'tinggi_cm' => (string) $measurement['tinggi_cm'],
        'catatan' => $measurement['catatan'] ?? '',
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $errors[] = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba kembali.';
    } else {
        $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
        $rawMeasurementId = is_string($_POST['pengukuran_id'] ?? null)
            ? trim($_POST['pengukuran_id'])
            : '';

        if ($action === 'delete_measurement') {
            $measurementId = filter_var($rawMeasurementId, FILTER_VALIDATE_INT);
            if ($measurementId === false || $measurementId < 1) {
                http_response_code(404);
                exit('Riwayat pengukuran tidak ditemukan.');
            }

            $deleteStatement = db()->prepare(
                'DELETE FROM riwayat_ukur WHERE id = :id AND anak_id = :anak_id'
            );
            $deleteStatement->execute([
                'id' => $measurementId,
                'anak_id' => $childId,
            ]);
            if ($deleteStatement->rowCount() !== 1) {
                http_response_code(404);
                exit('Riwayat pengukuran tidak ditemukan.');
            }

            $_SESSION['measurement_success'] = 'Riwayat pengukuran berhasil dihapus.';
            redirect_to('riwayat_ukur.php?anak_id=' . $childId);
        }

        if ($action !== 'save_measurement') {
            http_response_code(400);
            exit('Aksi tidak valid.');
        }

        $editingMeasurementId = null;
        if ($rawMeasurementId !== '') {
            $editingMeasurementId = filter_var($rawMeasurementId, FILTER_VALIDATE_INT);
            if ($editingMeasurementId === false || $editingMeasurementId < 1) {
                http_response_code(404);
                exit('Riwayat pengukuran tidak ditemukan.');
            }

            $existsStatement = db()->prepare(
                'SELECT id FROM riwayat_ukur WHERE id = :id AND anak_id = :anak_id LIMIT 1'
            );
            $existsStatement->execute([
                'id' => $editingMeasurementId,
                'anak_id' => $childId,
            ]);
            if (!$existsStatement->fetch()) {
                http_response_code(404);
                exit('Riwayat pengukuran tidak ditemukan.');
            }
        }

        foreach (array_keys($measurementForm) as $field) {
            $measurementForm[$field] = trim(
                is_string($_POST[$field] ?? null) ? $_POST[$field] : ''
            );
        }

        if (!is_valid_non_future_date($measurementForm['tanggal_ukur'], 2000)) {
            $errors[] = 'Masukkan tanggal pengukuran yang valid (minimal tahun 2000 dan tidak di masa depan).';
        } elseif (
            is_valid_non_future_date($child['tanggal_lahir'], 2000)
            && $measurementForm['tanggal_ukur'] < $child['tanggal_lahir']
        ) {
            $errors[] = 'Tanggal pengukuran tidak boleh sebelum tanggal lahir anak.';
        }

        $parseMeasurementValue = static function (string $value): ?float {
            $normalized = str_replace(',', '.', $value);
            if (!preg_match('/^\d{1,3}(?:\.\d{1,2})?$/D', $normalized)) {
                return null;
            }

            $number = (float) $normalized;
            return $number > 0 && $number <= 999.99 ? $number : null;
        };

        $weight = $parseMeasurementValue($measurementForm['berat_kg']);
        if ($weight === null) {
            $errors[] = 'Berat badan harus berupa angka lebih dari 0 dan maksimal 999,99 kg.';
        }

        $height = $parseMeasurementValue($measurementForm['tinggi_cm']);
        if ($height === null) {
            $errors[] = 'Tinggi badan harus berupa angka lebih dari 0 dan maksimal 999,99 cm.';
        }

        $noteLength = preg_match_all('/./us', $measurementForm['catatan']);
        if ($noteLength === false || $noteLength > 500) {
            $errors[] = 'Catatan maksimal 500 karakter.';
        }

        if (!$errors) {
            if ($editingMeasurementId !== null) {
                $updateStatement = db()->prepare(
                    'UPDATE riwayat_ukur
                     SET tanggal_ukur = :tanggal_ukur,
                         berat_kg = :berat_kg,
                         tinggi_cm = :tinggi_cm,
                         catatan = :catatan
                     WHERE id = :id AND anak_id = :anak_id'
                );
                $updateStatement->execute([
                    'tanggal_ukur' => $measurementForm['tanggal_ukur'],
                    'berat_kg' => $weight,
                    'tinggi_cm' => $height,
                    'catatan' => $measurementForm['catatan'] === ''
                        ? null
                        : $measurementForm['catatan'],
                    'id' => $editingMeasurementId,
                    'anak_id' => $childId,
                ]);
                $_SESSION['measurement_success'] = 'Riwayat pengukuran berhasil diperbarui.';
            } else {
                $insertStatement = db()->prepare(
                    'INSERT INTO riwayat_ukur (anak_id, tanggal_ukur, berat_kg, tinggi_cm, catatan)
                     VALUES (:anak_id, :tanggal_ukur, :berat_kg, :tinggi_cm, :catatan)'
                );
                $insertStatement->execute([
                    'anak_id' => $childId,
                    'tanggal_ukur' => $measurementForm['tanggal_ukur'],
                    'berat_kg' => $weight,
                    'tinggi_cm' => $height,
                    'catatan' => $measurementForm['catatan'] === ''
                        ? null
                        : $measurementForm['catatan'],
                ]);
                $_SESSION['measurement_success'] = 'Pengukuran berhasil dicatat.';
            }

            redirect_to('riwayat_ukur.php?anak_id=' . $childId);
        }
    }
}

$historyStatement = db()->prepare(
    'SELECT id, tanggal_ukur, berat_kg, tinggi_cm, catatan
     FROM riwayat_ukur
     WHERE anak_id = :anak_id
     ORDER BY tanggal_ukur DESC, id DESC'
);
$historyStatement->execute(['anak_id' => $childId]);
$measurements = $historyStatement->fetchAll();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pengukuran | Mom&amp;Kids+</title>
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
                <a class="button button-small button-outline page-back" href="anak.php">
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
            <h1>Pengukuran <?php echo escape_html($child['nama']); ?></h1>
            <p>
                <?php echo $isAdmin
                    ? 'Data milik ' . escape_html($child['nama_ibu']) . '. '
                    : ''; ?>
                Catat berat dan tinggi badan secara berkala.
            </p>
        </div>

        <div class="management-layout">
            <section class="management-card">
                <h2><?php echo $editingMeasurementId === null
                    ? 'Catat pengukuran'
                    : 'Ubah pengukuran'; ?></h2>
                <p class="management-help">Gunakan hasil pengukuran aktual.</p>

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

                <form method="post" action="riwayat_ukur.php?anak_id=<?php echo (int) $childId; ?>" class="auth-form">
                    <input type="hidden" name="csrf_token" value="<?php echo escape_html(csrf_token()); ?>">
                    <input type="hidden" name="action" value="save_measurement">
                    <input type="hidden" name="anak_id" value="<?php echo (int) $childId; ?>">
                    <input type="hidden" name="pengukuran_id"
                        value="<?php echo $editingMeasurementId === null ? '' : (int) $editingMeasurementId; ?>">

                    <?php $minMeasureDate = is_valid_non_future_date($child['tanggal_lahir'], 2000)
                        ? $child['tanggal_lahir']
                        : '2000-01-01'; ?>
                    <label for="tanggal_ukur">Tanggal ukur</label>
                    <input id="tanggal_ukur" name="tanggal_ukur" type="date"
                        min="<?php echo escape_html($minMeasureDate); ?>" max="<?php echo date('Y-m-d'); ?>"
                        required value="<?php echo escape_html($measurementForm['tanggal_ukur']); ?>">

                    <label for="berat_kg">Berat badan (kg)</label>
                    <input id="berat_kg" name="berat_kg" type="number" min="0.01" max="999.99"
                        step="0.01" inputmode="decimal" required
                        value="<?php echo escape_html($measurementForm['berat_kg']); ?>">

                    <label for="tinggi_cm">Tinggi badan (cm)</label>
                    <input id="tinggi_cm" name="tinggi_cm" type="number" min="0.01" max="999.99"
                        step="0.01" inputmode="decimal" required
                        value="<?php echo escape_html($measurementForm['tinggi_cm']); ?>">

                    <label for="catatan">Catatan (opsional)</label>
                    <textarea id="catatan" name="catatan" rows="3" maxlength="500"><?php
                        echo escape_html($measurementForm['catatan']);
                    ?></textarea>

                    <button class="button button-primary auth-submit" type="submit">
                        <?php echo $editingMeasurementId === null ? 'Simpan pengukuran' : 'Perbarui pengukuran'; ?>
                    </button>
                    <?php if ($editingMeasurementId !== null): ?>
                        <a class="cancel-link" href="riwayat_ukur.php?anak_id=<?php echo (int) $childId; ?>">Batal mengubah</a>
                    <?php endif; ?>
                </form>
            </section>

            <section class="management-card">
                <div class="management-card-heading">
                    <div>
                        <h2>Riwayat pengukuran</h2>
                        <p class="management-help"><?php echo count($measurements); ?> catatan</p>
                    </div>
                </div>

                <?php if (!$measurements): ?>
                    <p class="empty-management">Belum ada pengukuran untuk anak ini.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="mothers-table">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Berat</th>
                                    <th>Tinggi</th>
                                    <th>Catatan</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($measurements as $item): ?>
                                    <tr>
                                        <td><?php echo escape_html(date('d-m-Y', strtotime($item['tanggal_ukur']))); ?></td>
                                        <td><?php echo escape_html((string) $item['berat_kg']); ?> kg</td>
                                        <td><?php echo escape_html((string) $item['tinggi_cm']); ?> cm</td>
                                        <td><?php echo $item['catatan'] === null || $item['catatan'] === ''
                                            ? '-'
                                            : escape_html($item['catatan']); ?></td>
                                        <td>
                                            <div class="table-actions">
                                                <a class="action-link" href="riwayat_ukur.php?anak_id=<?php echo (int) $childId; ?>&amp;edit=<?php echo (int) $item['id']; ?>">Ubah</a>
                                                <form method="post"
                                                    action="riwayat_ukur.php?anak_id=<?php echo (int) $childId; ?>"
                                                    data-confirm="Hapus catatan pengukuran ini?">
                                                    <input type="hidden" name="csrf_token" value="<?php echo escape_html(csrf_token()); ?>">
                                                    <input type="hidden" name="action" value="delete_measurement">
                                                    <input type="hidden" name="anak_id" value="<?php echo (int) $childId; ?>">
                                                    <input type="hidden" name="pengukuran_id" value="<?php echo (int) $item['id']; ?>">
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
