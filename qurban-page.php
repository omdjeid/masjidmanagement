<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/site.php';

$selectedYear = isset($_GET['tahun']) ? (int) $_GET['tahun'] : 0;
$notice = null;
$years = [];
$participants = [];
$selectedYearAnimalCounts = [];
$qurbanGroups = [];
$goatParticipants = [];
$displayParticipantCount = 0;
$registeredParticipantCount = 0;

try {
    $years = db()->query(
        "SELECT DISTINCT hijri_year
         FROM qurban_participants
         WHERE status = 'published'
         ORDER BY hijri_year DESC"
    )->fetchAll(PDO::FETCH_COLUMN) ?: [];

    $years = array_values(array_map(static fn (mixed $year): int => (int) $year, $years));

    if ($selectedYear <= 0 || !in_array($selectedYear, $years, true)) {
        $selectedYear = $years[0] ?? 0;
    }

    if ($selectedYear > 0) {
        $statement = db()->prepare(
            "SELECT *
             FROM qurban_participants
             WHERE status = 'published' AND hijri_year = :hijri_year
             ORDER BY sort_order ASC, COALESCE(group_name, '') ASC, participant_name ASC, id ASC"
        );
        $statement->execute(['hijri_year' => $selectedYear]);
        $participants = $statement->fetchAll();
    }
} catch (Throwable) {
    $notice = 'Data peserta qurban belum dapat dimuat saat ini.';
}

foreach ($participants as $participant) {
    $registeredParticipantCount++;
    $animalType = (string) ($participant['animal_type'] ?? 'kambing');
    $animalLabel = qurban_animal_label($animalType);
    $rowCount = qurban_public_row_count($animalType, (int) ($participant['share_count'] ?? 1));
    $selectedYearAnimalCounts[$animalLabel] = ($selectedYearAnimalCounts[$animalLabel] ?? 0) + $rowCount;
    $displayParticipantCount += $rowCount;

    if ($animalType === 'sapi') {
        $groupName = trim((string) ($participant['group_name'] ?? ''));
        if ($groupName === '') {
            $groupName = 'Kelompok Sapi';
        }

        if (!isset($qurbanGroups[$groupName])) {
            $qurbanGroups[$groupName] = [];
        }

        for ($i = 0; $i < $rowCount; $i++) {
            $expandedParticipant = $participant;
            $qurbanGroups[$groupName][] = $expandedParticipant;
        }
        continue;
    }

    $goatParticipants[] = $participant;
}

$siteName = configured_site_name();
$selectedYearLabel = $selectedYear > 0 ? qurban_hijri_year_label($selectedYear) : '-';

public_page_start(
    'Peserta Qurban',
    'qurban',
    [
        'description' => 'Daftar peserta qurban per tahun Hijriah di ' . $siteName . '. Pilih tahun untuk melihat peserta yang tampil pada periode tersebut.',
    ]
);
?>
        <?php if ($notice !== null): ?>
            <div class="status-notice"><p class="content-copy"><?= h($notice); ?></p></div>
        <?php endif; ?>

        <section class="page-intro">
            <div>
                <p class="eyebrow">Qurban</p>
                <h1>Peserta Qurban</h1>
                <p class="content-copy">Pilih tahun Hijriah untuk melihat daftar peserta qurban yang dipublikasikan oleh pengelola <?= h($siteName); ?>.</p>
            </div>
            <div class="filter-row">
                <?php foreach ($years as $year): ?>
                    <a class="chip-link<?= $selectedYear === $year ? ' is-active' : ''; ?>" href="<?= h(app_url('qurban-page.php')); ?>?tahun=<?= h((string) $year); ?>">
                        <?= h(qurban_hijri_year_label($year)); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if ($participants !== []): ?>
            <section class="qurban-layout">
                <article class="feature-card">
                    <p class="eyebrow">Tahun Terpilih</p>
                    <h2><?= h($selectedYearLabel); ?></h2>
                    <p class="content-copy">Daftar peserta qurban yang tampil untuk periode <?= h($selectedYearLabel); ?>. Jika Anda memilih tahun lain, hanya peserta pada tahun itu yang akan muncul.</p>
                    <div class="feature-meta">
                        <span>Total Peserta: <?= h((string) $registeredParticipantCount); ?> Orang</span>
                        <span><?= h($siteName); ?></span>
                    </div>
                    <div class="qurban-payment-note">
                        <span><span class="qurban-paid-mark" aria-hidden="true">✅</span> Sudah membayar</span>
                        <span>Tanpa tanda: Belum membayar</span>
                    </div>
                    <?php if ($selectedYearAnimalCounts !== []): ?>
                        <ul class="qurban-summary-list">
                            <?php foreach ($selectedYearAnimalCounts as $animalLabel => $total): ?>
                                <li>
                                    <span><?= h($animalLabel); ?></span>
                                    <strong><?= h((string) $total); ?> peserta</strong>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </article>

                <div class="qurban-groups-stack campaign-stack">
                    <?php if ($qurbanGroups !== []): ?>
                        <section class="qurban-groups-card list-card">
                            <div class="qurban-groups-card__head">
                                <div>
                                    <p class="eyebrow">Kelompok Sapi</p>
                                    <h3>Peserta per Kelompok</h3>
                                </div>
                                <span class="qurban-groups-card__count"><?= h((string) count($qurbanGroups)); ?> kelompok</span>
                            </div>

                            <div class="qurban-groups-grid">
                                <?php foreach ($qurbanGroups as $groupName => $groupParticipants): ?>
                                    <article class="qurban-group-card campaign-card">
                                        <h4><?= h($groupName); ?></h4>
                                        <ol class="qurban-group-list">
                                            <?php foreach ($groupParticipants as $participant): ?>
                                                <li>
                                                    <strong>
                                                        <?= h((string) $participant['participant_name']); ?>
                                                        <?php if ((int) ($participant['is_paid'] ?? 0) === 1): ?>
                                                            <span class="qurban-paid-mark" aria-label="Sudah bayar" title="Sudah bayar">✅</span>
                                                        <?php endif; ?>
                                                    </strong>
                                                    <?php if (trim((string) ($participant['notes'] ?? '')) !== ''): ?>
                                                        <small><?= h((string) $participant['notes']); ?></small>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ol>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <?php if ($goatParticipants !== []): ?>
                        <section class="qurban-groups-card list-card">
                            <div class="qurban-groups-card__head">
                                <div>
                                    <p class="eyebrow">Qurban Kambing</p>
                                    <h3>Peserta Kambing</h3>
                                </div>
                                <span class="qurban-groups-card__count"><?= h((string) count($goatParticipants)); ?> peserta</span>
                            </div>

                            <div class="qurban-goat-card campaign-card">
                                <ol class="qurban-group-list">
                                    <?php foreach ($goatParticipants as $participant): ?>
                                        <li>
                                            <strong>
                                                <?= h((string) $participant['participant_name']); ?>
                                                <?php if ((int) ($participant['is_paid'] ?? 0) === 1): ?>
                                                    <span class="qurban-paid-mark" aria-label="Sudah bayar" title="Sudah bayar">✅</span>
                                                <?php endif; ?>
                                            </strong>
                                            <?php if (trim((string) ($participant['notes'] ?? '')) !== ''): ?>
                                                <small><?= h((string) $participant['notes']); ?></small>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            </div>
                        </section>
                    <?php endif; ?>
                </div>
            </section>
        <?php else: ?>
            <div class="status-notice">
                <p class="content-copy">
                    <?= $years === []
                        ? 'Belum ada peserta qurban yang dipublikasikan saat ini.'
                        : 'Belum ada peserta qurban untuk tahun yang dipilih.'; ?>
                </p>
            </div>
        <?php endif; ?>
<?php
public_page_end();
