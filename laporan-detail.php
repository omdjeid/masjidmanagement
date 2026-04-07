<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/site.php';

function report_body_html(string $body): string
{
    $body = trim($body);

    if ($body === '') {
        return '';
    }

    if (preg_match('/<\s*(p|h2|h3|ul|ol|li|blockquote|strong|em|a|br|img)\b/i', $body) === 1) {
        return sanitize_rich_text_html($body);
    }

    $paragraphs = preg_split("/\R{2,}/", $body) ?: [];
    $html = '';

    foreach ($paragraphs as $paragraph) {
        $text = trim($paragraph);
        if ($text === '') {
            continue;
        }

        $html .= '<p>' . nl2br(h($text)) . '</p>';
    }

    return $html;
}

function report_current_page_url(): string
{
    return absolute_request_url('laporan-detail.php');
}

function report_gallery_items(?string $value): array
{
    $lines = preg_split('/\R+/', trim((string) $value)) ?: [];
    $items = [];

    foreach ($lines as $line) {
        $url = media_url(trim($line));
        if ($url === '') {
            continue;
        }

        $items[] = $url;
    }

    return array_values(array_unique($items));
}

function report_media_type(string $url): string
{
    $path = (string) parse_url($url, PHP_URL_PATH);
    $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

    return match ($extension) {
        'jpg', 'jpeg', 'png', 'webp', 'gif', 'avif' => 'image',
        'pdf' => 'pdf',
        default => 'link',
    };
}

$slug = trim((string) ($_GET['slug'] ?? ''));
$notice = null;
$report = null;
$relatedReports = [];

try {
    if ($slug !== '') {
        $statement = db()->prepare("SELECT * FROM reports WHERE slug = :slug AND status = 'published' LIMIT 1");
        $statement->execute(['slug' => $slug]);
        $result = $statement->fetch();
        if ($result !== false) {
            $report = $result;
        }
    } else {
        $result = db()->query("SELECT * FROM reports WHERE status = 'published' ORDER BY COALESCE(published_at, created_at) DESC LIMIT 1")->fetch();
        if ($result !== false) {
            $report = $result;
        }
    }

    if ($report !== null) {
        $related = db()->prepare(
            "SELECT * FROM reports
             WHERE status = 'published' AND slug <> :slug
             ORDER BY COALESCE(published_at, created_at) DESC
             LIMIT 3"
        );
        $related->execute(['slug' => (string) $report['slug']]);
        $relatedReports = $related->fetchAll();
    }
} catch (Throwable) {
    $notice = 'Data laporan belum dapat dimuat saat ini.';
}

$reportHeroImage = media_url((string) ($report['featured_image'] ?? ''));
$reportBody = report_body_html((string) ($report['body'] ?? ''));
$attachmentUrl = media_url((string) ($report['attachment_url'] ?? ''));
$attachmentType = $attachmentUrl !== '' ? report_media_type($attachmentUrl) : 'link';
$galleryItems = report_gallery_items((string) ($report['gallery_urls'] ?? ''));
$siteName = configured_site_name();
$whatsAppShareUrl = 'https://wa.me/?text=' . rawurlencode((string) ($report['title'] ?? ('Laporan ' . $siteName)) . ' - ' . report_current_page_url());
$reportMetaDescription = $report !== null
    ? meta_plain_text((string) (($report['excerpt'] ?? '') !== '' ? $report['excerpt'] : ($report['body'] ?? '')))
    : '';

public_page_start(
    (string) ($report['title'] ?? 'Laporan'),
    'laporan',
    [
        'title' => (string) ($report['title'] ?? 'Laporan'),
        'description' => $reportMetaDescription,
        'image' => (string) ($report['featured_image'] ?? ''),
        'image_alt' => (string) ($report['title'] ?? 'Featured image laporan'),
        'type' => 'article',
        'twitter_card' => $reportHeroImage !== '' ? 'summary_large_image' : 'summary',
    ]
);
?>
        <?php if ($notice !== null): ?>
            <div class="status-notice"><p class="content-copy"><?= h($notice); ?></p></div>
        <?php endif; ?>

        <?php if ($report !== null): ?>
            <section class="article-hero<?= $reportHeroImage !== '' ? ' article-hero--has-image' : ''; ?>"<?= $reportHeroImage !== '' ? ' style="background-image: linear-gradient(0deg, rgba(0, 53, 39, 0.78), rgba(0, 53, 39, 0.2)), url(\'' . h($reportHeroImage) . '\');"' : ''; ?>>
                <div>
                    <p class="eyebrow"><?= h((string) (($report['category'] ?? '') !== '' ? $report['category'] : 'Laporan')); ?></p>
                    <h1><?= h((string) $report['title']); ?></h1>
                    <div class="article-meta">
                        <span>Oleh <?= h((string) $report['author']); ?></span>
                        <span><?= h(format_human_date($report['published_at'] !== null ? (string) $report['published_at'] : null, true)); ?></span>
                        <?php if (trim((string) ($report['period_label'] ?? '')) !== ''): ?>
                            <span><?= h((string) $report['period_label']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="article-layout">
                <aside class="article-side">
                    <article class="meta-card">
                        <p class="eyebrow">Dokumen Terkait</p>
                        <ul class="topic-list">
                            <?php foreach (array_slice($relatedReports, 0, 3) as $item): ?>
                                <li>
                                    <a href="<?= h(app_url('laporan-detail.php?slug=' . urlencode((string) $item['slug']))); ?>">
                                        <span class="topic-list__arrow">&rarr;</span>
                                        <span><?= h((string) $item['title']); ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if ($attachmentUrl !== ''): ?>
                            <div style="margin-top: 1.25rem;">
                                <a class="button-secondary" href="<?= h($attachmentUrl); ?>" target="_blank" rel="noopener noreferrer">Buka Lampiran</a>
                            </div>
                        <?php endif; ?>
                    </article>
                </aside>

                <article class="article-body">
                    <?= $reportBody; ?>

                    <?php if ($attachmentUrl !== ''): ?>
                        <section class="report-attachment-section">
                            <p class="eyebrow">Preview Lampiran</p>
                            <?php if ($attachmentType === 'pdf'): ?>
                                <div class="report-attachment-preview">
                                    <iframe src="<?= h($attachmentUrl); ?>#toolbar=0" title="Preview lampiran laporan" loading="lazy"></iframe>
                                </div>
                            <?php elseif ($attachmentType === 'image'): ?>
                                <div class="report-attachment-image">
                                    <img src="<?= h($attachmentUrl); ?>" alt="Lampiran laporan" loading="lazy">
                                </div>
                            <?php else: ?>
                                <div class="report-attachment-link">
                                    <p class="content-copy">Lampiran tersedia dalam format eksternal.</p>
                                    <a class="button-secondary" href="<?= h($attachmentUrl); ?>" target="_blank" rel="noopener noreferrer">Buka Lampiran</a>
                                </div>
                            <?php endif; ?>
                        </section>
                    <?php endif; ?>

                    <?php if ($galleryItems !== []): ?>
                        <section class="report-gallery-section">
                            <div class="section-head">
                                <div>
                                    <p class="eyebrow">Dokumentasi Kegiatan</p>
                                    <h2 class="section-title">Gallery Kegiatan</h2>
                                </div>
                            </div>
                            <div class="report-gallery-grid">
                                <?php foreach ($galleryItems as $galleryUrl): ?>
                                    <a class="report-gallery-card" href="<?= h($galleryUrl); ?>" target="_blank" rel="noopener noreferrer">
                                        <img src="<?= h($galleryUrl); ?>" alt="Dokumentasi kegiatan laporan" loading="lazy">
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <div class="share-section">
                        <p class="eyebrow">Bagikan Laporan Ini</p>
                        <a class="whatsapp-share" href="<?= h($whatsAppShareUrl); ?>" target="_blank" rel="noopener noreferrer">
                            <span>Bagikan ke WhatsApp</span>
                        </a>
                    </div>
                </article>

                <div class="article-spacer" aria-hidden="true"></div>
            </section>

            <?php if ($relatedReports !== []): ?>
                <section class="list-card">
                    <div class="section-head">
                        <div>
                            <p class="eyebrow">Arsip Laporan</p>
                            <h2 class="section-title">Laporan Terkait Lainnya</h2>
                        </div>
                        <a class="section-link" href="<?= h(app_url('laporan.php')); ?>">Lihat Semua</a>
                    </div>
                    <div class="related-grid">
                        <?php foreach ($relatedReports as $item): ?>
                            <a class="related-card" href="<?= h(app_url('laporan-detail.php?slug=' . urlencode((string) $item['slug']))); ?>">
                                <div class="related-thumb">
                                    <?php $relatedImage = media_url((string) ($item['featured_image'] ?? '')); ?>
                                    <?php if ($relatedImage !== ''): ?>
                                        <img src="<?= h($relatedImage); ?>" alt="<?= h((string) $item['title']); ?>" loading="lazy">
                                    <?php endif; ?>
                                    <span class="related-thumb__badge"><?= h((string) (($item['category'] ?? '') !== '' ? $item['category'] : 'Laporan')); ?></span>
                                </div>
                                <div class="related-card__body">
                                    <h3><?= h((string) $item['title']); ?></h3>
                                    <p><?= h((string) $item['author']); ?> - <?= h(format_human_date((string) ($item['published_at'] ?? ''))); ?><?php if (trim((string) ($item['period_label'] ?? '')) !== ''): ?> - <?= h((string) $item['period_label']); ?><?php endif; ?></p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        <?php else: ?>
            <div class="status-notice">
                <p class="content-copy">Laporan yang Anda cari belum tersedia.</p>
            </div>
        <?php endif; ?>
<?php
public_page_end();
