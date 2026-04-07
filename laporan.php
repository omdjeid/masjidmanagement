<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/site.php';

$notice = null;
$reports = [];
$siteName = configured_site_name();

try {
    $reports = db()->query(
        "SELECT * FROM reports
         WHERE status = 'published'
         ORDER BY COALESCE(published_at, created_at) DESC, id DESC"
    )->fetchAll();
} catch (Throwable) {
    $notice = 'Data laporan belum dapat dimuat saat ini.';
}

$featured = $reports[0] ?? null;

public_page_start(
    'Laporan',
    'laporan',
    [
        'title' => 'Laporan',
        'description' => $featured !== null
            ? meta_plain_text((string) (($featured['excerpt'] ?? '') !== '' ? $featured['excerpt'] : ($featured['body'] ?? '')))
            : 'Kumpulan laporan publik ' . $siteName . '.',
        'image' => (string) ($featured['featured_image'] ?? ''),
        'image_alt' => (string) ($featured['title'] ?? 'Featured image laporan'),
        'twitter_card' => $featured !== null && trim((string) ($featured['featured_image'] ?? '')) !== '' ? 'summary_large_image' : 'summary',
    ]
);
?>
        <?php if ($notice !== null): ?>
            <div class="status-notice"><p class="content-copy"><?= h($notice); ?></p></div>
        <?php endif; ?>

        <section class="page-intro">
            <div>
                <p class="eyebrow">Public Reports</p>
                <h1>Laporan<br><?= h($siteName); ?></h1>
                <p class="content-copy">Kumpulan laporan kegiatan, laporan keuangan bulanan, dan informasi dokumentasi publik lain yang ditampilkan dengan struktur editorial seperti halaman daftar artikel.</p>
            </div>
            <a class="button-secondary" href="<?= h(app_url()); ?>">Kembali ke Home</a>
        </section>

        <section class="article-list-layout">
            <?php if ($featured !== null): ?>
                <article class="article-feature-card">
                    <div class="article-feature-card__media">
                        <?php $featuredImage = media_url((string) ($featured['featured_image'] ?? '')); ?>
                        <?php if ($featuredImage !== ''): ?>
                            <img src="<?= h($featuredImage); ?>" alt="<?= h((string) $featured['title']); ?>" loading="lazy">
                        <?php endif; ?>
                    </div>
                    <div class="article-feature-card__body">
                        <p class="eyebrow">Featured Report</p>
                        <h2><?= h((string) $featured['title']); ?></h2>
                        <p class="content-copy"><?= h((string) ($featured['excerpt'] ?? ('Laporan pilihan ' . $siteName . '.'))); ?></p>
                        <div class="article-meta">
                            <span><?= h((string) $featured['author']); ?></span>
                            <span><?= h(format_human_date((string) ($featured['published_at'] ?? ''))); ?></span>
                            <?php if (trim((string) ($featured['period_label'] ?? '')) !== ''): ?>
                                <span><?= h((string) $featured['period_label']); ?></span>
                            <?php endif; ?>
                            <span class="status-badge status-badge--published"><?= h((string) (($featured['category'] ?? '') !== '' ? $featured['category'] : 'Laporan')); ?></span>
                        </div>
                        <div style="margin-top: 22px;">
                            <a class="button-primary" href="<?= h(app_url('laporan-detail.php?slug=' . urlencode((string) $featured['slug']))); ?>">Baca Laporan</a>
                        </div>
                    </div>
                </article>

                <section class="article-card-grid">
                    <?php foreach ($reports as $report): ?>
                        <a class="article-list-card" href="<?= h(app_url('laporan-detail.php?slug=' . urlencode((string) $report['slug']))); ?>">
                            <div class="article-list-card__thumb">
                                <?php $imageUrl = media_url((string) ($report['featured_image'] ?? '')); ?>
                                <?php if ($imageUrl !== ''): ?>
                                    <img src="<?= h($imageUrl); ?>" alt="<?= h((string) $report['title']); ?>" loading="lazy">
                                <?php endif; ?>
                            </div>
                            <div class="article-list-card__body">
                                <span class="status-badge status-badge--published"><?= h((string) (($report['category'] ?? '') !== '' ? $report['category'] : 'Laporan')); ?></span>
                                <h3><?= h((string) $report['title']); ?></h3>
                                <p><?= h((string) ($report['excerpt'] ?? ('Laporan ' . $siteName . '.'))); ?></p>
                                <div class="article-meta">
                                    <span><?= h((string) $report['author']); ?></span>
                                    <span><?= h(format_human_date((string) ($report['published_at'] ?? ''))); ?></span>
                                    <?php if (trim((string) ($report['period_label'] ?? '')) !== ''): ?>
                                        <span><?= h((string) $report['period_label']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </section>
            <?php else: ?>
                <div class="status-notice">
                    <p class="content-copy">Belum ada laporan yang dipublikasikan saat ini.</p>
                </div>
            <?php endif; ?>
        </section>
<?php
public_page_end();
