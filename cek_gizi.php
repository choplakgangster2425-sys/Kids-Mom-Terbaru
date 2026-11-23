<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/who_growth.php';
require_once __DIR__ . '/includes/admin_layout.php';
require_login();

$userId = (int) $_SESSION['user_id'];
$currentUser = current_user();
if (!$currentUser) {
    redirect_to('login.php');
}

$isAdmin = $currentUser['role'] === 'admin';
$childrenSql = 'SELECT a.id, a.nama, a.tanggal_lahir, a.jenis_kelamin, u.nama AS nama_ibu
                FROM anak a JOIN users u ON u.id = a.user_id';
$childrenParameters = [];
if (!$isAdmin) {
    $childrenSql .= ' WHERE a.user_id = :user_id';
    $childrenParameters['user_id'] = $userId;
}
$childrenSql .= ' ORDER BY a.nama ASC';
$childrenStatement = db()->prepare($childrenSql);
$childrenStatement->execute($childrenParameters);
$children = $childrenStatement->fetchAll();

$errors = [];
$selectedChildId = null;
$selectedChild = null;
$selectedMeasurementId = null;
$measurements = [];
$savedResult = null;

$rawChildId = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['anak_id'] ?? null)
    : ($_GET['anak_id'] ?? null);

if ($rawChildId !== null && $rawChildId !== '') {
    $selectedChildId = filter_var($rawChildId, FILTER_VALIDATE_INT);
    if ($selectedChildId === false || $selectedChildId < 1) {
        http_response_code(404);
        exit('Data anak tidak ditemukan.');
    }

    $childSql = 'SELECT a.id, a.user_id, a.nama, a.tanggal_lahir, a.jenis_kelamin, u.nama AS nama_ibu
                 FROM anak a JOIN users u ON u.id = a.user_id
                 WHERE a.id = :id';
    $childParameters = ['id' => $selectedChildId];
    if (!$isAdmin) {
        $childSql .= ' AND a.user_id = :user_id';
        $childParameters['user_id'] = $userId;
    }
    $childSql .= ' LIMIT 1';

    $childStatement = db()->prepare($childSql);
    $childStatement->execute($childParameters);
    $selectedChild = $childStatement->fetch();
    if (!$selectedChild) {
        http_response_code(404);
        exit('Data anak tidak ditemukan.');
    }

    $measurementStatement = db()->prepare(
        'SELECT id, tanggal_ukur, berat_kg, tinggi_cm
         FROM riwayat_ukur
         WHERE anak_id = :anak_id
         ORDER BY tanggal_ukur DESC, id DESC'
    );
    $measurementStatement->execute(['anak_id' => $selectedChildId]);
    $measurements = $measurementStatement->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $errors[] = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba kembali.';
    } elseif (!$selectedChild) {
        http_response_code(404);
        exit('Data anak tidak ditemukan.');
    } else {
        $measurementId = filter_var(
            is_string($_POST['pengukuran_id'] ?? null) ? $_POST['pengukuran_id'] : null,
            FILTER_VALIDATE_INT
        );
        $selectedMeasurementId = ($measurementId !== false && $measurementId >= 1)
            ? $measurementId
            : null;

        if ($measurementId === false || $measurementId < 1) {
            $errors[] = 'Pilih catatan pengukuran yang akan diperiksa.';
        } else {
            $measurementSql = 'SELECT m.id, m.anak_id, m.tanggal_ukur, m.berat_kg, m.tinggi_cm,
                                      a.user_id, a.nama, a.tanggal_lahir, a.jenis_kelamin
                               FROM riwayat_ukur m
                               JOIN anak a ON a.id = m.anak_id
                               WHERE m.id = :measurement_id AND a.id = :anak_id';
            $measurementParameters = [
                'measurement_id' => $measurementId,
                'anak_id' => $selectedChildId,
            ];
            if (!$isAdmin) {
                $measurementSql .= ' AND a.user_id = :user_id';
                $measurementParameters['user_id'] = $userId;
            }
            $measurementSql .= ' LIMIT 1';

            $measurementLookup = db()->prepare($measurementSql);
            $measurementLookup->execute($measurementParameters);
            $measurement = $measurementLookup->fetch();

            if (!$measurement) {
                http_response_code(404);
                exit('Catatan pengukuran tidak ditemukan.');
            }

            $birthDate = new DateTimeImmutable($measurement['tanggal_lahir']);
            $measurementDate = new DateTimeImmutable($measurement['tanggal_ukur']);
            $latestSupportedDate = $birthDate->modify('+5 years');

            if ($measurementDate < $birthDate) {
                $errors[] = 'Tanggal pengukuran tidak boleh sebelum tanggal lahir anak.';
            } elseif ($measurementDate > $latestSupportedDate) {
                $errors[] = 'Standar WHO yang digunakan hanya mencakup usia sampai 60 bulan.';
            } else {
                $ageDiff = $birthDate->diff($measurementDate);
                $ageInDays = (int) $ageDiff->format('%a');
                $ageMonths = min(60.0, $ageInDays / 30.4375);
                $ageMonthsStored = min(60, ($ageDiff->y * 12) + $ageDiff->m);

                try {
                    $scores = calculate_who_growth_scores(
                        $measurement['jenis_kelamin'],
                        $ageMonths,
                        (float) $measurement['berat_kg'],
                        (float) $measurement['tinggi_cm']
                    );
                } catch (OutOfRangeException | InvalidArgumentException $exception) {
                    $scores = null;
                    $errors[] = 'Data pengukuran berada di luar rentang valid tabel acuan WHO; '
                        . 'periksa kembali berat, tinggi, dan tanggal pengukuran bersama tenaga kesehatan.';
                }

                if ($scores !== null) {
                    $heightForAge = $scores['height_for_age'];
                    $weightForAge = $scores['weight_for_age'];
                    $weightForHeight = $scores['weight_for_height'];
                    $hasLowWeight = $weightForAge < -2
                        || ($weightForHeight !== null && $weightForHeight < -2);
                    $hasLowIndicator = $heightForAge < -2 || $hasLowWeight;
                    $status = classify_who_growth($scores);

                    $scoreSummary = sprintf(
                        'Tinggi/panjang-menurut-usia: %.2f SD; berat-menurut-usia: %.2f SD; berat-menurut-tinggi: %s.',
                        $heightForAge,
                        $weightForAge,
                        $weightForHeight === null
                            ? 'tidak tersedia di rentang tabel'
                            : sprintf('%.2f SD', $weightForHeight)
                    );

                    if ($heightForAge < -2) {
                        $recommendation = 'Ada indikasi tinggi/panjang badan menurut usia berada di bawah -2 SD. '
                            . 'Konsultasikan hasil pengukuran dengan tenaga kesehatan.';
                        if ($hasLowWeight) {
                            $recommendation .= ' Indikator berat badan juga berada di bawah -2 SD.';
                        }
                    } elseif ($hasLowWeight) {
                        $recommendation = 'Ada indikator berat badan di bawah rentang skrining WHO. '
                            . 'Konsultasikan hasil pengukuran dengan tenaga kesehatan.';
                    } elseif ($weightForHeight === null) {
                        $recommendation = 'Berat menurut tinggi/panjang tidak dapat dinilai dari rentang tabel ini. '
                            . 'Minta bantuan tenaga kesehatan untuk penilaian yang sesuai.';
                    } elseif ($weightForHeight > 2) {
                        $recommendation = 'Berat badan menurut tinggi berada di atas +2 SD. '
                            . 'Konsultasikan hasil ini dengan tenaga kesehatan.';
                    } else {
                        $recommendation = 'Tidak ada indikator rendah pada skrining ini. '
                            . 'Lanjutkan pemantauan rutin dan konsultasi bila ada kekhawatiran.';
                    }

                    if ($hasLowIndicator && $weightForHeight !== null && $weightForHeight > 2) {
                        $recommendation .= ' Indikator berat menurut tinggi juga berada di atas +2 SD.';
                    }

                    $advice = $scoreSummary . ' ' . $recommendation
                        . ' Hasil ini merupakan skrining awal, bukan diagnosis medis.';

                    $insertStatement = db()->prepare(
                        'INSERT INTO riwayat_cek_gizi
                         (user_id, anak_id, umur_bulan, berat_kg, tinggi_cm, status, saran)
                         VALUES (:user_id, :anak_id, :umur_bulan, :berat_kg, :tinggi_cm, :status, :saran)'
                    );
                    $insertStatement->execute([
                        'user_id' => $measurement['user_id'],
                        'anak_id' => $measurement['anak_id'],
                        'umur_bulan' => $ageMonthsStored,
                        'berat_kg' => $measurement['berat_kg'],
                        'tinggi_cm' => $measurement['tinggi_cm'],
                        'status' => $status,
                        'saran' => $advice,
                    ]);

                    $resultId = (int) db()->lastInsertId();
                    redirect_to(
                        'cek_gizi.php?anak_id=' . (int) $selectedChildId . '&hasil=' . $resultId
                    );
                }
            }
        }
    }
}

if ($selectedChild && isset($_GET['hasil'])) {
    $resultId = filter_var($_GET['hasil'], FILTER_VALIDATE_INT);
    if ($resultId === false || $resultId < 1) {
        http_response_code(404);
        exit('Hasil skrining tidak ditemukan.');
    }

    $resultSql = 'SELECT id, umur_bulan, berat_kg, tinggi_cm, status, saran, created_at
                  FROM riwayat_cek_gizi
                  WHERE id = :id AND anak_id = :anak_id AND user_id = :user_id
                  LIMIT 1';
    $resultStatement = db()->prepare($resultSql);
    $resultStatement->execute([
        'id' => $resultId,
        'anak_id' => $selectedChildId,
        'user_id' => $selectedChild['user_id'],
    ]);
    $savedResult = $resultStatement->fetch();

    if (!$savedResult) {
        http_response_code(404);
        exit('Hasil skrining tidak ditemukan.');
    }
}

$history = [];
if ($selectedChild) {
    $historyStatement = db()->prepare(
        'SELECT id, umur_bulan, berat_kg, tinggi_cm, status, saran, created_at
         FROM riwayat_cek_gizi
         WHERE anak_id = :anak_id AND user_id = :user_id
         ORDER BY created_at DESC, id DESC'
    );
    $historyStatement->execute([
        'anak_id' => $selectedChildId,
        'user_id' => $selectedChild['user_id'],
    ]);
    $history = $historyStatement->fetchAll();
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cek Gizi | Mom&amp;Kids+</title>
    <link rel="stylesheet" href="assets/css/style.css?v=3">
    <link rel="stylesheet" href="assets/css/auth.css?v=6">
</head>
<body class="auth-page">
    <?php if ($isAdmin): ?>
        <?php render_admin_sidebar_start('cek_gizi', $currentUser['nama']); ?>
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
            <span class="eyebrow">Skrining awal</span>
            <h1>Cek status gizi</h1>
            <p>Pilih anak dan catatan pengukuran yang sudah tersimpan untuk menghitung indikator pertumbuhan.</p>
        </div>

        <section class="management-card nutrition-card">
            <form method="get" action="cek_gizi.php" class="auth-form child-selector">
                <label for="anak_id">Pilih anak</label>
                <select id="anak_id" name="anak_id" required>
                    <option value="">Pilih data anak</option>
                    <?php foreach ($children as $item): ?>
                        <option value="<?php echo (int) $item['id']; ?>"
                            <?php echo $selectedChildId === (int) $item['id'] ? 'selected' : ''; ?>>
                            <?php echo escape_html($item['nama']); ?>
                            <?php if ($isAdmin): ?>
                                - <?php echo escape_html($item['nama_ibu']); ?>
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="button button-outline auth-submit" type="submit">Pilih anak</button>
            </form>

            <?php if (!$children): ?>
                <p class="empty-management">
                    Belum ada data anak. Tambahkan data anak dan pengukuran terlebih dahulu.
                    <a class="action-link" href="anak.php">Tambah data anak</a>
                </p>
            <?php elseif ($selectedChild): ?>
                <div class="selected-child">
                    <span>Catatan untuk</span>
                    <strong><?php echo escape_html($selectedChild['nama']); ?></strong>
                    <?php if ($isAdmin): ?>
                        <span>anak dari <?php echo escape_html($selectedChild['nama_ibu']); ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($errors): ?>
                    <div class="form-errors" role="alert">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo escape_html($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($savedResult): ?>
                    <div class="nutrition-result" role="status">
                        <span class="eyebrow">Hasil skrining awal</span>
                        <h2><?php echo escape_html($savedResult['status']); ?></h2>
                        <p><?php echo escape_html($savedResult['saran']); ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!$measurements): ?>
                    <p class="empty-management">
                        Belum ada catatan pengukuran untuk anak ini.
                        <a class="action-link" href="riwayat_ukur.php?anak_id=<?php echo (int) $selectedChildId; ?>">
                            Tambah pengukuran
                        </a>
                    </p>
                <?php else: ?>
                    <p class="measurement-note">
                        Standar WHO menggunakan panjang badan terlentang untuk usia di bawah 24 bulan
                        dan tinggi berdiri mulai 24 bulan. Pastikan cara ukur sesuai.
                    </p>
                    <form method="post" action="cek_gizi.php?anak_id=<?php echo (int) $selectedChildId; ?>"
                        class="auth-form check-form">
                        <input type="hidden" name="csrf_token" value="<?php echo escape_html(csrf_token()); ?>">
                        <input type="hidden" name="anak_id" value="<?php echo (int) $selectedChildId; ?>">
                        <label for="pengukuran_id">Pilih catatan pengukuran</label>
                        <select id="pengukuran_id" name="pengukuran_id" required>
                            <option value="">Pilih tanggal dan ukuran</option>
                            <?php foreach ($measurements as $item): ?>
                                <option value="<?php echo (int) $item['id']; ?>"
                                    <?php echo $selectedMeasurementId === (int) $item['id'] ? 'selected' : ''; ?>>
                                    <?php echo escape_html(date('d-m-Y', strtotime($item['tanggal_ukur']))); ?>
                                    - <?php echo escape_html((string) $item['berat_kg']); ?> kg
                                    - <?php echo escape_html((string) $item['tinggi_cm']); ?> cm
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="button button-primary auth-submit" type="submit">Cek status gizi</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <?php if ($history): ?>
            <section class="management-card nutrition-history">
                <h2>Riwayat cek gizi</h2>
                <div class="table-wrap">
                    <table class="mothers-table">
                        <thead>
                            <tr>
                                <th>Tanggal cek</th>
                                <th>Usia</th>
                                <th>Berat</th>
                                <th>Tinggi</th>
                                <th>Status</th>
                                <th>Hasil</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $item): ?>
                                <tr>
                                    <td><?php echo escape_html(date('d-m-Y', strtotime($item['created_at']))); ?></td>
                                    <td><?php echo (int) $item['umur_bulan']; ?> bulan</td>
                                    <td><?php echo escape_html((string) $item['berat_kg']); ?> kg</td>
                                    <td><?php echo escape_html((string) $item['tinggi_cm']); ?> cm</td>
                                    <td><?php echo escape_html($item['status']); ?></td>
                                    <td><a class="action-link" href="cek_gizi.php?anak_id=<?php echo (int) $selectedChildId; ?>&amp;hasil=<?php echo (int) $item['id']; ?>">Lihat</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>

        <p class="health-disclaimer">
            Kalkulator memakai acuan LMS standar pertumbuhan anak WHO untuk usia 0–60 bulan.
            Hasilnya hanya skrining awal, bukan diagnosis atau pengganti konsultasi tenaga kesehatan.
            <a href="https://www.who.int/tools/child-growth-standards/standards"
                target="_blank" rel="noopener noreferrer">Lihat standar pertumbuhan WHO</a>.
        </p>
    </main>
    <?php if ($isAdmin): ?>
        <?php render_admin_sidebar_end(); ?>
    <?php endif; ?>
    <script src="assets/js/app.js?v=6"></script>
</body>
</html>
