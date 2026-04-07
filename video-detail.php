<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/site.php';

function youtube_embed_url(string $url): string
{
    $videoId = youtube_video_id($url);

    return $videoId !== '' ? 'https://www.youtube.com/embed/' . $videoId : '';
}

function current_video_page_url(): string
{
    return absolute_request_url('video-detail.php');
}

$id = (int) ($_GET['id'] ?? 0);
$notice = null;
$video = null;
$relatedVideos = [];

try {
    if ($id > 0) {
        $statement = db()->prepare("SELECT * FROM videos WHERE id = :id AND status = 'published' LIMIT 1");
        $statement->execute(['id' => $id]);
        $result = $statement->fetch();
        if ($result !== false) {
            $video = $result;
        }
    } else {
        $result = db()->query("SELECT * FROM videos WHERE status = 'published' ORDER BY video_date IS NULL, video_date DESC, created_at DESC LIMIT 1")->fetch();
        if ($result !== false) {
            $video = $result;
        }
    }

    if ($video !== null) {
        $related = db()->prepare(
            "SELECT * FROM videos
             WHERE status = 'published' AND id <> :id
             ORDER BY video_date IS NULL, video_date DESC, created_at DESC
             LIMIT 4"
        );
        $related->execute(['id' => (int) $video['id']]);
        $relatedVideos = $related->fetchAll();
    }
} catch (Throwable) {
    $notice = 'Data video belum dapat dimuat saat ini.';
}

$points = array_values(array_filter(array_map('trim', preg_split("/\R+|\.\s+/", (string) ($video['summary'] ?? '')) ?: [])));
$embedUrl = youtube_embed_url((string) ($video['youtube_url'] ?? ''));
$videoCategory = trim((string) ($video['category'] ?? ''));
$videoCategoryLabel = $videoCategory !== '' ? 'Kajian ' . $videoCategory : 'Kajian Publik';
$shareUrl = 'https://wa.me/?text=' . rawurlencode((string) ($video['title'] ?? 'Video Kajian') . ' - ' . current_video_page_url());
$videoThumbnail = youtube_thumbnail_url((string) ($video['youtube_url'] ?? ''));
$siteName = configured_site_name();
$videoMetaDescription = $video !== null
    ? meta_plain_text((string) (($video['summary'] ?? '') !== '' ? $video['summary'] : ($video['title'] ?? ('Video kajian ' . $siteName))))
    : '';
$generalDefaults = general_setting_defaults();
$whatsAppChannelUrl = trim((string) (configuration_get('whatsapp_channel_url', $generalDefaults['whatsapp_channel_url']) ?? ''));

public_page_start(
    (string) ($video['title'] ?? 'Video Kajian'),
    'video',
    [
        'title' => (string) ($video['title'] ?? 'Video Kajian'),
        'description' => $videoMetaDescription,
        'image' => (string) ($videoThumbnail !== '' ? $videoThumbnail : ''),
        'image_alt' => (string) ($video['title'] ?? 'Thumbnail video kajian'),
        'type' => 'website',
        'twitter_card' => $videoThumbnail !== '' ? 'summary_large_image' : 'summary',
    ]
);
?>
        <?php if ($notice !== null): ?>
            <div class="status-notice"><p class="content-copy"><?= h($notice); ?></p></div>
        <?php endif; ?>

        <?php if ($video !== null): ?>
            <section class="video-detail-page">
                <div class="video-detail-grid">
                    <div class="video-detail-main">
                        <div class="video-player-shell">
                            <div class="video-player-ratio">
                                <?php if ($embedUrl !== ''): ?>
                                    <iframe
                                        src="<?= h($embedUrl); ?>"
                                        title="<?= h((string) $video['title']); ?>"
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                        allowfullscreen
                                    ></iframe>
                                <?php endif; ?>
                            </div>
                        </div>

                        <section class="video-info-block">
                            <div class="video-info-top">
                                <div>
                                    <span class="video-category-chip"><?= h($videoCategoryLabel); ?></span>
                                    <h1 class="video-title"><?= h((string) $video['title']); ?></h1>
                                    <div class="video-info-meta">
                                        <span><?= h((string) $video['speaker']); ?></span>
                                        <span class="video-dot" aria-hidden="true"></span>
                                        <span><?= h(format_human_date($video['video_date'] !== null ? (string) $video['video_date'] : null)); ?></span>
                                    </div>
                                </div>
                                <a class="video-share-button" href="<?= h($shareUrl); ?>" target="_blank" rel="noopener noreferrer">Bagikan</a>
                            </div>

                            <?php if ($points !== []): ?>
                                <div class="video-divider" aria-hidden="true"></div>

                                <section class="faidah-section">
                                    <div class="faidah-header">
                                        <span class="faidah-icon">Ilmu</span>
                                        <h2>Poin-Poin Faidah</h2>
                                    </div>
                                    <div class="faidah-grid">
                                        <?php foreach ($points as $point): ?>
                                            <article class="faidah-card">
                                                <p><?= h($point); ?></p>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                </section>
                            <?php endif; ?>
                        </section>
                    </div>

                    <aside class="video-detail-aside">
                        <?php if ($relatedVideos !== []): ?>
                            <article class="related-panel">
                                <h3 class="related-panel__title">Video Terkait</h3>
                                <div class="related-panel__list">
                                    <?php foreach ($relatedVideos as $item): ?>
                                        <a class="related-video-item" href="<?= h(app_url('video-detail.php?id=' . urlencode((string) $item['id']))); ?>">
                                            <?php $thumbnailUrl = youtube_thumbnail_url((string) ($item['youtube_url'] ?? '')); ?>
                                            <div class="related-video-item__thumb">
                                                <?php if ($thumbnailUrl !== ''): ?>
                                                    <img src="<?= h($thumbnailUrl); ?>" alt="<?= h((string) $item['title']); ?>" loading="lazy">
                                                <?php endif; ?>
                                                <span class="related-video-item__overlay">Play</span>
                                            </div>
                                            <div class="related-video-item__body">
                                                <h4><?= h((string) $item['title']); ?></h4>
                                                <p><?= h((string) $item['speaker']); ?></p>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                                <a class="video-cta-link" href="<?= h(app_url('kajian-video.php')); ?>">Lihat Semua Video</a>
                            </article>
                        <?php endif; ?>

                        <article class="video-subscribe-card">
                            <h3>Ikuti WhatsApp Channel</h3>
                            <p>Gabung ke WhatsApp Channel untuk mendapatkan update kajian terbaru langsung dari <?= h($siteName); ?>.</p>
                            <div class="newsletter-form">
                                <?php if ($whatsAppChannelUrl !== ''): ?>
                                    <a class="button-primary" href="<?= h($whatsAppChannelUrl); ?>" target="_blank" rel="noopener noreferrer">Buka WhatsApp Channel</a>
                                <?php else: ?>
                                    <span class="public-input">URL WhatsApp Channel belum diatur di halaman setting.</span>
                                <?php endif; ?>
                            </div>
                        </article>
                    </aside>
                </div>
            </section>
        <?php else: ?>
            <div class="status-notice">
                <p class="content-copy">Video yang Anda cari belum tersedia.</p>
            </div>
        <?php endif; ?>
<?php
public_page_end();
