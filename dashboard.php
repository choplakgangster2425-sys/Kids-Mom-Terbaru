<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin_layout.php';
require_login();

$user = current_user();
if ($user === null) {
    redirect_to('login.php');
}

if ($user['role'] !== 'admin') {
    redirect_to('index.php');
}

$stats = [
    'ibu' => (int) db()->query('SELECT COUNT(*) FROM users WHERE role = \'user\'')->fetchColumn(),
    'anak' => (int) db()->query('SELECT COUNT(*) FROM anak')->fetchColumn(),
    'konsultasi_menunggu' => (int) db()->query('SELECT COUNT(*) FROM konsultasi WHERE status = \'Menunggu\'')->fetchColumn(),
    'posyandu_mendatang' => (int) db()->query('SELECT COUNT(*) FROM jadwal_posyandu WHERE tanggal >= CURRENT_DATE')->fetchColumn(),
    'artikel' => (int) db()->query('SELECT COUNT(*) FROM artikel WHERE status = \'published\'')->fetchColumn(),
    'resep' => (int) db()->query('SELECT COUNT(*) FROM resep WHERE status = \'published\'')->fetchColumn(),
];

$pendingConsultations = db()->query(
    'SELECT k.id, k.pertanyaan, k.created_at, u.nama AS nama_ibu
     FROM konsultasi k
     JOIN users u ON u.id = k.user_id
     WHERE k.status = \'Menunggu\'
     ORDER BY k.created_at DESC, k.id DESC
     LIMIT 5'
)->fetchAll();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin | Mom&amp;Kids+</title>
    <link rel="stylesheet" href="assets/css/style.css?v=3">
    <link rel="stylesheet" href="assets/css/auth.css?v=6">
</head>
<body class="auth-page">
    <?php render_admin_sidebar_start('dashboard', $user['nama']); ?>

    <main class="admin-main">
        <div class="admin-heading" data-reveal>
            <span class="eyebrow">Ringkasan Admin / Bidan</span>
            <h1>Halo, <?php echo escape_html($user['nama']); ?>!</h1>
            <p>Ringkasan aktivitas Mom&amp;Kids+ hari ini. Pilih menu di samping untuk mengelola layanan keluarga dengan lebih mudah.</p>
        </div>

        <section class="report-stat-grid admin-overview-grid" aria-label="Ringkasan statistik">
            <article class="management-card report-stat" data-reveal>
                <span>Total Ibu terdaftar</span>
                <strong><?php echo $stats['ibu']; ?></strong>
                <a class="action-link" href="admin/ibu.php">Lihat daftar ibu</a>
            </article>
            <article class="management-card report-stat" data-reveal>
                <span>Total data anak</span>
                <strong><?php echo $stats['anak']; ?></strong>
                <a class="action-link" href="anak.php">Kelola data anak</a>
            </article>
            <article class="management-card report-stat" data-reveal>
                <span>Konsultasi menunggu balasan</span>
                <strong><?php echo $stats['konsultasi_menunggu']; ?></strong>
                <a class="action-link" href="admin/konsultasi.php">Buka konsultasi</a>
            </article>
            <article class="management-card report-stat" data-reveal>
                <span>Jadwal Posyandu mendatang</span>
                <strong><?php echo $stats['posyandu_mendatang']; ?></strong>
                <a class="action-link" href="admin/posyandu.php">Kelola Posyandu</a>
            </article>
            <article class="management-card report-stat" data-reveal>
                <span>Artikel dipublikasikan</span>
                <strong><?php echo $stats['artikel']; ?></strong>
                <a class="action-link" href="admin/artikel.php">Kelola artikel</a>
            </article>
            <article class="management-card report-stat" data-reveal>
                <span>Resep dipublikasikan</span>
                <strong><?php echo $stats['resep']; ?></strong>
                <a class="action-link" href="admin/resep.php">Kelola resep</a>
            </article>
        </section>

        <section class="management-card nutrition-history" data-reveal>
            <div class="management-card-heading">
                <div>
                    <h2>Pertanyaan Tanya Bidan menunggu balasan</h2>
                    <p class="management-help">5 pertanyaan terbaru yang belum dijawab.</p>
                </div>
                <a class="action-link" href="admin/konsultasi.php">Kelola semua</a>
            </div>

            <?php if (!$pendingConsultations): ?>
                <p class="empty-management">Tidak ada konsultasi yang menunggu balasan saat ini.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="mothers-table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Nama Ibu</th>
                                <th>Pertanyaan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingConsultations as $item): ?>
                                <tr>
                                    <td><?php echo escape_html(date('d-m-Y H:i', strtotime($item['created_at']))); ?></td>
                                    <td><?php echo escape_html($item['nama_ibu']); ?></td>
                                    <td class="table-excerpt"><?php echo escape_html($item['pertanyaan']); ?></td>
                                    <td><a class="action-link" href="admin/konsultasi.php">Balas</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <?php render_admin_sidebar_end(); ?>
    <script src="assets/js/app.js?v=6"></script>
</body>
</html>
