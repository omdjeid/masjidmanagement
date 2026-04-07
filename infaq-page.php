<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/site.php';

$notice = null;
$campaigns = [];
$featuredCampaign = null;
sync_infaq_campaign_statuses();

try {
    $campaigns = db()->query(
        "SELECT * FROM infaq_campaigns
         WHERE status IN ('active', 'completed', 'archived')
         ORDER BY FIELD(status, 'active', 'completed', 'archived'), created_at DESC, id DESC"
    )->fetchAll();
} catch (Throwable) {
    $notice = 'Data infaq belum dapat dimuat saat ini.';
}

$featuredCampaign = $campaigns[0] ?? null;
$featuredStatus = $featuredCampaign !== null ? resolve_infaq_campaign_status($featuredCampaign) : 'draft';
$featuredProgress = infaq_progress_metrics((float) ($featuredCampaign['target_amount'] ?? 0), (float) ($featuredCampaign['collected_amount'] ?? 0));
$siteName = configured_site_name();

public_page_start('Infaq & Sadaqah', 'infaq');
?>
        <?php if ($notice !== null): ?>
            <div class="status-notice"><p class="content-copy"><?= h($notice); ?></p></div>
        <?php endif; ?>

        <section class="page-intro">
            <div>
                <p class="eyebrow">Sacred Action</p>
                <h1>Infaq &<br>Sadaqah</h1>
                <p class="content-copy">Ruang publik untuk mendukung program pembangunan, perawatan, dan pelayanan <?= h($siteName); ?> dengan nuansa visual yang selaras dengan referensi stitch.</p>
            </div>
            <a class="button-secondary" href="<?= h(app_url()); ?>">Kembali ke Home</a>
        </section>

        <?php if ($featuredCampaign !== null): ?>
            <section class="infaq-layout">
                <article class="feature-card">
                    <p class="eyebrow">Campaign Utama</p>
                    <h2><?= h((string) $featuredCampaign['title']); ?></h2>
                    <p class="content-copy"><?= h((string) ($featuredCampaign['description'] ?? ('Dukung kebutuhan utama ' . $siteName . ' melalui campaign infaq ini.'))); ?></p>
                    <div class="feature-meta">
                        <span>Periode: <?= h(format_human_date((string) ($featuredCampaign['start_date'] ?? ''))); ?> - <?= h(format_human_date((string) ($featuredCampaign['end_date'] ?? ''))); ?></span>
                        <span>Status: <?= h($featuredStatus); ?></span>
                    </div>
                    <div class="donation-progress-card">
                        <div class="donation-progress-card__top">
                            <span>Terkumpul</span>
                            <strong><?= h(format_currency((float) $featuredCampaign['collected_amount'])); ?></strong>
                        </div>
                        <div class="donation-progress">
                            <div class="donation-progress__fill" style="width: <?= h((string) $featuredProgress['fill_percent']); ?>%"></div>
                        </div>
                        <div class="donation-progress-card__meta">
                            <span>Target: <?= h(format_currency((float) $featuredCampaign['target_amount'])); ?></span>
                            <span><?= h((string) $featuredProgress['percent']); ?>% tercapai</span>
                        </div>
                    </div>
                </article>

                <div class="campaign-stack">
                    <?php foreach ($campaigns as $campaign): ?>
                        <?php $progress = infaq_progress_metrics((float) $campaign['target_amount'], (float) $campaign['collected_amount']); ?>
                        <?php $campaignStatus = resolve_infaq_campaign_status($campaign); ?>
                        <article class="campaign-card">
                            <div class="campaign-card__head">
                                <div>
                                    <span class="status-badge status-badge--<?= h($campaignStatus); ?>"><?= h($campaignStatus); ?></span>
                                    <h3><?= h((string) $campaign['title']); ?></h3>
                                </div>
                                <strong><?= h(format_currency((float) $campaign['collected_amount'])); ?></strong>
                            </div>
                            <p><?= h((string) ($campaign['description'] ?? ('Campaign infaq ' . $siteName . '.'))); ?></p>
                            <div class="donation-progress">
                                <div class="donation-progress__fill" style="width: <?= h((string) $progress['fill_percent']); ?>%"></div>
                            </div>
                            <div class="campaign-card__meta">
                                <span>Target: <?= h(format_currency((float) $campaign['target_amount'])); ?></span>
                                <span><?= h((string) $progress['percent']); ?>% tercapai</span>
                                <span>Periode: <?= h(format_human_date((string) ($campaign['start_date'] ?? ''))); ?> - <?= h(format_human_date((string) ($campaign['end_date'] ?? ''))); ?></span>
                            </div>
                        </article>
                    <?php endforeach; ?>

                    <article class="newsletter-card">
                        <p class="eyebrow">Ajak Kebaikan</p>
                        <h2 class="section-title">Sebarkan Campaign Infaq</h2>
                        <p>Bantu lebih banyak jamaah mengetahui program infaq <?= h($siteName); ?> dan dukung target kebaikan bersama.</p>
                        <?php
                        $shareText = 'Mari dukung campaign infaq ' . $siteName . ': '
                            . (string) ($featuredCampaign['title'] ?? 'Campaign Infaq')
                            . '. Terkumpul '
                            . format_currency((float) ($featuredCampaign['collected_amount'] ?? 0))
                            . ' dari target '
                            . format_currency((float) ($featuredCampaign['target_amount'] ?? 0))
                            . '. ' . absolute_app_url('infaq-page.php');
                        $whatsAppShareUrl = 'https://wa.me/?text=' . rawurlencode($shareText);
                        ?>
                        <div class="newsletter-form">
                            <a class="button-primary" href="<?= h($whatsAppShareUrl); ?>" target="_blank" rel="noopener noreferrer">Bagikan via WhatsApp</a>
                        </div>
                    </article>
                </div>
            </section>
        <?php else: ?>
            <div class="status-notice">
                <p class="content-copy">Belum ada campaign infaq yang dipublikasikan saat ini.</p>
            </div>
        <?php endif; ?>
<?php
public_page_end();
