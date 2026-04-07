<?php
declare(strict_types=1);

$layout = $GLOBALS['_public_layout_data'] ?? [];
$siteName = (string) ($layout['site_name'] ?? configured_site_name());
$title = (string) ($layout['title'] ?? $siteName);
$activeNav = (string) ($layout['active_nav'] ?? '');
$navItems = is_array($layout['nav_items'] ?? null) ? $layout['nav_items'] : [];
$pageTitleTag = page_title_tag($title, $siteName);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitleTag); ?></title>
    <?php render_site_meta_tags($layout); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= h(asset_url('css/public-pages.css')); ?>">
    <?php render_google_analytics_tag(); ?>
</head>
<body>
    <header class="public-header">
        <div class="public-header__inner">
            <a class="public-brand" href="<?= h(app_url()); ?>"><?= h($siteName); ?></a>
            <nav class="public-nav" aria-label="Navigasi publik">
                <?php foreach ($navItems as $key => $item): ?>
                    <a class="<?= $activeNav === $key ? 'is-active' : ''; ?>" href="<?= h((string) ($item['href'] ?? app_url())); ?>"><?= h((string) ($item['label'] ?? '')); ?></a>
                <?php endforeach; ?>
            </nav>
        </div>
    </header>
    <main class="public-main">
