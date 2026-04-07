<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/site.php';
sync_study_schedule_statuses();

$filter = trim((string) ($_GET['category'] ?? ''));
$notice = null;
$categories = [];
$schedules = [];

try {
    $categories = db()->query(
        "SELECT DISTINCT category FROM study_schedules
         WHERE category IS NOT NULL AND category <> ''
         ORDER BY category ASC"
    )->fetchAll(PDO::FETCH_COLUMN) ?: [];

    $sql = 'SELECT * FROM study_schedules';
    $params = [];
    if ($filter !== '') {
        $sql .= ' WHERE category = :category';
        $params['category'] = $filter;
    }
    $sql .= ' ORDER BY session_date ASC, start_time ASC, id ASC';
    $statement = db()->prepare($sql);
    $statement->execute($params);
    $schedules = $statement->fetchAll();
} catch (Throwable) {
    $notice = 'Data jadwal belum dapat dimuat saat ini.';
}

$schedules = array_map(static fn (array $schedule): array => array_merge($schedule, [
    'status' => resolve_study_schedule_status($schedule),
]), $schedules);

$featured = $schedules[0] ?? null;
$siteName = configured_site_name();

public_page_start('Jadwal Kajian', 'jadwal');
?>
        <?php if ($notice !== null): ?>
            <div class="status-notice"><p class="content-copy"><?= h($notice); ?></p></div>
        <?php endif; ?>

        <section class="page-intro">
            <div>
                <p class="eyebrow">Agenda Ibadah</p>
                <h1>Jadwal Kajian<br><?= h($siteName); ?></h1>
            </div>
            <div class="filter-row">
                <a class="chip-link<?= $filter === '' ? ' is-active' : ''; ?>" href="<?= h(app_url('jadwal.php')); ?>">Semua</a>
                <?php foreach ($categories as $category): ?>
                    <a class="chip-link<?= $filter === (string) $category ? ' is-active' : ''; ?>" href="<?= h(app_url('jadwal.php')); ?>?category=<?= h(urlencode((string) $category)); ?>">
                        <?= h((string) $category); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if ($featured !== null): ?>
            <section class="schedule-layout">
                <article class="feature-card">
                    <p class="eyebrow"><?= (string) $featured['session_date'] === date('Y-m-d') ? 'Hari Ini' : 'Terdekat'; ?></p>
                    <h2><?= h((string) $featured['title']); ?></h2>
                    <p class="content-copy"><?= h((string) ($featured['summary'] ?? ('Kajian pilihan untuk jamaah ' . $siteName . '.'))); ?></p>
                    <div class="feature-meta">
                        <span><?= h((string) $featured['speaker']); ?></span>
                        <span><?= h(format_human_date((string) $featured['session_date'])); ?> - <?= h(substr((string) $featured['start_time'], 0, 5)); ?> WIB</span>
                        <span><?= h((string) $featured['location']); ?></span>
                    </div>
                    <div class="feature-meta">
                        <?php if ((string) $featured['status'] !== 'completed' && trim((string) ($featured['live_url'] ?? '')) !== ''): ?>
                            <a class="button-primary" href="<?= h((string) $featured['live_url']); ?>" target="_blank" rel="noopener noreferrer">Tonton Live</a>
                        <?php else: ?>
                            <a class="button-primary" href="<?= h(app_url('video-detail.php')); ?>">Lihat Rekaman Terkait</a>
                        <?php endif; ?>
                    </div>
                </article>

                <div>
                    <div class="timeline-stack">
                        <?php foreach ($schedules as $schedule): ?>
                            <article class="timeline-card">
                                <div class="timeline-date">
                                    <strong><?= h(date('d', strtotime((string) $schedule['session_date']))); ?></strong>
                                    <span><?= h(date('M', strtotime((string) $schedule['session_date']))); ?></span>
                                </div>
                                <div>
                                    <div class="timeline-card__meta">
                                        <span class="status-badge status-badge--<?= h((string) $schedule['status']); ?>"><?= h((string) ($schedule['category'] ?: $schedule['status'])); ?></span>
                                        <span class="meta-text"><?= h(substr((string) $schedule['start_time'], 0, 5)); ?> WIB</span>
                                    </div>
                                    <h3><?= h((string) $schedule['title']); ?></h3>
                                    <p><?= h((string) $schedule['speaker']); ?></p>
                                    <div class="timeline-footer">
                                        <span><?= h((string) $schedule['location']); ?></span>
                                        <?php if ((string) $schedule['status'] === 'completed'): ?>
                                            <a class="section-link" href="<?= h(app_url('video-detail.php')); ?>">Lihat Rekaman</a>
                                        <?php elseif (trim((string) ($schedule['live_url'] ?? '')) !== ''): ?>
                                            <a class="section-link" href="<?= h((string) $schedule['live_url']); ?>" target="_blank" rel="noopener noreferrer">Tonton Live</a>
                                        <?php else: ?>
                                            <a class="section-link" href="<?= h(app_url('jadwal.php')); ?>">Ingatkan Saya</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <section class="newsletter-card">
                        <p class="eyebrow">Langganan</p>
                        <h2 class="section-title">Jangan Lewatkan Ilmu Bermanfaat</h2>
                        <p>Dapatkan notifikasi jadwal kajian terbaru langsung ke perangkat Anda setiap minggunya.</p>
                        <form class="newsletter-form">
                            <input class="public-input" type="email" placeholder="Alamat Email">
                            <button class="button-primary" type="button">Berlangganan</button>
                        </form>
                    </section>
                </div>
            </section>
        <?php else: ?>
            <div class="status-notice">
                <p class="content-copy">Belum ada jadwal kajian yang dipublikasikan saat ini.</p>
            </div>
        <?php endif; ?>
<?php
public_page_end();
