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

$summary = [
    'ibu' => (int) db()->query(
        'SELECT COUNT(*) FROM users WHERE role = \'user\''
    )->fetchColumn(),
    'anak' => (int) db()->query(
        'SELECT COUNT(*) FROM anak'
    )->fetchColumn(),
    'stunting' => (int) db()->query(
        'SELECT COUNT(DISTINCT current_check.anak_id)
         FROM riwayat_cek_gizi current_check
         WHERE current_check.anak_id IS NOT NULL
           AND current_check.status = \'Indikasi Stunting\'
           AND NOT EXISTS (
               SELECT 1
               FROM riwayat_cek_gizi newer_check
               WHERE newer_check.anak_id = current_check.anak_id
                 AND (
                     newer_check.created_at > current_check.created_at
                     OR (
                         newer_check.created_at = current_check.created_at
                         AND newer_check.id > current_check.id
                     )
                 )
           )'
    )->fetchColumn(),
];

$recentStatement = db()->query(
    'SELECT r.created_at, r.umur_bulan, r.berat_kg, r.tinggi_cm, r.status,
            a.nama AS nama_anak, u.nama AS nama_ibu
     FROM riwayat_cek_gizi r
     LEFT JOIN anak a ON a.id = r.anak_id
     JOIN users u ON u.id = r.user_id
     ORDER BY r.created_at DESC, r.id DESC
     LIMIT 20'
);
$recentChecks = $recentStatement->fetchAll();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan | Mom&amp;Kids+</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=3">
    <link rel="stylesheet" href="../assets/css/auth.css?v=6">
    <style>
        @media print {
            .auth-header,
            .print-controls {
                display: none !important;
            }

            .auth-page {
                min-height: 0;
                background: #fff;
            }

            .admin-main {
                width: 100%;
                padding: 0;
            }

            .management-card,
            .report-stat {
                break-inside: avoid;
                box-shadow: none;
            }
        }
    </style>
</head>
<body class="auth-page">
    <?php render_admin_sidebar_start('laporan', $currentUser['nama'], '../'); ?>

    <main class="admin-main">
        <div class="management-card-heading">
            <div class="admin-heading">
                <span class="eyebrow">Ringkasan Mom&amp;Kids+</span>
                <h1>Laporan data</h1>
                <p>Diperbarui: <?php echo escape_html(date('d-m-Y H:i')); ?></p>
            </div>
            <div class="print-controls">
                <button class="button button-primary button-small" type="button" data-print>
                    Cetak / Simpan PDF
                </button>
            </div>
        </div>

        <section class="report-stat-grid" aria-label="Ringkasan data">
            <article class="management-card report-stat">
                <span>Total Ibu terdaftar</span>
                <strong><?php echo $summary['ibu']; ?></strong>
            </article>
            <article class="management-card report-stat">
                <span>Total anak</span>
                <strong><?php echo $summary['anak']; ?></strong>
            </article>
            <article class="management-card report-stat">
                <span>Anak dengan skrining terakhir indikasi stunting</span>
                <strong><?php echo $summary['stunting']; ?></strong>
            </article>
        </section>

        <section class="management-card nutrition-history">
            <h2>Riwayat pemeriksaan terbaru</h2>
            <?php if (!$recentChecks): ?>
                <p class="empty-management">Belum ada riwayat pemeriksaan gizi.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="mothers-table">
                        <thead>
                            <tr>
                                <th>Tanggal pemeriksaan</th>
                                <th>Nama anak</th>
                                <th>Nama Ibu</th>
                                <th>Usia</th>
                                <th>Berat</th>
                                <th>Tinggi</th>
                                <th>Status skrining</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentChecks as $check): ?>
                                <tr>
                                    <td><?php echo escape_html(date('d-m-Y H:i', strtotime($check['created_at']))); ?></td>
                                    <td><?php echo escape_html($check['nama_anak'] ?? 'Data anak tidak tersedia'); ?></td>
                                    <td><?php echo escape_html($check['nama_ibu']); ?></td>
                                    <td><?php echo (int) $check['umur_bulan']; ?> bulan</td>
                                    <td><?php echo escape_html((string) $check['berat_kg']); ?> kg</td>
                                    <td><?php echo escape_html((string) $check['tinggi_cm']); ?> cm</td>
                                    <td><?php echo escape_html($check['status']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <p class="management-help report-note">
                Jumlah indikasi stunting dihitung dari hasil skrining terbaru setiap anak, bukan diagnosis medis.
            </p>
        </section>
    </main>
    <?php render_admin_sidebar_end(); ?>
    <script src="../assets/js/app.js?v=6"></script>
</body>
</html>
