<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/site.php';

function video_duration_label(?int $minutes): string
{
    if ($minutes === null || $minutes <= 0) {
        return 'Durasi belum tersedia';
    }

    if ($minutes >= 60) {
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        if ($rest === 0) {
            return $hours . ' Jam';
        }

        return $hours . ' Jam ' . $rest . ' Menit';
    }

    return $minutes . ' Menit';
}

$notice = null;
$videos = [];

try {
    $videos = db()->query(
        "SELECT * FROM videos
         WHERE status = 'published'
         ORDER BY video_date IS NULL, video_date DESC, created_at DESC, id DESC"
    )->fetchAll();
} catch (Throwable) {
    $notice = 'Data video belum dapat dimuat saat ini.';
}

$featuredVideo = $videos[0] ?? null;
$takeaways = array_values(array_filter(array_map('trim', preg_split("/\R+|\.\s+/", (string) ($featuredVideo['summary'] ?? '')) ?: [])));
$archiveVideos = array_slice($videos, 1, 3);
$browseVideos = array_slice($videos, 1);
if ($browseVideos === [] && $featuredVideo !== null) {
    $browseVideos = [$featuredVideo];
}

$featuredVideoThumb = $featuredVideo !== null ? youtube_thumbnail_url((string) ($featuredVideo['youtube_url'] ?? '')) : '';
$generalDefaults = general_setting_defaults();
$siteName = configured_site_name();
$whatsAppChannelUrl = trim((string) (configuration_get('whatsapp_channel_url', $generalDefaults['whatsapp_channel_url']) ?? ''));
public_page_start(
    'Kajian Video',
    'video',
    [
        'title' => 'Kajian Video',
        'description' => $featuredVideo !== null
            ? meta_plain_text((string) (($featuredVideo['summary'] ?? '') !== '' ? $featuredVideo['summary'] : ($featuredVideo['title'] ?? ('Kajian video ' . $siteName))))
            : 'Arsip kajian video ' . $siteName . '.',
        'image' => $featuredVideoThumb,
        'image_alt' => (string) ($featuredVideo['title'] ?? 'Thumbnail kajian video'),
        'twitter_card' => $featuredVideoThumb !== '' ? 'summary_large_image' : 'summary',
    ]
);
?>
        <?php if ($notice !== null): ?>
            <div class="status-notice"><p class="content-copy"><?= h($notice); ?></p></div>
        <?php endif; ?>

        <section class="lecture-page">
            <div class="lecture-shell">
                <section class="lecture-topbar">
                    <div>
                        <p class="eyebrow">Media Dakwah</p>
                        <h1 class="section-title">Kajian Video</h1>
                    </div>
                    <?php if ($featuredVideo !== null): ?>
                        <a class="button-secondary" href="<?= h(app_url('video-detail.php?id=' . urlencode((string) $featuredVideo['id']))); ?>">Buka Video Utama</a>
                    <?php endif; ?>
                </section>

                <?php if ($featuredVideo !== null): ?>
                    <section class="lecture-hero-grid">
                        <div class="lecture-main">
                            <a class="lecture-player-card" href="<?= h(app_url('video-detail.php?id=' . urlencode((string) $featuredVideo['id']))); ?>">
                                <?php $featuredThumb = youtube_thumbnail_url((string) ($featuredVideo['youtube_url'] ?? '')); ?>
                                <?php if ($featuredThumb !== ''): ?>
                                    <img src="<?= h($featuredThumb); ?>" alt="<?= h((string) $featuredVideo['title']); ?>" loading="lazy">
                                <?php endif; ?>
                                <div class="lecture-play-overlay">
                                    <span class="lecture-play-button">Play</span>
                                </div>
                                <div class="lecture-player-copy">
                                    <span class="lecture-kicker">Now Playing</span>
                                    <h2><?= h((string) $featuredVideo['title']); ?></h2>
                                </div>
                            </a>

                            <?php if ($takeaways !== []): ?>
                                <article class="takeaways-card">
                                    <p class="eyebrow">Poin-Poin Faidah Kajian</p>
                                    <div class="takeaway-list">
                                        <?php foreach ($takeaways as $index => $item): ?>
                                            <div class="takeaway-item">
                                                <span><?= h(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)); ?></span>
                                                <p><?= h($item); ?></p>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </article>
                            <?php endif; ?>
                        </div>

                        <aside class="archive-aside">
                            <div class="archive-head">
                                <p class="eyebrow">Arsip Terkait</p>
                                <a class="section-link" href="#arsip-kajian">Lihat Semua</a>
                            </div>

                            <div class="archive-list">
                                <?php foreach ($archiveVideos as $item): ?>
                                    <a class="archive-item" href="<?= h(app_url('video-detail.php?id=' . urlencode((string) ($item['id'] ?? 1)))); ?>">
                                        <div class="archive-item__thumb">
                                            <?php $archiveThumb = youtube_thumbnail_url((string) ($item['youtube_url'] ?? '')); ?>
                                            <?php if ($archiveThumb !== ''): ?>
                                                <img src="<?= h($archiveThumb); ?>" alt="<?= h((string) $item['title']); ?>" loading="lazy">
                                            <?php endif; ?>
                                        </div>
                                        <div class="archive-item__body">
                                            <h3><?= h((string) $item['title']); ?></h3>
                                            <p><?= h((string) $item['speaker']); ?></p>
                                            <span><?= h(format_human_date((string) ($item['video_date'] ?? ''))); ?> - <?= h(video_duration_label(isset($item['duration_minutes']) ? (int) $item['duration_minutes'] : null)); ?></span>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>

                            <article class="filter-cta-card">
                                <h3>Ikuti WhatsApp Channel</h3>
                                <p>Dapatkan pengumuman kajian terbaru dan update konten video langsung dari <?= h($siteName); ?>.</p>
                                <?php if ($whatsAppChannelUrl !== ''): ?>
                                    <a class="button-primary" href="<?= h($whatsAppChannelUrl); ?>" target="_blank" rel="noopener noreferrer">Buka WhatsApp Channel</a>
                                <?php else: ?>
                                    <p>WhatsApp Channel belum tersedia saat ini.</p>
                                <?php endif; ?>
                            </article>
                        </aside>
                    </section>

                    <section class="lecture-browse" id="arsip-kajian">
                        <div class="section-head">
                            <div>
                                <p class="eyebrow">Arsip Kajian</p>
                                <h2 class="section-title">Jelajahi Arsip Kajian</h2>
                            </div>
                        </div>

                        <div class="lecture-grid">
                            <?php foreach ($browseVideos as $index => $item): ?>
                                <?php $category = trim((string) ($item['category'] ?? '')); ?>
                                <a class="lecture-tile<?= $index === 1 ? ' lecture-tile--featured' : ''; ?>" href="<?= h(app_url('video-detail.php?id=' . urlencode((string) ($item['id'] ?? 1)))); ?>">
                                    <div class="lecture-tile__head">
                                        <span class="lecture-chip"><?= h($category !== '' ? $category : 'Tanpa Kategori'); ?></span>
                                        <span class="lecture-action">Lihat Rekaman</span>
                                    </div>
                                    <div class="lecture-tile__body">
                                        <h3><?= h((string) $item['title']); ?></h3>
                                        <p><?= h((string) $item['speaker']); ?></p>
                                    </div>
                                    <div class="lecture-tile__foot">
                                        <span><?= h(strtoupper(date('d M Y', strtotime((string) ($item['video_date'] ?? 'now'))))); ?></span>
                                        <span><?= h(video_duration_label(isset($item['duration_minutes']) ? (int) $item['duration_minutes'] : null)); ?></span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php else: ?>
                    <div class="status-notice">
                        <p class="content-copy">Belum ada video kajian yang dipublikasikan saat ini.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
<?php
public_page_end();
