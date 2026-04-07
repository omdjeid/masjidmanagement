<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/site.php';

header('Content-Type: text/plain; charset=UTF-8');

$publicRoot = rtrim(app_url(), '/');
if ($publicRoot === '') {
    $publicRoot = '/';
}

$dashboardPath = app_url('dashboard.php');
$loginPath = app_url('login.php');
$setupAdminPath = app_url('setup-admin.php');
$settingsPath = app_url('settings.php');
$sitemapUrl = absolute_app_url('sitemap.xml');

echo "User-agent: *\n";
echo "Allow: " . $publicRoot . "/\n";
echo "Disallow: " . $dashboardPath . "\n";
echo "Disallow: " . rtrim($dashboardPath, '/') . "/\n";
echo "Disallow: " . $loginPath . "\n";
echo "Disallow: " . $setupAdminPath . "\n";
echo "Disallow: " . $settingsPath . "\n";
echo "Sitemap: " . $sitemapUrl . "\n";
