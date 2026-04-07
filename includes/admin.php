<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/configuration.php';

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function is_safe_rich_text_link(string $value): bool
{
    $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    if ($value === '' || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
        return false;
    }

    return preg_match('/^(https?:|mailto:|tel:|#|\/)/i', $value) === 1;
}

function is_safe_rich_text_image_src(string $value): bool
{
    $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    if ($value === '' || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
        return false;
    }

    return preg_match('/^(https?:|\/)/i', $value) === 1;
}

function sanitize_rich_text_html_fallback(string $html): string
{
    $html = trim(str_replace(["\r\n", "\r"], "\n", $html));

    if ($html === '') {
        return '';
    }

    $html = preg_replace('/<(\/?)div\b[^>]*>/i', '<$1p>', $html) ?? $html;
    $html = preg_replace('/<(\/?)b(\s|>)/i', '<$1strong$2', $html) ?? $html;
    $html = preg_replace('/<(\/?)i(\s|>)/i', '<$1em$2', $html) ?? $html;
    $html = strip_tags($html, '<p><br><strong><em><u><ul><ol><li><h2><h3><blockquote><a><img>');
    $html = preg_replace('/\s+on\w+="[^"]*"/i', '', $html) ?? $html;
    $html = preg_replace("/\s+on\w+='[^']*'/i", '', $html) ?? $html;
    $html = preg_replace('/\s+style="[^"]*"/i', '', $html) ?? $html;
    $html = preg_replace("/\s+style='[^']*'/i", '', $html) ?? $html;

    return trim($html);
}

function sanitize_rich_text_node(DOMNode $node): void
{
    if ($node instanceof DOMComment && $node->parentNode instanceof DOMNode) {
        $node->parentNode->removeChild($node);
        return;
    }

    $children = [];
    foreach ($node->childNodes as $child) {
        $children[] = $child;
    }

    foreach ($children as $child) {
        sanitize_rich_text_node($child);
    }

    if (!$node instanceof DOMElement) {
        return;
    }

    $allowedTags = ['p', 'br', 'strong', 'em', 'u', 'ul', 'ol', 'li', 'h2', 'h3', 'blockquote', 'a', 'img'];
    $tagName = strtolower($node->tagName);

    if ($tagName === 'body') {
        return;
    }

    if (!in_array($tagName, $allowedTags, true)) {
        if (!$node->parentNode instanceof DOMNode) {
            return;
        }

        if (in_array($tagName, ['script', 'style', 'iframe', 'object', 'embed', 'link', 'meta'], true)) {
            $node->parentNode->removeChild($node);
            return;
        }

        while ($node->firstChild instanceof DOMNode) {
            $node->parentNode->insertBefore($node->firstChild, $node);
        }

        $node->parentNode->removeChild($node);
        return;
    }

    $allowedAttributes = match ($tagName) {
        'a' => ['href'],
        'img' => ['src', 'alt'],
        default => [],
    };

    $attributeNames = [];
    foreach ($node->attributes ?? [] as $attribute) {
        $attributeNames[] = $attribute->nodeName;
    }

    foreach ($attributeNames as $attributeName) {
        if (!in_array(strtolower($attributeName), $allowedAttributes, true)) {
            $node->removeAttribute($attributeName);
        }
    }

    if ($tagName === 'a') {
        $href = $node->getAttribute('href');
        if (!is_safe_rich_text_link($href)) {
            $node->removeAttribute('href');
        } else {
            $node->setAttribute('href', trim($href));

            if (preg_match('/^(https?:|mailto:|tel:)/i', trim($href)) === 1) {
                $node->setAttribute('target', '_blank');
                $node->setAttribute('rel', 'noopener noreferrer');
            }
        }

        return;
    }

    if ($tagName === 'img') {
        $src = $node->getAttribute('src');
        if (!is_safe_rich_text_image_src($src)) {
            if ($node->parentNode instanceof DOMNode) {
                $node->parentNode->removeChild($node);
            }
            return;
        }

        $node->setAttribute('src', trim($src));
        $node->setAttribute('alt', trim($node->getAttribute('alt')));
    }
}

function sanitize_rich_text_html(string $html): string
{
    $html = trim(str_replace(["\r\n", "\r"], "\n", $html));

    if ($html === '') {
        return '';
    }

    if (!class_exists('DOMDocument')) {
        return sanitize_rich_text_html_fallback($html);
    }

    $document = new DOMDocument('1.0', 'UTF-8');
    $wrappedHtml = '<!DOCTYPE html><html><body>' . $html . '</body></html>';
    $encodedHtml = function_exists('mb_convert_encoding')
        ? mb_convert_encoding($wrappedHtml, 'HTML-ENTITIES', 'UTF-8')
        : $wrappedHtml;

    $previousState = libxml_use_internal_errors(true);
    $loaded = $document->loadHTML($encodedHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previousState);

    if ($loaded !== true) {
        return sanitize_rich_text_html_fallback($html);
    }

    $body = $document->getElementsByTagName('body')->item(0);
    if (!$body instanceof DOMElement) {
        return sanitize_rich_text_html_fallback($html);
    }

    sanitize_rich_text_node($body);

    $sanitizedHtml = '';
    foreach ($body->childNodes as $child) {
        $sanitizedHtml .= $document->saveHTML($child);
    }

    return trim($sanitizedHtml);
}

function redirect_to(string $path): void
{
    header('Location: ' . app_url($path));
    exit;
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function get_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return is_array($flash) ? $flash : null;
}

function normalize_slug(string $text): string
{
    $text = trim($text);
    $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $normalized = strtolower($transliterated !== false ? $transliterated : $text);
    $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? '';
    $normalized = trim($normalized, '-');

    return $normalized !== '' ? $normalized : 'artikel';
}

function format_currency(float $amount): string
{
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function format_datetime_local(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    $date = date_create($value);

    return $date instanceof DateTimeInterface ? $date->format('Y-m-d\TH:i') : '';
}

function format_human_date(?string $value, bool $withTime = false): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    $date = date_create($value);

    if (!$date instanceof DateTimeInterface) {
        return '-';
    }

    return $date->format($withTime ? 'd M Y H:i' : 'd M Y');
}

function status_badge_class(string $status): string
{
    return match ($status) {
        'draft' => 'badge badge--draft',
        'scheduled' => 'badge badge--scheduled',
        'published' => 'badge badge--published',
        'active' => 'badge badge--active',
        'completed' => 'badge badge--completed',
        'archived' => 'badge badge--archived',
        default => 'badge',
    };
}

function infaq_completion_modes(): array
{
    return [
        'date' => 'Sesuai tanggal',
        'amount' => 'Sesuai jumlah',
    ];
}

function infaq_completion_mode_label(string $mode): string
{
    $modes = infaq_completion_modes();

    return $modes[$mode] ?? 'Sesuai tanggal';
}

function infaq_progress_metrics(float $targetAmount, float $collectedAmount): array
{
    if ($targetAmount <= 0) {
        return [
            'percent' => 0,
            'fill_percent' => 0,
        ];
    }

    $percent = (int) round(($collectedAmount / $targetAmount) * 100);

    return [
        'percent' => $percent,
        'fill_percent' => max(0, min(100, $percent)),
    ];
}

function resolve_infaq_campaign_status(array $campaign, ?DateTimeInterface $today = null): string
{
    $storedStatus = (string) ($campaign['status'] ?? 'active');

    if ($storedStatus === 'draft' || $storedStatus === 'archived') {
        return $storedStatus;
    }

    $completionMode = (string) ($campaign['completion_mode'] ?? 'date');
    $targetAmount = (float) ($campaign['target_amount'] ?? 0);
    $collectedAmount = (float) ($campaign['collected_amount'] ?? 0);

    if ($completionMode === 'amount') {
        return ($targetAmount > 0 && $collectedAmount >= $targetAmount) ? 'completed' : 'active';
    }

    $today = $today ?? new DateTimeImmutable('today');
    $endDateValue = trim((string) ($campaign['end_date'] ?? ''));

    if ($endDateValue !== '') {
        $endDate = date_create($endDateValue);
        if ($endDate instanceof DateTimeInterface && $endDate->format('Y-m-d') <= $today->format('Y-m-d')) {
            return 'completed';
        }
    }

    return 'active';
}

function sync_infaq_campaign_statuses(): void
{
    try {
        db()->exec(
            "UPDATE infaq_campaigns
             SET status = 'completed'
             WHERE status NOT IN ('draft', 'archived')
               AND completion_mode = 'date'
               AND end_date IS NOT NULL
               AND end_date <= CURDATE()"
        );

        db()->exec(
            "UPDATE infaq_campaigns
             SET status = 'completed'
             WHERE status NOT IN ('draft', 'archived')
               AND completion_mode = 'amount'
               AND target_amount > 0
               AND collected_amount >= target_amount"
        );

        db()->exec(
            "UPDATE infaq_campaigns
             SET status = 'active'
             WHERE status = 'completed'
               AND completion_mode = 'date'
               AND (end_date IS NULL OR end_date > CURDATE())"
        );

        db()->exec(
            "UPDATE infaq_campaigns
             SET status = 'active'
             WHERE status = 'completed'
               AND completion_mode = 'amount'
               AND (target_amount <= 0 OR collected_amount < target_amount)"
        );
    } catch (Throwable) {
        // Keep pages usable even if schema is not updated yet.
    }
}

function resolve_study_schedule_status(array $schedule, ?DateTimeInterface $now = null): string
{
    $storedStatus = (string) ($schedule['status'] ?? 'scheduled');

    if ($storedStatus === 'draft' || $storedStatus === 'archived') {
        return $storedStatus;
    }

    $sessionDate = trim((string) ($schedule['session_date'] ?? ''));
    $startTime = trim((string) ($schedule['start_time'] ?? ''));
    $endTime = trim((string) ($schedule['end_time'] ?? ''));
    $compareTime = $endTime !== '' ? $endTime : $startTime;

    if ($sessionDate === '' || $compareTime === '') {
        return $storedStatus;
    }

    $now = $now ?? new DateTimeImmutable('now');
    $scheduleDateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $sessionDate . ' ' . $compareTime);

    if (!$scheduleDateTime instanceof DateTimeImmutable) {
        return $storedStatus;
    }

    return $scheduleDateTime <= $now ? 'completed' : 'scheduled';
}

function sync_study_schedule_statuses(): void
{
    try {
        db()->exec(
            "UPDATE study_schedules
             SET status = 'completed'
             WHERE status NOT IN ('draft', 'archived')
               AND TIMESTAMP(session_date, COALESCE(end_time, start_time)) <= NOW()"
        );

        db()->exec(
            "UPDATE study_schedules
             SET status = 'scheduled'
             WHERE status = 'completed'
               AND TIMESTAMP(session_date, COALESCE(end_time, start_time)) > NOW()"
        );
    } catch (Throwable) {
        // Keep pages usable even if schema is not updated yet.
    }
}

function fetch_record_count(string $table): ?int
{
    if (!preg_match('/^[a-z_]+$/', $table)) {
        return null;
    }

    try {
        $result = db()->query(sprintf('SELECT COUNT(*) AS total FROM `%s`', $table))->fetch();

        return $result !== false ? (int) $result['total'] : 0;
    } catch (Throwable) {
        return null;
    }
}

function admin_nav_items(): array
{
    $items = [
        ['key' => 'dashboard', 'label' => 'Ringkasan', 'href' => '/dashboard.php'],
        ['key' => 'kajian', 'label' => 'Jadwal Kajian', 'href' => '/kajian.php'],
        ['key' => 'artikel', 'label' => 'Artikel', 'href' => '/artikel.php'],
        ['key' => 'gallery', 'label' => 'Gallery', 'href' => '/gallery.php'],
        ['key' => 'video', 'label' => 'Video', 'href' => '/video.php'],
        ['key' => 'infaq', 'label' => 'Infaq', 'href' => '/infaq.php'],
        ['key' => 'laporan', 'label' => 'Laporan', 'href' => '/laporan-admin.php'],
    ];

    if (user_has_role('super_admin')) {
        $items[] = ['key' => 'settings', 'label' => 'Setting', 'href' => '/settings.php'];
    }

    return $items;
}

function render_admin_page_start(string $pageTitle, string $activeNav): void
{
    $user = current_user();
    $generalDefaults = general_setting_defaults();
    $siteName = (string) (configuration_get('site_name', $generalDefaults['site_name']) ?? $generalDefaults['site_name']);
    $siteTagline = trim((string) (configuration_get('site_tagline', $generalDefaults['site_tagline']) ?? $generalDefaults['site_tagline']));
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle); ?> | <?= h($siteName); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:FILL@0..1" rel="stylesheet">
    <link rel="stylesheet" href="<?= h(asset_url('css/dashboard.css')); ?>">
</head>
<body>
    <main class="dashboard-shell">
        <aside class="sidebar">
            <div class="sidebar__brand">
                <a href="<?= h(app_url('dashboard.php')); ?>"><?= h($siteName); ?></a>
                <?php if ($siteTagline !== ''): ?>
                    <p><?= h($siteTagline); ?></p>
                <?php endif; ?>
            </div>

            <nav class="sidebar__nav" aria-label="Menu admin">
                <?php foreach (admin_nav_items() as $item): ?>
                    <a class="<?= $item['key'] === $activeNav ? 'is-active' : ''; ?>" href="<?= h(app_url($item['href'])); ?>">
                        <?= h($item['label']); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="sidebar__support">
                <p>Login sebagai <?= h($user['full_name'] ?? 'Admin'); ?>.</p>
                <form method="post" action="<?= h(app_url('logout.php')); ?>">
                    <?= csrf_input(); ?>
                    <button type="submit">Keluar</button>
                </form>
            </div>
        </aside>

        <section class="dashboard-content">
    <?php
}

function render_admin_page_header(string $eyebrow, string $title, string $description, array $actions = []): void
{
    $flash = get_flash();
    ?>
            <section class="page-top">
                <div class="page-top__content">
                    <p class="eyebrow"><?= h($eyebrow); ?></p>
                    <h1><?= h($title); ?></h1>
                    <p class="page-top__description"><?= h($description); ?></p>
                </div>
                <?php if ($actions !== []): ?>
                    <div class="page-actions">
                        <?php foreach ($actions as $action): ?>
                            <a class="button-link<?= !empty($action['secondary']) ? ' button-link--secondary' : ''; ?>" href="<?= h(app_url($action['href'])); ?>">
                                <?= h($action['label']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <?php if ($flash !== null): ?>
                <div class="flash-message flash-message--<?= h($flash['type'] ?? 'success'); ?>">
                    <?= h($flash['message'] ?? 'Perubahan berhasil disimpan.'); ?>
                </div>
            <?php endif; ?>
    <?php
}

function render_admin_page_end(): void
{
    ?>
        </section>
    </main>
</body>
</html>
    <?php
}
