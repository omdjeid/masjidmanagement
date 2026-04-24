<?php
declare(strict_types=1);

require_once __DIR__ . '/admin.php';

function youtube_video_id(string $url): string
{
    $parts = parse_url($url);
    if (!is_array($parts)) {
        return '';
    }

    $host = strtolower((string) ($parts['host'] ?? ''));

    if ($host === 'youtu.be') {
        return trim((string) ($parts['path'] ?? ''), '/');
    }

    parse_str((string) ($parts['query'] ?? ''), $query);

    return (string) ($query['v'] ?? '');
}

function youtube_thumbnail_url(string $url, string $quality = 'hqdefault'): string
{
    $videoId = youtube_video_id($url);

    if ($videoId === '') {
        return '';
    }

    return sprintf('https://img.youtube.com/vi/%s/%s.jpg', rawurlencode($videoId), $quality);
}

function media_url(?string $path): string
{
    $path = trim((string) $path);

    if ($path === '') {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $path) === 1) {
        return $path;
    }

    return app_url(ltrim($path, '/'));
}

function absolute_media_url(?string $path): string
{
    $path = trim((string) $path);

    if ($path === '') {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $path) === 1) {
        return $path;
    }

    return absolute_app_url(ltrim($path, '/'));
}

function meta_plain_text(string $value, int $limit = 180): string
{
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');

    if ($text === '') {
        return '';
    }

    if ($limit <= 0) {
        return $text;
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $limit - 1)) . '...';
    }

    if (strlen($text) <= $limit) {
        return $text;
    }

    return rtrim(substr($text, 0, $limit - 1)) . '...';
}

function featured_image_alt_text(array $record, string $fallback = ''): string
{
    $alt = trim((string) ($record['featured_image_alt'] ?? ''));

    if ($alt !== '') {
        return $alt;
    }

    $title = trim((string) ($record['title'] ?? ''));

    if ($title !== '') {
        return $title;
    }

    return trim($fallback);
}

function featured_image_title_text(array $record, string $fallback = ''): string
{
    $title = trim((string) ($record['featured_image_title'] ?? ''));

    if ($title !== '') {
        return $title;
    }

    $recordTitle = trim((string) ($record['title'] ?? ''));

    if ($recordTitle !== '') {
        return $recordTitle;
    }

    return trim($fallback);
}

function google_analytics_measurement_id(): string
{
    $defaults = general_setting_defaults();
    $code = strtoupper(trim((string) (configuration_get('google_analytics_code', $defaults['google_analytics_code']) ?? $defaults['google_analytics_code'])));

    if ($code === '') {
        return '';
    }

    if (preg_match('/^(G-[A-Z0-9]+|UA-\d+-\d+)$/', $code) !== 1) {
        return '';
    }

    return $code;
}

function render_google_analytics_tag(): void
{
    $measurementId = google_analytics_measurement_id();

    if ($measurementId === '') {
        return;
    }
    ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= h($measurementId); ?>"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '<?= h($measurementId); ?>');
    </script>
    <?php
}

function page_title_tag(string $title, string $siteName): string
{
    $title = trim($title);
    $siteName = trim($siteName);

    if ($title === '') {
        return $siteName;
    }

    if ($siteName === '' || strcasecmp($title, $siteName) === 0) {
        return $title;
    }

    return $title . ' | ' . $siteName;
}

function site_meta_settings(): array
{
    $defaults = meta_setting_defaults();
    $settings = [];

    foreach ($defaults as $key => $default) {
        $settings[$key] = trim((string) (configuration_get($key, $default) ?? $default));
    }

    if (!in_array($settings['og_type'], ['website', 'article', 'profile'], true)) {
        $settings['og_type'] = $defaults['og_type'];
    }

    if (!in_array($settings['twitter_card'], ['summary', 'summary_large_image'], true)) {
        $settings['twitter_card'] = $defaults['twitter_card'];
    }

    return $settings;
}

function render_site_meta_tags(array $layout): void
{
    $siteName = trim((string) ($layout['site_name'] ?? ''));
    $pageTitle = trim((string) ($layout['title'] ?? $siteName));
    $pageMeta = is_array($layout['meta'] ?? null) ? $layout['meta'] : [];
    $fullTitle = trim((string) ($pageMeta['title'] ?? ''));
    if ($fullTitle === '') {
        $fullTitle = page_title_tag($pageTitle, $siteName);
    }
    $siteTagline = trim((string) (configuration_get('site_tagline', general_setting_defaults()['site_tagline']) ?? ''));
    $settings = site_meta_settings();

    $metaDescription = trim((string) ($pageMeta['description'] ?? ''));
    if ($metaDescription === '') {
        $metaDescription = $settings['meta_description'] !== '' ? $settings['meta_description'] : $siteTagline;
    }
    $metaDescription = meta_plain_text($metaDescription);

    $ogType = trim((string) ($pageMeta['type'] ?? ''));
    if ($ogType === '') {
        $ogType = $settings['og_type'];
    }
    if (preg_match('/^[a-z0-9_.-]+$/i', $ogType) !== 1) {
        $ogType = 'website';
    }

    $ogTitle = trim((string) ($pageMeta['og_title'] ?? ''));
    if ($ogTitle === '') {
        $ogTitle = $settings['og_title'] !== '' ? $settings['og_title'] : $fullTitle;
    }

    $ogDescription = trim((string) ($pageMeta['og_description'] ?? ''));
    if ($ogDescription === '') {
        $ogDescription = $settings['og_description'] !== '' ? $settings['og_description'] : $metaDescription;
    }
    $ogDescription = meta_plain_text($ogDescription);

    $pageImage = trim((string) ($pageMeta['image'] ?? ''));
    $ogImage = absolute_media_url($pageImage !== '' ? $pageImage : $settings['og_image']);
    $imageAlt = meta_plain_text(trim((string) ($pageMeta['image_alt'] ?? '')), 120);

    $twitterCard = trim((string) ($pageMeta['twitter_card'] ?? ''));
    if ($twitterCard === '') {
        $twitterCard = $settings['twitter_card'];
    }
    if (!in_array($twitterCard, ['summary', 'summary_large_image'], true)) {
        $twitterCard = $ogImage !== '' ? 'summary_large_image' : 'summary';
    }

    $twitterTitle = trim((string) ($pageMeta['twitter_title'] ?? ''));
    if ($twitterTitle === '') {
        $twitterTitle = $settings['twitter_title'] !== '' ? $settings['twitter_title'] : $ogTitle;
    }

    $twitterDescription = trim((string) ($pageMeta['twitter_description'] ?? ''));
    if ($twitterDescription === '') {
        $twitterDescription = $settings['twitter_description'] !== '' ? $settings['twitter_description'] : $ogDescription;
    }
    $twitterDescription = meta_plain_text($twitterDescription);

    $twitterImageSource = trim((string) ($pageMeta['twitter_image'] ?? ''));
    if ($twitterImageSource === '') {
        $twitterImageSource = trim((string) ($pageMeta['image'] ?? ''));
    }
    if ($twitterImageSource === '') {
        $twitterImageSource = $settings['twitter_image'] !== '' ? $settings['twitter_image'] : $settings['og_image'];
    }
    $twitterImage = absolute_media_url($twitterImageSource);
    $faviconUrl = media_url($settings['favicon_url']);
    $requestUrl = trim((string) ($pageMeta['url'] ?? ''));
    if ($requestUrl === '') {
        $requestUrl = absolute_request_url();
    }

    if ($metaDescription !== ''): ?>
    <meta name="description" content="<?= h($metaDescription); ?>">
    <?php endif;

    if ($settings['meta_keywords'] !== ''): ?>
    <meta name="keywords" content="<?= h($settings['meta_keywords']); ?>">
    <?php endif; ?>
    <meta property="og:type" content="<?= h($ogType); ?>">
    <meta property="og:title" content="<?= h($ogTitle); ?>">
    <?php if ($ogDescription !== ''): ?>
    <meta property="og:description" content="<?= h($ogDescription); ?>">
    <?php endif; ?>
    <?php if ($siteName !== ''): ?>
    <meta property="og:site_name" content="<?= h($siteName); ?>">
    <?php endif; ?>
    <meta property="og:url" content="<?= h($requestUrl); ?>">
    <?php if ($ogImage !== ''): ?>
    <meta property="og:image" content="<?= h($ogImage); ?>">
    <?php if ($imageAlt !== ''): ?>
    <meta property="og:image:alt" content="<?= h($imageAlt); ?>">
    <?php endif; ?>
    <?php endif; ?>
    <meta name="twitter:card" content="<?= h($twitterCard); ?>">
    <meta name="twitter:title" content="<?= h($twitterTitle); ?>">
    <?php if ($twitterDescription !== ''): ?>
    <meta name="twitter:description" content="<?= h($twitterDescription); ?>">
    <?php endif; ?>
    <?php if ($twitterImage !== ''): ?>
    <meta name="twitter:image" content="<?= h($twitterImage); ?>">
    <?php if ($imageAlt !== ''): ?>
    <meta name="twitter:image:alt" content="<?= h($imageAlt); ?>">
    <?php endif; ?>
    <?php endif; ?>
    <?php if ($faviconUrl !== ''): ?>
    <link rel="icon" href="<?= h($faviconUrl); ?>">
    <?php endif;
}

function public_page_start(string $title, string $activeNav, array $meta = []): void
{
    $siteName = configured_site_name();
    $navItems = [
        'home' => ['label' => 'Home', 'href' => app_url()],
        'jadwal' => ['label' => 'Jadwal', 'href' => app_url('jadwal.php')],
        'artikel' => ['label' => 'Artikel', 'href' => app_url('artikel-list.php')],
        'video' => ['label' => 'Video', 'href' => app_url('kajian-video.php')],
        'laporan' => ['label' => 'Laporan', 'href' => app_url('laporan.php')],
        'lokasi' => ['label' => 'Lokasi', 'href' => app_url('lokasi.php')],
        'infaq' => ['label' => 'Infaq', 'href' => app_url('infaq-page.php')],
        'qurban' => ['label' => 'Qurban', 'href' => app_url('qurban-page.php')],
    ];

    $GLOBALS['_public_layout_data'] = [
        'title' => $title,
        'active_nav' => $activeNav,
        'site_name' => $siteName,
        'meta' => $meta,
        'nav_items' => $navItems,
    ];

    require __DIR__ . '/header.php';
}

function public_page_end(): void
{
    $siteName = configured_site_name();
    $siteTagline = configured_site_tagline();
    $mobileItems = [
        'home' => ['label' => 'Home', 'href' => app_url()],
        'jadwal' => ['label' => 'Jadwal', 'href' => app_url('jadwal.php')],
        'artikel' => ['label' => 'Artikel', 'href' => app_url('artikel-list.php')],
        'video' => ['label' => 'Video', 'href' => app_url('kajian-video.php')],
        'laporan' => ['label' => 'Laporan', 'href' => app_url('laporan.php')],
        'lokasi' => ['label' => 'Lokasi', 'href' => app_url('lokasi.php')],
        'infaq' => ['label' => 'Infaq', 'href' => app_url('infaq-page.php')],
        'qurban' => ['label' => 'Qurban', 'href' => app_url('qurban-page.php')],
    ];

    $layout = is_array($GLOBALS['_public_layout_data'] ?? null) ? $GLOBALS['_public_layout_data'] : [];
    $layout['site_name'] = $siteName;
    $layout['site_tagline'] = $siteTagline;
    $layout['mobile_items'] = $mobileItems;
    $GLOBALS['_public_layout_data'] = $layout;

    require __DIR__ . '/footer.php';
}
