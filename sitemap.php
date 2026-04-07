<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/site.php';

function sitemap_lastmod(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return null;
    }

    return gmdate('c', $timestamp);
}

function sitemap_entry(string $path, ?string $lastmod = null, ?string $changefreq = null, ?string $priority = null): array
{
    return [
        'loc' => absolute_app_url($path),
        'lastmod' => $lastmod,
        'changefreq' => $changefreq,
        'priority' => $priority,
    ];
}

function sitemap_xml_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

$entries = [
    sitemap_entry('', sitemap_lastmod(date('c')), 'daily', '1.0'),
    sitemap_entry('jadwal.php', null, 'daily', '0.9'),
    sitemap_entry('artikel-list.php', null, 'daily', '0.9'),
    sitemap_entry('kajian-video.php', null, 'weekly', '0.8'),
    sitemap_entry('laporan.php', null, 'weekly', '0.8'),
    sitemap_entry('lokasi.php', null, 'monthly', '0.7'),
    sitemap_entry('infaq-page.php', null, 'weekly', '0.8'),
];

try {
    $latestArticle = db()->query(
        "SELECT MAX(COALESCE(updated_at, published_at, created_at)) AS lastmod
         FROM articles
         WHERE status = 'published'"
    )->fetch();
    if (is_array($latestArticle) && trim((string) ($latestArticle['lastmod'] ?? '')) !== '') {
        $entries[2]['lastmod'] = sitemap_lastmod((string) $latestArticle['lastmod']);
    }

    $latestVideo = db()->query(
        "SELECT MAX(COALESCE(updated_at, created_at)) AS lastmod
         FROM videos
         WHERE status = 'published'"
    )->fetch();
    if (is_array($latestVideo) && trim((string) ($latestVideo['lastmod'] ?? '')) !== '') {
        $entries[3]['lastmod'] = sitemap_lastmod((string) $latestVideo['lastmod']);
    }

    $latestReport = db()->query(
        "SELECT MAX(COALESCE(updated_at, published_at, created_at)) AS lastmod
         FROM reports
         WHERE status = 'published'"
    )->fetch();
    if (is_array($latestReport) && trim((string) ($latestReport['lastmod'] ?? '')) !== '') {
        $entries[4]['lastmod'] = sitemap_lastmod((string) $latestReport['lastmod']);
    }

    $latestInfaq = db()->query(
        "SELECT MAX(COALESCE(updated_at, created_at)) AS lastmod
         FROM infaq_campaigns
         WHERE status IN ('active', 'completed', 'archived')"
    )->fetch();
    if (is_array($latestInfaq) && trim((string) ($latestInfaq['lastmod'] ?? '')) !== '') {
        $entries[6]['lastmod'] = sitemap_lastmod((string) $latestInfaq['lastmod']);
    }

    $articleRows = db()->query(
        "SELECT slug, COALESCE(updated_at, published_at, created_at) AS lastmod
         FROM articles
         WHERE status = 'published'
         ORDER BY COALESCE(published_at, created_at) DESC, id DESC"
    )->fetchAll();
    foreach ($articleRows as $row) {
        $slug = trim((string) ($row['slug'] ?? ''));
        if ($slug === '') {
            continue;
        }

        $entries[] = sitemap_entry(
            'artikel-detail.php?slug=' . urlencode($slug),
            sitemap_lastmod((string) ($row['lastmod'] ?? '')),
            'monthly',
            '0.8'
        );
    }

    $videoRows = db()->query(
        "SELECT id, COALESCE(updated_at, created_at) AS lastmod
         FROM videos
         WHERE status = 'published'
         ORDER BY video_date IS NULL, video_date DESC, created_at DESC, id DESC"
    )->fetchAll();
    foreach ($videoRows as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }

        $entries[] = sitemap_entry(
            'video-detail.php?id=' . $id,
            sitemap_lastmod((string) ($row['lastmod'] ?? '')),
            'monthly',
            '0.8'
        );
    }

    $reportRows = db()->query(
        "SELECT slug, COALESCE(updated_at, published_at, created_at) AS lastmod
         FROM reports
         WHERE status = 'published'
         ORDER BY COALESCE(published_at, created_at) DESC, id DESC"
    )->fetchAll();
    foreach ($reportRows as $row) {
        $slug = trim((string) ($row['slug'] ?? ''));
        if ($slug === '') {
            continue;
        }

        $entries[] = sitemap_entry(
            'laporan-detail.php?slug=' . urlencode($slug),
            sitemap_lastmod((string) ($row['lastmod'] ?? '')),
            'monthly',
            '0.8'
        );
    }
} catch (Throwable) {
    // Tetap kirim sitemap dasar walau database belum siap.
}

header('Content-Type: application/xml; charset=UTF-8');
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($entries as $entry): ?>
  <url>
    <loc><?= sitemap_xml_escape((string) $entry['loc']); ?></loc>
<?php if ($entry['lastmod'] !== null): ?>
    <lastmod><?= sitemap_xml_escape((string) $entry['lastmod']); ?></lastmod>
<?php endif; ?>
<?php if ($entry['changefreq'] !== null): ?>
    <changefreq><?= sitemap_xml_escape((string) $entry['changefreq']); ?></changefreq>
<?php endif; ?>
<?php if ($entry['priority'] !== null): ?>
    <priority><?= sitemap_xml_escape((string) $entry['priority']); ?></priority>
<?php endif; ?>
  </url>
<?php endforeach; ?>
</urlset>
