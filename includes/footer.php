<?php
declare(strict_types=1);

$layout = $GLOBALS['_public_layout_data'] ?? [];
$siteName = (string) ($layout['site_name'] ?? configured_site_name());
$siteTagline = trim((string) ($layout['site_tagline'] ?? ''));
$activeNav = (string) ($layout['active_nav'] ?? '');
$mobileItems = is_array($layout['mobile_items'] ?? null) ? $layout['mobile_items'] : [];
?>
    </main>
    <footer class="public-footer">
        <div class="public-footer__inner">
            <div>
                <div class="public-brand"><?= h($siteName); ?></div>
                <?php if ($siteTagline !== ''): ?>
                    <p><?= h($siteTagline); ?></p>
                <?php endif; ?>
            </div>
            <div class="public-footer__links">
                <a href="<?= h(app_url()); ?>">Home</a>
                <a href="<?= h(app_url('jadwal.php')); ?>">Jadwal</a>
                <a href="<?= h(app_url('artikel-list.php')); ?>">Artikel</a>
                <a href="<?= h(app_url('kajian-video.php')); ?>">Video</a>
                <a href="<?= h(app_url('laporan.php')); ?>">Laporan</a>
                <a href="<?= h(app_url('lokasi.php')); ?>">Lokasi</a>
                <a href="<?= h(app_url('infaq-page.php')); ?>">Infaq</a>
            </div>
            <p class="public-footer__copy">&copy; 2026 <?= h($siteName); ?><?= $siteTagline !== '' ? '. ' . h($siteTagline) : ''; ?></p>
        </div>
    </footer>
    <nav class="public-mobile-nav" aria-label="Navigasi bawah">
        <?php foreach ($mobileItems as $key => $item): ?>
            <a class="<?= $activeNav === $key ? 'is-active' : ''; ?>" href="<?= h((string) ($item['href'] ?? app_url())); ?>"><?= h((string) ($item['label'] ?? '')); ?></a>
        <?php endforeach; ?>
    </nav>
</body>
</html>
