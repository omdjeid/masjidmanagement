<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/admin.php';

require_role(['super_admin', 'admin', 'editor']);
sync_study_schedule_statuses();
sync_infaq_campaign_statuses();

function dashboard_count_value(?int $value): int
{
    return $value ?? 0;
}

function dashboard_recent_item(string $table, string $orderBy): ?array
{
    if (!preg_match('/^[a-z_]+$/', $table)) {
        return null;
    }

    try {
        $result = db()->query(sprintf('SELECT * FROM `%s` ORDER BY %s LIMIT 1', $table, $orderBy))->fetch();

        return $result !== false ? $result : null;
    } catch (Throwable) {
        return null;
    }
}

$user = current_user();
$stats = [
    'Kajian' => fetch_record_count('study_schedules'),
    'Artikel' => fetch_record_count('articles'),
    'Video' => fetch_record_count('videos'),
    'Infaq' => fetch_record_count('infaq_campaigns'),
];

$statValues = [
    'Kajian' => dashboard_count_value($stats['Kajian']),
    'Artikel' => dashboard_count_value($stats['Artikel']),
    'Video' => dashboard_count_value($stats['Video']),
    'Infaq' => dashboard_count_value($stats['Infaq']),
];

$summaryBars = [
    ['label' => 'Kajian', 'value' => $statValues['Kajian'], 'tone' => 'soft'],
    ['label' => 'Artikel', 'value' => $statValues['Artikel'], 'tone' => 'base'],
    ['label' => 'Video', 'value' => $statValues['Video'], 'tone' => 'accent'],
    ['label' => 'Infaq', 'value' => $statValues['Infaq'], 'tone' => 'soft'],
    ['label' => 'User', 'value' => dashboard_count_value(fetch_record_count('admin_users')), 'tone' => 'base'],
];

$maxBarValue = max(1, ...array_map(static fn (array $item): int => max(1, (int) $item['value']), $summaryBars));

$latestSchedule = null;
$latestArticle = null;
$latestVideo = null;
$latestCampaign = null;
$totalCollectedAmount = 0.0;
$activeCampaignCount = 0;
$scheduledKajianCount = 0;
$categoryCount = dashboard_count_value(fetch_record_count('master_categories'));
$tickerItems = configuration_lines('homepage_ticker_text', homepage_ticker_defaults());
$generalDefaults = general_setting_defaults();
$siteName = (string) (configuration_get('site_name', $generalDefaults['site_name']) ?? $generalDefaults['site_name']);
$canManageSettings = user_has_role('super_admin');

try {
    $latestSchedule = dashboard_recent_item('study_schedules', 'session_date DESC, start_time DESC, id DESC');
    $latestArticle = dashboard_recent_item('articles', 'COALESCE(published_at, created_at) DESC, id DESC');
    $latestVideo = dashboard_recent_item('videos', 'video_date IS NULL, video_date DESC, id DESC');
    $latestCampaign = dashboard_recent_item('infaq_campaigns', 'created_at DESC, id DESC');

    $infaqSummary = db()->query(
        "SELECT COALESCE(SUM(collected_amount), 0) AS total_collected,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_campaigns
         FROM infaq_campaigns"
    )->fetch();
    if ($infaqSummary !== false) {
        $totalCollectedAmount = (float) ($infaqSummary['total_collected'] ?? 0);
        $activeCampaignCount = (int) ($infaqSummary['active_campaigns'] ?? 0);
    }

    $kajianSummary = db()->query(
        "SELECT COUNT(*) AS total
         FROM study_schedules
         WHERE status = 'scheduled' AND session_date >= CURDATE()"
    )->fetch();
    if ($kajianSummary !== false) {
        $scheduledKajianCount = (int) ($kajianSummary['total'] ?? 0);
    }
} catch (Throwable) {
    // Keep dashboard usable with fallbacks if some tables are unavailable.
}

render_admin_page_start('Dashboard Admin', 'dashboard');
?>
            <section class="control-topbar">
                <div>
                    <p class="eyebrow">Mosque Management Portal</p>
                    <h1>Control Room</h1>
                    <p class="page-top__description">Ringkasan admin <?= h($siteName); ?> yang menampilkan konten, jadwal, video, campaign, dan setting aktif dalam satu control room.</p>
                </div>
                <div class="control-topbar__meta">
                    <div class="control-chip control-chip--alert">
                        <span class="material-symbols-outlined" aria-hidden="true">notifications</span>
                        <span><?= h((string) count($tickerItems)); ?> ticker aktif</span>
                    </div>
                    <div class="control-chip">
                        <span class="material-symbols-outlined" aria-hidden="true">account_circle</span>
                        <span><?= h($user['full_name'] ?? 'Admin'); ?></span>
                    </div>
                </div>
            </section>

            <section class="dashboard-overview">
                <article class="dashboard-analytics">
                    <div class="dashboard-analytics__head">
                        <div>
                            <p class="eyebrow">Overview</p>
                            <h2>Ringkasan Modul</h2>
                        </div>
                        <div class="dashboard-switches">
                            <span class="switch-chip is-active">Total</span>
                            <span class="switch-chip">Aktif</span>
                        </div>
                    </div>
                    <div class="bar-chart" aria-label="Statistik modul admin">
                        <?php foreach ($summaryBars as $bar): ?>
                            <?php $height = max(18, (int) round(((int) $bar['value'] / $maxBarValue) * 160)); ?>
                            <div class="bar-chart__item">
                                <div class="bar-chart__value"><?= h((string) $bar['value']); ?></div>
                                <div class="bar-chart__bar bar-chart__bar--<?= h((string) $bar['tone']); ?>" style="height: <?= h((string) $height); ?>px;"></div>
                                <span class="bar-chart__label"><?= h((string) $bar['label']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>

                <div class="dashboard-aside-stats">
                    <article class="dashboard-stat dashboard-stat--primary">
                        <p>Total Infaq Terkumpul</p>
                        <strong><?= h(format_currency($totalCollectedAmount)); ?></strong>
                        <span><?= h((string) $activeCampaignCount); ?> campaign aktif sedang berjalan.</span>
                    </article>
                    <article class="dashboard-stat">
                        <p>Kajian Terjadwal</p>
                        <strong><?= h((string) $scheduledKajianCount); ?></strong>
                        <span>Agenda yang masih upcoming dan siap tampil ke publik.</span>
                    </article>
                </div>
            </section>

            <section class="dashboard-wizard">
                <div class="dashboard-wizard__head">
                    <div>
                        <p class="eyebrow">Quick Actions</p>
                        <h2>Publikasi & Operasional</h2>
                    </div>
                </div>

                <div class="dashboard-wizard__card">
                    <div class="quick-action-grid">
                        <a class="quick-action" href="<?= h(app_url('kajian.php')); ?>">
                            <span class="material-symbols-outlined" aria-hidden="true">schedule</span>
                            <strong><?= h((string) ($latestSchedule['title'] ?? 'Tambah Kajian Baru')); ?></strong>
                            <p><?= h($latestSchedule !== null ? ((string) $latestSchedule['speaker'] . ' - ' . format_human_date((string) $latestSchedule['session_date'])) : 'Buat agenda kajian baru untuk jamaah.'); ?></p>
                        </a>
                        <a class="quick-action" href="<?= h(app_url('artikel.php')); ?>">
                            <span class="material-symbols-outlined" aria-hidden="true">edit_note</span>
                            <strong><?= h((string) ($latestArticle['title'] ?? 'Tulis Artikel Baru')); ?></strong>
                            <p><?= h($latestArticle !== null ? ((string) $latestArticle['author'] . ' - ' . format_human_date((string) ($latestArticle['published_at'] ?? $latestArticle['created_at']), true)) : 'Masuk ke editor artikel untuk update editorial.'); ?></p>
                        </a>
                        <a class="quick-action" href="<?= h(app_url('gallery.php')); ?>">
                            <span class="material-symbols-outlined" aria-hidden="true">photo_library</span>
                            <strong>Gallery Upload</strong>
                            <p>Lihat semua gambar lokal yang sudah diupload ke server beserta relasinya ke artikel.</p>
                        </a>
                        <a class="quick-action" href="<?= h(app_url('video.php')); ?>">
                            <span class="material-symbols-outlined" aria-hidden="true">video_library</span>
                            <strong><?= h((string) ($latestVideo['title'] ?? 'Upload Video Baru')); ?></strong>
                            <p><?= h($latestVideo !== null ? ((string) $latestVideo['speaker'] . ' - ' . format_human_date((string) ($latestVideo['video_date'] ?? ''), false)) : 'Tambahkan video kajian terbaru dari YouTube.'); ?></p>
                        </a>
                        <a class="quick-action" href="<?= h(app_url('infaq.php')); ?>">
                            <span class="material-symbols-outlined" aria-hidden="true">payments</span>
                            <strong><?= h((string) ($latestCampaign['title'] ?? 'Kelola Campaign Infaq')); ?></strong>
                            <p><?= h($latestCampaign !== null ? ('Terkumpul ' . format_currency((float) $latestCampaign['collected_amount']) . ' dari target ' . format_currency((float) $latestCampaign['target_amount'])) : 'Atur campaign infaq dan progres donasi.'); ?></p>
                        </a>
                    </div>
                </div>
            </section>

            <section class="dashboard-footer-links">
                <?php if ($canManageSettings): ?>
                    <a href="<?= h(app_url('settings.php')); ?>">Settings</a>
                <?php endif; ?>
                <a href="<?= h(app_url('kajian.php')); ?>">Kelola Kajian</a>
                <a href="<?= h(app_url('artikel.php')); ?>">Editor Artikel</a>
                <a href="<?= h(app_url('gallery.php')); ?>">Gallery Upload</a>
                <a href="<?= h(app_url('laporan-admin.php')); ?>">Kelola Laporan</a>
                <a href="<?= h(app_url('video.php')); ?>">Upload Video</a>
            </section>
<?php
render_admin_page_end();
