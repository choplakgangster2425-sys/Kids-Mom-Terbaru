<?php

declare(strict_types=1);

function render_admin_sidebar_start(string $activeKey, string $adminName, string $basePath = ''): void
{
    $icons = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'laporan' => '<path d="M4 19V5"/><path d="M4 19h17"/><path d="m7 15 4-4 3 2 5-6"/>',
        'ibu' => '<path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M20 8v6m-3-3h6"/>',
        'anak' => '<circle cx="12" cy="8" r="5"/><path d="M7 13c-1 1-2 2-2 4v3h14v-3c0-2-1-3-2-4"/><path d="M10 8h.01M14 8h.01"/>',
        'cek_gizi' => '<path d="M12 21s-8-4.5-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 10c0 6.5-8 11-8 11Z"/><path d="M12 11v5m-2.5-2.5h5"/>',
        'konsultasi' => '<path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8Z"/>',
        'posyandu' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/><path d="M12 14v4m-2-2h4"/>',
        'artikel' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/><path d="M8 7h8m-8 4h8"/>',
        'resep' => '<path d="M3 11h18l-2 9H5l-2-9Z"/><path d="M7 11a5 5 0 0 1 10 0"/><path d="M12 3v3m-5-2 2 3m8-3-2 3"/>',
        'home' => '<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-6v-7h-4v7H4a1 1 0 0 1-1-1V10Z"/>',
        'logout' => '<path d="M10 17l5-5-5-5m5 5H3"/><path d="M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/>',
    ];
    $renderIcon = static function (string $name) use ($icons): string {
        return '<svg class="admin-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
            . ' stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
            . ($icons[$name] ?? $icons['dashboard']) . '</svg>';
    };
    $menuGroups = [
        'Utama' => [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => $basePath . 'dashboard.php', 'icon' => 'dashboard'],
            ['key' => 'laporan', 'label' => 'Laporan data', 'href' => $basePath . 'admin/laporan.php', 'icon' => 'laporan'],
        ],
        'Data keluarga' => [
            ['key' => 'ibu', 'label' => 'Daftar ibu', 'href' => $basePath . 'admin/ibu.php', 'icon' => 'ibu'],
            ['key' => 'anak', 'label' => 'Data anak', 'href' => $basePath . 'anak.php', 'icon' => 'anak'],
            ['key' => 'cek_gizi', 'label' => 'Cek gizi', 'href' => $basePath . 'cek_gizi.php', 'icon' => 'cek_gizi'],
        ],
        'Layanan & konten' => [
            ['key' => 'konsultasi', 'label' => 'Konsultasi masuk', 'href' => $basePath . 'admin/konsultasi.php', 'icon' => 'konsultasi'],
            ['key' => 'posyandu', 'label' => 'Kelola Posyandu', 'href' => $basePath . 'admin/posyandu.php', 'icon' => 'posyandu'],
            ['key' => 'artikel', 'label' => 'Kelola artikel', 'href' => $basePath . 'admin/artikel.php', 'icon' => 'artikel'],
            ['key' => 'resep', 'label' => 'Kelola resep', 'href' => $basePath . 'admin/resep.php', 'icon' => 'resep'],
        ],
    ];
    $logoSrc = $basePath . 'assets/img/LOGO%20Mom%26Kids%2B-3.png';
    $homeHref = $basePath . 'dashboard.php';
    $publicHref = $basePath . 'index.php';
    $logoutHref = $basePath . 'logout.php';
    ?>
    <div class="admin-shell">
        <div class="admin-mobile-bar">
            <a class="brand" href="<?php echo escape_html($homeHref); ?>">
                <img class="brand-logo" src="<?php echo escape_html($logoSrc); ?>" alt="Mom&amp;Kids+">
            </a>
            <button class="menu-toggle admin-sidebar-toggle" type="button" aria-expanded="false" aria-controls="admin-sidebar">
                <span class="sr-only">Buka menu admin</span>
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>

        <div class="admin-sidebar-backdrop" data-sidebar-close></div>

        <aside class="admin-sidebar" id="admin-sidebar" aria-label="Navigasi Admin">
            <div class="admin-sidebar-header">
                <div class="admin-sidebar-brand-row">
                    <a class="brand" href="<?php echo escape_html($homeHref); ?>">
                        <img class="brand-logo" src="<?php echo escape_html($logoSrc); ?>" alt="Mom&amp;Kids+">
                    </a>
                    <button class="admin-sidebar-collapse-toggle" type="button" aria-expanded="true"
                        aria-controls="admin-sidebar" aria-label="Perkecil sidebar" title="Perkecil sidebar">
                        <span></span><span></span><span></span>
                    </button>
                </div>
                <div class="admin-user-pill">
                    <span class="admin-role-tag">Admin / Bidan</span>
                    <strong><?php echo escape_html($adminName); ?></strong>
                </div>
            </div>

            <nav class="admin-sidebar-nav">
                <?php foreach ($menuGroups as $groupLabel => $items): ?>
                    <div class="admin-nav-group">
                        <span class="admin-nav-label"><?php echo escape_html($groupLabel); ?></span>
                        <?php foreach ($items as $item): ?>
                            <a class="admin-nav-link<?php echo $activeKey === $item['key'] ? ' is-active' : ''; ?>"
                                href="<?php echo escape_html($item['href']); ?>"
                                title="<?php echo escape_html($item['label']); ?>"
                                <?php echo $activeKey === $item['key'] ? 'aria-current="page"' : ''; ?>>
                                <?php echo $renderIcon($item['icon']); ?>
                                <span class="admin-nav-text"><?php echo escape_html($item['label']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </nav>

            <div class="admin-sidebar-footer">
                <a class="button button-small button-outline admin-sidebar-btn" href="<?php echo escape_html($publicHref); ?>"
                    aria-label="Lihat beranda" title="Lihat beranda">
                    <?php echo $renderIcon('home'); ?>
                    <span class="admin-nav-text">Lihat beranda</span>
                </a>
                <form method="post" action="<?php echo escape_html($logoutHref); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo escape_html(csrf_token()); ?>">
                    <button class="button button-small button-outline admin-sidebar-btn" type="submit"
                        aria-label="Keluar" title="Keluar">
                        <?php echo $renderIcon('logout'); ?>
                        <span class="admin-nav-text">Keluar</span>
                    </button>
                </form>
            </div>
        </aside>

        <div class="admin-workspace">
            <button class="admin-sidebar-open-toggle" type="button" aria-expanded="false"
                aria-controls="admin-sidebar" aria-label="Buka sidebar" title="Buka sidebar">
                <span></span><span></span><span></span>
            </button>
    <?php
}

function render_admin_sidebar_end(): void
{
    ?>
        </div>
    </div>
    <?php
}
