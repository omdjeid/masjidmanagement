<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/site.php';

$notice = null;
$articles = [];
$siteName = configured_site_name();

try {
    $articles = db()->query(
        "SELECT * FROM articles
         WHERE status = 'published'
         ORDER BY COALESCE(published_at, created_at) DESC, id DESC"
    )->fetchAll();
} catch (Throwable) {
    $notice = 'Data artikel belum dapat dimuat saat ini.';
}

$featured = $articles[0] ?? null;

public_page_start(
    'Daftar Artikel',
    'artikel',
    [
        'title' => 'Daftar Artikel',
        'description' => $featured !== null
            ? meta_plain_text((string) (($featured['excerpt'] ?? '') !== '' ? $featured['excerpt'] : ($featured['body'] ?? '')))
            : 'Kumpulan artikel ' . $siteName . '.',
        'image' => (string) ($featured['featured_image'] ?? ''),
        'image_alt' => $featured !== null ? featured_image_alt_text($featured, (string) $featured['title']) : '',
        'twitter_card' => $featured !== null && trim((string) ($featured['featured_image'] ?? '')) !== '' ? 'summary_large_image' : 'summary',
    ]
);
?>
        <?php if ($notice !== null): ?>
            <div class="status-notice"><p class="content-copy"><?= h($notice); ?></p></div>
        <?php endif; ?>

        <section class="page-intro">
            <div>
                <p class="eyebrow">Editorial Sanctuary</p>
                <h1>Artikel<br><?= h($siteName); ?></h1>
                <p class="content-copy">Kumpulan renungan, pembelajaran, dan tulisan yang menenangkan dengan nuansa editorial seperti referensi stitch awal.</p>
            </div>
            <a class="button-secondary" href="<?= h(app_url()); ?>">Kembali ke Home</a>
        </section>

        <section class="article-list-layout">
            <?php if ($featured !== null): ?>
                <article class="article-feature-card">
                    <div class="article-feature-card__media">
                        <?php $featuredImage = media_url((string) ($featured['featured_image'] ?? '')); ?>
                        <?php if ($featuredImage !== ''): ?>
                            <img src="<?= h($featuredImage); ?>" alt="<?= h(featured_image_alt_text($featured, (string) $featured['title'])); ?>" loading="lazy">
                        <?php endif; ?>
                    </div>
                    <div class="article-feature-card__body">
                        <p class="eyebrow">Featured Article</p>
                        <h2><?= h((string) $featured['title']); ?></h2>
                        <p class="content-copy"><?= h((string) ($featured['excerpt'] ?? ('Artikel pilihan ' . $siteName . '.'))); ?></p>
                        <div class="article-meta">
                            <span><?= h((string) $featured['author']); ?></span>
                            <span><?= h(format_human_date((string) ($featured['published_at'] ?? ''))); ?></span>
                            <span class="status-badge status-badge--published"><?= h((string) (($featured['category'] ?? '') !== '' ? $featured['category'] : ($featured['status'] ?? 'Artikel'))); ?></span>
                        </div>
                        <div style="margin-top: 22px;">
                            <a class="button-primary" href="<?= h(app_url('artikel-detail.php?slug=' . urlencode((string) $featured['slug']))); ?>">Baca Artikel</a>
                        </div>
                    </div>
                </article>

                <section class="article-card-grid">
                    <?php foreach ($articles as $article): ?>
                        <a class="article-list-card" href="<?= h(app_url('artikel-detail.php?slug=' . urlencode((string) $article['slug']))); ?>">
                            <div class="article-list-card__thumb">
                                <?php $imageUrl = media_url((string) ($article['featured_image'] ?? '')); ?>
                                <?php if ($imageUrl !== ''): ?>
                                    <img src="<?= h($imageUrl); ?>" alt="<?= h(featured_image_alt_text($article, (string) $article['title'])); ?>" loading="lazy">
                                <?php endif; ?>
                            </div>
                            <div class="article-list-card__body">
                                <span class="status-badge status-badge--published"><?= h((string) (($article['category'] ?? '') !== '' ? $article['category'] : ($article['status'] ?? 'Artikel'))); ?></span>
                                <h3><?= h((string) $article['title']); ?></h3>
                                <p><?= h((string) ($article['excerpt'] ?? ('Artikel ' . $siteName . '.'))); ?></p>
                                <div class="article-meta">
                                    <span><?= h((string) $article['author']); ?></span>
                                    <span><?= h(format_human_date((string) ($article['published_at'] ?? ''))); ?></span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </section>
            <?php else: ?>
                <div class="status-notice">
                    <p class="content-copy">Belum ada artikel yang dipublikasikan saat ini.</p>
                </div>
            <?php endif; ?>
        </section>
<?php
public_page_end();
