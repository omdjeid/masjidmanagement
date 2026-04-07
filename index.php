<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/site.php';
sync_study_schedule_statuses();
sync_infaq_campaign_statuses();

function home_initials(string $text): string
{
    $parts = preg_split('/\s+/', trim($text)) ?: [];
    $initials = '';

    foreach ($parts as $part) {
        $clean = trim($part, " \t\n\r\0\x0B.,-");
        if ($clean === '' || preg_match('/^(ust|ustadz|ust\.|dr|dr\.)$/i', $clean) === 1) {
            continue;
        }

        $initials .= strtoupper(function_exists('mb_substr') ? mb_substr($clean, 0, 1) : substr($clean, 0, 1));

        if (strlen($initials) >= 2) {
            break;
        }
    }

    return $initials !== '' ? $initials : 'MA';
}

function home_http_post_json(string $url, array $payload): ?array
{
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($body === false) {
        return null;
    }

    $response = null;

    if (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL) || ini_get('allow_url_fopen') === '1') {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => $body,
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $result = @file_get_contents($url, false, $context);
        if (is_string($result) && $result !== '') {
            $response = $result;
        }
    }

    if (($response === null || $response === '') && function_exists('curl_init')) {
        $curl = curl_init($url);
        if ($curl !== false) {
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_TIMEOUT => 10,
            ]);

            $result = curl_exec($curl);
            if (is_string($result) && $result !== '') {
                $response = $result;
            }
            curl_close($curl);
        }
    }

    if (!is_string($response) || $response === '') {
        return null;
    }

    $decoded = json_decode($response, true);

    return is_array($decoded) ? $decoded : null;
}

function home_apply_prayer_offset(string $time, int $offsetMinutes): string
{
    $base = DateTimeImmutable::createFromFormat('H:i', $time);

    if (!$base instanceof DateTimeImmutable) {
        return $time;
    }

    if ($offsetMinutes === 0) {
        return $base->format('H:i');
    }

    $modifier = ($offsetMinutes > 0 ? '+' : '') . $offsetMinutes . ' minutes';

    return $base->modify($modifier)->format('H:i');
}

function home_fetch_prayer_schedule(DateTimeImmutable $today, string $province, string $city): ?array
{
    $cities = array_values(array_unique([$city, str_replace('Kota ', '', $city), str_replace('Kab. ', '', $city)]));

    foreach ($cities as $city) {
        $response = home_http_post_json(
            'https://equran.id/api/v2/shalat',
            [
                'provinsi' => $province,
                'kabkota' => $city,
                'bulan' => (int) $today->format('n'),
                'tahun' => (int) $today->format('Y'),
            ]
        );

        if (!is_array($response) || (int) ($response['code'] ?? 0) !== 200) {
            continue;
        }

        $data = $response['data'] ?? null;
        $items = is_array($data) ? ($data['jadwal'] ?? []) : [];

        if (!is_array($items)) {
            continue;
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            if ((string) ($item['tanggal_lengkap'] ?? '') !== $today->format('Y-m-d')) {
                continue;
            }

            return [
                'provinsi' => (string) ($data['provinsi'] ?? $province),
                'kabkota' => (string) ($data['kabkota'] ?? $city),
                'tanggal_lengkap' => (string) ($item['tanggal_lengkap'] ?? $today->format('Y-m-d')),
                'subuh' => (string) ($item['subuh'] ?? ''),
                'dzuhur' => (string) ($item['dzuhur'] ?? ''),
                'ashar' => (string) ($item['ashar'] ?? ''),
                'maghrib' => (string) ($item['maghrib'] ?? ''),
                'isya' => (string) ($item['isya'] ?? ''),
            ];
        }
    }

    return null;
}

function home_prayer_widget_state(array $schedule, DateTimeImmutable $now): array
{
    $date = (string) ($schedule['tanggal_lengkap'] ?? $now->format('Y-m-d'));
    $labels = [
        'subuh' => 'Subuh',
        'dzuhur' => 'Dzuhur',
        'ashar' => 'Ashar',
        'maghrib' => 'Maghrib',
        'isya' => 'Isya',
    ];

    $upcomingKey = null;
    $upcomingTime = null;

    foreach ($labels as $key => $label) {
        $time = trim((string) ($schedule[$key] ?? ''));
        if ($time === '') {
            continue;
        }

        $candidate = DateTimeImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . $time);
        if (!$candidate instanceof DateTimeImmutable) {
            continue;
        }

        if ($candidate > $now) {
            $upcomingKey = $key;
            $upcomingTime = $candidate;
            break;
        }
    }

    if ($upcomingKey === null || !$upcomingTime instanceof DateTimeImmutable) {
        return [
            'eyebrow' => 'Jadwal Shalat Hari Ini',
            'title' => 'Isya',
            'time' => (string) ($schedule['isya'] ?? '--:--'),
        ];
    }

    $diff = $now->diff($upcomingTime);
    $minutes = max(1, ((int) $diff->h * 60) + (int) $diff->i + ((int) $diff->s > 0 ? 1 : 0));
    $hours = intdiv($minutes, 60);
    $remainingMinutes = $minutes % 60;

    if ($hours > 0) {
        $countdown = sprintf('%d Jam %d Menit', $hours, $remainingMinutes);
    } else {
        $countdown = sprintf('%d Menit', $remainingMinutes);
    }

    return [
        'eyebrow' => 'Shalat Berikutnya Dalam ' . $countdown,
        'title' => $labels[$upcomingKey],
        'time' => (string) ($schedule[$upcomingKey] ?? '--:--'),
    ];
}

$timezone = new DateTimeZone('Asia/Jakarta');
$today = new DateTimeImmutable('now', $timezone);
$prayerDefaults = prayer_setting_defaults();
$prayerSettings = [];

foreach ($prayerDefaults as $key => $default) {
    $prayerSettings[$key] = configuration_get($key, $default) ?? $default;
}

$tickerItems = configuration_lines('homepage_ticker_text', homepage_ticker_defaults());
$nextSchedule = null;
$featuredArticle = null;
$featuredVideo = null;
$featuredInfaq = null;
$prayerSchedule = [
    'provinsi' => (string) ($prayerSettings['prayer_api_province'] !== '' ? $prayerSettings['prayer_api_province'] : 'Provinsi belum diatur'),
    'kabkota' => (string) ($prayerSettings['prayer_api_city'] !== '' ? $prayerSettings['prayer_api_city'] : 'Kota belum diatur'),
    'tanggal_lengkap' => $today->format('Y-m-d'),
    'subuh' => '04:58',
    'dzuhur' => '12:27',
    'ashar' => '15:44',
    'maghrib' => '18:31',
    'isya' => '19:41',
];
$prayerError = null;

try {
    $schedule = db()->query(
        "SELECT * FROM study_schedules
         WHERE status = 'scheduled' AND session_date >= CURDATE()
         ORDER BY session_date ASC, start_time ASC
         LIMIT 1"
    )->fetch();
    if ($schedule !== false) {
        $nextSchedule = $schedule;
    }

    $article = db()->query(
        "SELECT * FROM articles
         WHERE status = 'published'
         ORDER BY COALESCE(published_at, created_at) DESC
         LIMIT 1"
    )->fetch();
    if ($article !== false) {
        $featuredArticle = $article;
    }

    $video = db()->query(
        "SELECT * FROM videos
         WHERE status = 'published'
         ORDER BY video_date IS NULL, video_date DESC, created_at DESC
         LIMIT 1"
    )->fetch();
    if ($video !== false) {
        $featuredVideo = $video;
    }

    $infaq = db()->query(
        "SELECT * FROM infaq_campaigns
         WHERE status IN ('active', 'completed')
         ORDER BY FIELD(status, 'active', 'completed', 'archived'), created_at DESC
         LIMIT 1"
    )->fetch();
    if ($infaq !== false) {
        $featuredInfaq = $infaq;
    }
} catch (Throwable) {
}

if (is_array($nextSchedule)) {
    $nextSchedule['status'] = resolve_study_schedule_status($nextSchedule);
}

try {
    $prayerProvince = trim((string) $prayerSettings['prayer_api_province']);
    $prayerCity = trim((string) $prayerSettings['prayer_api_city']);

    if ($prayerProvince !== '' && $prayerCity !== '') {
        $apiPrayerSchedule = home_fetch_prayer_schedule($today, $prayerProvince, $prayerCity);
        if (is_array($apiPrayerSchedule)) {
            $prayerSchedule = $apiPrayerSchedule;
        } else {
            $prayerError = 'Jadwal shalat sementara memakai fallback lokal karena API belum merespons.';
        }
    } else {
        $prayerError = 'Atur provinsi dan kota pada menu Settings agar widget jadwal shalat memakai data lokasi masjid Anda.';
    }
} catch (Throwable) {
    $prayerError = 'Jadwal shalat sementara memakai fallback lokal karena API belum merespons.';
}

foreach (['subuh', 'dzuhur', 'ashar', 'maghrib', 'isya'] as $prayerKey) {
    $offsetKey = 'prayer_offset_' . $prayerKey;
    $offsetMinutes = (int) ($prayerSettings[$offsetKey] ?? '0');
    $prayerSchedule[$prayerKey] = home_apply_prayer_offset((string) ($prayerSchedule[$prayerKey] ?? ''), $offsetMinutes);
}

$progress = infaq_progress_metrics((float) ($featuredInfaq['target_amount'] ?? 0), (float) ($featuredInfaq['collected_amount'] ?? 0));

$whatsAppChannelUrl = trim((string) (configuration_get('whatsapp_channel_url', general_setting_defaults()['whatsapp_channel_url']) ?? ''));
$siteName = configured_site_name();
$siteTagline = configured_site_tagline();
$featuredVideoDate = isset($featuredVideo['video_date']) ? format_human_date((string) $featuredVideo['video_date']) : '-';
$featuredArticleDate = isset($featuredArticle['published_at']) ? format_human_date((string) $featuredArticle['published_at']) : '-';
$featuredInfaqCollected = format_currency((float) ($featuredInfaq['collected_amount'] ?? 0));
$featuredInfaqTarget = format_currency((float) ($featuredInfaq['target_amount'] ?? 0));
$heroPrayer = home_prayer_widget_state($prayerSchedule, $today);
$homeMetaImage = trim((string) ($featuredArticle['featured_image'] ?? ''));
if ($homeMetaImage === '' && $featuredVideo !== null) {
    $homeMetaImage = youtube_thumbnail_url((string) ($featuredVideo['youtube_url'] ?? ''));
}
$homeMetaDescriptionSetting = trim((string) (configuration_get('meta_description', '') ?? ''));
$homeLayout = [
    'site_name' => $siteName,
    'title' => $siteTagline !== '' ? $siteName . ' | ' . $siteTagline : $siteName,
    'meta' => [
        'title' => $siteTagline !== '' ? $siteName . ' | ' . $siteTagline : $siteName,
        'description' => $homeMetaDescriptionSetting !== ''
            ? $homeMetaDescriptionSetting
                : ($featuredArticle !== null
                ? meta_plain_text((string) (($featuredArticle['excerpt'] ?? '') !== '' ? $featuredArticle['excerpt'] : ($featuredArticle['body'] ?? '')))
                : $siteName . ' menghadirkan jadwal kajian, artikel, video, infaq, dan informasi jamaah dalam pengalaman digital yang tenang dan modern.'),
        'image' => $homeMetaImage,
        'image_alt' => $featuredArticle !== null
            ? featured_image_alt_text($featuredArticle, (string) $featuredArticle['title'])
            : (string) ($featuredVideo['title'] ?? $siteName),
        'twitter_card' => $homeMetaImage !== '' ? 'summary_large_image' : 'summary',
    ],
];
$prayerLabels = [
    'subuh' => 'Subuh',
    'dzuhur' => 'Dzuhur',
    'ashar' => 'Ashar',
    'maghrib' => 'Maghrib',
    'isya' => 'Isya',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h((string) $homeLayout['title']); ?></title>
    <?php render_site_meta_tags($homeLayout); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:FILL@0..1" rel="stylesheet">
    <link rel="stylesheet" href="<?= h(asset_url('css/home.css')); ?>">
    <?php render_google_analytics_tag(); ?>
</head>
<body>
    <header class="site-header">
        <div class="site-header__inner">
            <a class="site-brand" href="<?= h(app_url()); ?>"><?= h($siteName); ?></a>
            <nav class="site-nav" aria-label="Navigasi utama">
                <a href="<?= h(app_url()); ?>" style="color: var(--primary); font-weight: 700;">Home</a>
                <a href="<?= h(app_url('jadwal.php')); ?>">Jadwal</a>
                <a href="<?= h(app_url('artikel-list.php')); ?>">Artikel</a>
                <a href="<?= h(app_url('kajian-video.php')); ?>">Video</a>
                <a href="<?= h(app_url('laporan.php')); ?>">Laporan</a>
                <a href="<?= h(app_url('lokasi.php')); ?>">Lokasi</a>
                <a href="<?= h(app_url('infaq-page.php')); ?>">Infaq</a>
            </nav>
        </div>
    </header>

    <main class="page-shell" id="home">
        <section class="hero-prayer">
            <div class="hero-prayer__content">
                <div>
                    <p class="eyebrow"><?= h($heroPrayer['eyebrow']); ?></p>
                    <h1><?= h($heroPrayer['title']); ?></h1>
                    <p class="hero-prayer__time"><?= h($heroPrayer['time']); ?> WIB</p>
                    <p class="hero-prayer__meta"><?= h((string) $prayerSchedule['kabkota']); ?>, <?= h((string) $prayerSchedule['provinsi']); ?> · <?= h(format_human_date((string) $prayerSchedule['tanggal_lengkap'])); ?></p>
                </div>
                <div class="prayer-grid" aria-label="Jadwal shalat">
                    <?php foreach ($prayerLabels as $key => $label): ?>
                        <div class="<?= $heroPrayer['title'] === $label ? 'is-active' : ''; ?>">
                            <span><?= h($label); ?></span>
                            <strong><?= h((string) ($prayerSchedule[$key] ?? '--:--')); ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="ticker" aria-label="Pengumuman masjid">
            <div class="ticker__track">
                <?php foreach ($tickerItems as $item): ?>
                    <span><?= h($item); ?></span>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="bento-grid">
            <article class="bento-card bento-card--wide" id="kajian">
                <div>
                    <p class="eyebrow">Upcoming Session</p>
                    <h2>Jadwal Kajian</h2>
                    <?php if (is_array($nextSchedule)): ?>
                        <p class="card-copy"><?= h((string) ($nextSchedule['summary'] ?? 'Kajian terbaru dari ' . $siteName . '.')); ?></p>
                        <div class="schedule-card__details">
                            <strong><?= h((string) $nextSchedule['title']); ?></strong>
                            <span><?= h((string) $nextSchedule['speaker']); ?></span>
                            <span><?= h(format_human_date((string) $nextSchedule['session_date'])); ?> - <?= h(substr((string) $nextSchedule['start_time'], 0, 5)); ?> WIB</span>
                            <span><?= h((string) $nextSchedule['location']); ?></span>
                        </div>
                    <?php else: ?>
                        <p class="card-copy">Belum ada jadwal kajian yang dipublikasikan saat ini.</p>
                    <?php endif; ?>
                </div>
                <div class="schedule-card__footer">
                    <div class="schedule-card__meta" aria-label="Meta kajian terdekat">
                        <span class="meta-chip"><?= h((string) (is_array($nextSchedule) && ($nextSchedule['category'] ?? '') !== '' ? $nextSchedule['category'] : 'Kajian')); ?></span>
                        <?php if (is_array($nextSchedule)): ?>
                            <span class="meta-chip meta-chip--soft"><?= h(home_initials((string) $nextSchedule['speaker'])); ?></span>
                        <?php endif; ?>
                    </div>
                    <a class="button-link button-link--dark" href="<?= h(app_url('jadwal.php')); ?>">Lihat Jadwal</a>
                </div>
            </article>

            <a class="bento-card bento-card--video" href="<?= h(app_url('kajian-video.php')); ?>">
                <span class="material-symbols-outlined icon-pill" aria-hidden="true">play_circle</span>
                <div>
                    <p class="eyebrow">Archive</p>
                    <?php if (is_array($featuredVideo)): ?>
                        <strong><?= h((string) $featuredVideo['title']); ?></strong>
                        <div class="video-card__meta">
                            <span><?= h((string) $featuredVideo['speaker']); ?></span>
                            <span><?= h((string) (($featuredVideo['category'] ?? '') !== '' ? $featuredVideo['category'] : 'Kajian')); ?></span>
                            <span><?= h($featuredVideoDate); ?></span>
                        </div>
                    <?php else: ?>
                        <strong>Video Archive</strong>
                        <div class="video-card__meta">
                            <span>Belum ada video kajian yang dipublikasikan.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </a>

            <article class="bento-card bento-card--donation" id="infaq">
                <div>
                    <p class="eyebrow">Infaq &amp; Sadaqah</p>
                    <h2><?= h((string) ($featuredInfaq['title'] ?? 'Campaign Infaq')); ?></h2>
                    <p class="card-copy"><?= h((string) ($featuredInfaq['description'] ?? 'Belum ada campaign infaq yang dipublikasikan saat ini.')); ?></p>
                </div>
                <div class="progress-block">
                    <div class="progress-track">
                        <div class="progress-fill" style="width: <?= h((string) $progress['fill_percent']); ?>%"></div>
                    </div>
                    <div class="progress-meta">
                        <span><?= h($featuredInfaqCollected); ?> / <?= h($featuredInfaqTarget); ?></span>
                        <a class="button-link button-link--dark" href="<?= h(app_url('infaq-page.php')); ?>">Donasi Sekarang</a>
                    </div>
                    <p class="progress-caption"><?= h((string) $progress['percent']); ?>% target tercapai</p>
                </div>
            </article>

            <article class="bento-card bento-card--article" id="artikel">
                <?php $homeArticleImage = media_url((string) ($featuredArticle['featured_image'] ?? '')); ?>
                <div class="article-thumb<?= $homeArticleImage !== '' ? ' article-thumb--image' : ''; ?>" aria-hidden="true">
                    <?php if ($homeArticleImage !== ''): ?>
                        <img src="<?= h($homeArticleImage); ?>" alt="<?= h(featured_image_alt_text((array) $featuredArticle, (string) ($featuredArticle['title'] ?? $siteName))); ?>" loading="lazy">
                    <?php endif; ?>
                </div>
                <div class="article-content">
                    <p class="eyebrow">Editorial</p>
                    <h2><?= h((string) ($featuredArticle['title'] ?? 'Artikel ' . $siteName)); ?></h2>
                    <p class="card-copy"><?= h((string) ($featuredArticle['excerpt'] ?? 'Belum ada artikel yang dipublikasikan saat ini.')); ?></p>
                    <div class="article-meta">
                        <span>
                            <?php if (is_array($featuredArticle)): ?>
                                <?= h((string) $featuredArticle['author']); ?> - <?= h($featuredArticleDate); ?>
                            <?php else: ?>
                                Menunggu publikasi artikel terbaru
                            <?php endif; ?>
                        </span>
                        <a class="article-link" href="<?= h(is_array($featuredArticle) ? app_url('artikel-detail.php?slug=' . urlencode((string) $featuredArticle['slug'])) : app_url('artikel-list.php')); ?>">
                            Read Article
                            <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
                        </a>
                    </div>
                </div>
            </article>
        </section>

        <section class="community-section">
            <div class="section-heading">
                <div>
                    <h2>Community Voice</h2>
                    <p>Ruang digital yang tenang untuk kajian, literasi, dan pelayanan jamaah dengan arah visual yang sama seperti referensi stitch.</p>
                </div>
            </div>
            <div class="community-grid">
                <article class="community-card">
                    <h3>Youth Hub</h3>
                    <p>Memberi ruang untuk pembinaan generasi muda melalui mentoring, kajian, dan program keterampilan.</p>
                </article>
                <article class="community-card">
                    <h3>Library Access</h3>
                    <p>Menghadirkan akses ke koleksi kitab, buku Islam kontemporer, dan artikel pembelajaran yang tertata.</p>
                </article>
                <article class="community-card">
                    <h3>Family Counseling</h3>
                    <p>Membuka jalur pendampingan keluarga dan konsultasi dengan nuansa yang hangat, rapi, dan tidak terasa kaku.</p>
                </article>
            </div>
        </section>
    </main>

    <?php if ($whatsAppChannelUrl !== ''): ?>
        <a class="floating-channel-link" href="<?= h($whatsAppChannelUrl); ?>" target="_blank" rel="noopener noreferrer" aria-label="Buka WhatsApp Channel <?= h($siteName); ?>">
            <span class="floating-channel-link__icon material-symbols-outlined" aria-hidden="true">chat</span>
            <span class="floating-channel-link__copy">
                <strong>WhatsApp Channel</strong>
                <span>Ikuti update <?= h($siteName); ?></span>
            </span>
        </a>
    <?php endif; ?>

    <footer class="site-footer">
        <div class="site-footer__inner">
            <div>
                <div class="site-brand"><?= h($siteName); ?></div>
                <?php if ($siteTagline !== ''): ?>
                    <p><?= h($siteTagline); ?></p>
                <?php endif; ?>
            </div>
            <div class="footer-links">
                <a href="<?= h(app_url('jadwal.php')); ?>">Jadwal</a>
                <a href="<?= h(app_url('artikel-list.php')); ?>">Artikel</a>
                <a href="<?= h(app_url('kajian-video.php')); ?>">Video</a>
                <a href="<?= h(app_url('laporan.php')); ?>">Laporan</a>
                <a href="<?= h(app_url('lokasi.php')); ?>">Lokasi</a>
                <a href="<?= h(app_url('infaq-page.php')); ?>">Infaq</a>
            </div>
            <p class="site-footer__copy">&copy; 2026 <?= h($siteName); ?><?= $siteTagline !== '' ? '. ' . h($siteTagline) : ''; ?></p>
        </div>
    </footer>
</body>
</html>
