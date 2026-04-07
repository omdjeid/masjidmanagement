<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/admin.php';

require_role(['super_admin', 'admin', 'editor']);
sync_infaq_campaign_statuses();

$errors = [];
$pageError = null;
$editingId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$completionModes = infaq_completion_modes();
$form = [
    'title' => '',
    'description' => '',
    'completion_mode' => 'date',
    'target_amount' => '',
    'collected_amount' => '',
    'start_date' => '',
    'end_date' => '',
    'status' => 'active',
];
$records = [];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf_token('infaq.php');
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            db()->prepare('DELETE FROM infaq_campaigns WHERE id = :id')->execute(['id' => $id]);
            set_flash('success', 'Campaign infaq berhasil dihapus.');
            redirect_to('/infaq.php');
        }

        $editingId = (int) ($_POST['id'] ?? 0);
        $form = [
            'title' => trim((string) ($_POST['title'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'completion_mode' => trim((string) ($_POST['completion_mode'] ?? 'date')),
            'target_amount' => trim((string) ($_POST['target_amount'] ?? '')),
            'collected_amount' => trim((string) ($_POST['collected_amount'] ?? '')),
            'start_date' => trim((string) ($_POST['start_date'] ?? '')),
            'end_date' => trim((string) ($_POST['end_date'] ?? '')),
            'status' => trim((string) ($_POST['status'] ?? 'active')),
        ];

        if ($form['title'] === '') {
            $errors['title'] = 'Judul campaign wajib diisi.';
        }
        if (!array_key_exists($form['completion_mode'], $completionModes)) {
            $errors['completion_mode'] = 'Metode penyelesaian campaign tidak valid.';
        }
        if ($form['target_amount'] === '' || !is_numeric($form['target_amount'])) {
            $errors['target_amount'] = 'Target nominal wajib berupa angka.';
        } elseif ((float) $form['target_amount'] < 0) {
            $errors['target_amount'] = 'Target nominal tidak boleh negatif.';
        }
        if ($form['collected_amount'] !== '' && !is_numeric($form['collected_amount'])) {
            $errors['collected_amount'] = 'Nominal terkumpul harus berupa angka.';
        } elseif ($form['collected_amount'] !== '' && (float) $form['collected_amount'] < 0) {
            $errors['collected_amount'] = 'Nominal terkumpul tidak boleh negatif.';
        }
        if ($form['completion_mode'] === 'date' && $form['end_date'] === '') {
            $errors['end_date'] = 'Tanggal selesai wajib diisi jika penyelesaian berdasarkan tanggal.';
        }

        if ($errors === []) {
            $draftPayload = [
                'completion_mode' => $form['completion_mode'],
                'target_amount' => (float) $form['target_amount'],
                'collected_amount' => $form['collected_amount'] !== '' ? (float) $form['collected_amount'] : 0.0,
                'end_date' => $form['end_date'] !== '' ? $form['end_date'] : null,
                'status' => $form['status'],
            ];

            $payload = [
                'title' => $form['title'],
                'description' => $form['description'] !== '' ? $form['description'] : null,
                'completion_mode' => $form['completion_mode'],
                'target_amount' => (float) $form['target_amount'],
                'collected_amount' => $form['collected_amount'] !== '' ? (float) $form['collected_amount'] : 0.0,
                'start_date' => $form['start_date'] !== '' ? $form['start_date'] : null,
                'end_date' => $form['end_date'] !== '' ? $form['end_date'] : null,
                'status' => resolve_infaq_campaign_status($draftPayload),
            ];

            if ($action === 'update' && $editingId > 0) {
                $payload['id'] = $editingId;
                db()->prepare(
                    'UPDATE infaq_campaigns
                     SET title = :title, description = :description, completion_mode = :completion_mode, target_amount = :target_amount, collected_amount = :collected_amount,
                         start_date = :start_date, end_date = :end_date, status = :status
                     WHERE id = :id'
                )->execute($payload);
                set_flash('success', 'Campaign infaq berhasil diperbarui.');
            } else {
                db()->prepare(
                    'INSERT INTO infaq_campaigns (title, description, completion_mode, target_amount, collected_amount, start_date, end_date, status)
                     VALUES (:title, :description, :completion_mode, :target_amount, :collected_amount, :start_date, :end_date, :status)'
                )->execute($payload);
                set_flash('success', 'Campaign infaq baru berhasil ditambahkan.');
            }

            redirect_to('/infaq.php');
        }
    }

    if ($editingId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $statement = db()->prepare('SELECT * FROM infaq_campaigns WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $editingId]);
        $record = $statement->fetch();

        if ($record !== false) {
            $form = [
                'title' => (string) $record['title'],
                'description' => (string) ($record['description'] ?? ''),
                'completion_mode' => (string) ($record['completion_mode'] ?? 'date'),
                'target_amount' => (string) $record['target_amount'],
                'collected_amount' => (string) $record['collected_amount'],
                'start_date' => (string) ($record['start_date'] ?? ''),
                'end_date' => (string) ($record['end_date'] ?? ''),
                'status' => (string) $record['status'],
            ];
        }
    }

    $records = db()->query(
        'SELECT * FROM infaq_campaigns ORDER BY created_at DESC, id DESC'
    )->fetchAll();
} catch (Throwable) {
    $pageError = 'Modul infaq belum siap dipakai. Jalankan ulang `sql/schema.sql` agar tabel `infaq_campaigns` tersedia.';
}

render_admin_page_start('Infaq', 'infaq');
render_admin_page_header(
    'Infaq & Sadaqah',
    'Kelola Campaign Infaq',
    'Atur campaign donasi, target nominal, progres terkumpul, dan periode campaign langsung dari panel admin.',
    [
        ['href' => 'dashboard.php', 'label' => 'Kembali ke Dashboard', 'secondary' => true],
    ]
);
?>

            <?php if ($pageError !== null): ?>
                <div class="flash-message flash-message--error"><?= h($pageError); ?></div>
            <?php else: ?>
                <section class="content-grid">
                    <article class="card card--form">
                        <div class="card-heading">
                            <div>
                                <p class="eyebrow"><?= $editingId > 0 ? 'Edit Infaq' : 'Tambah Infaq'; ?></p>
                                <h2><?= $editingId > 0 ? 'Perbarui campaign' : 'Buat campaign baru'; ?></h2>
                            </div>
                        </div>

                        <form method="post" class="admin-form">
                            <?= csrf_input(); ?>
                            <input type="hidden" name="action" value="<?= $editingId > 0 ? 'update' : 'create'; ?>">
                            <input type="hidden" name="id" value="<?= h((string) $editingId); ?>">

                            <div class="field-grid">
                                <div class="field-group field-group--full">
                                    <label for="title">Judul Campaign</label>
                                    <input class="admin-input" id="title" name="title" type="text" value="<?= h($form['title']); ?>">
                                    <?php if (isset($errors['title'])): ?><p class="field-error"><?= h($errors['title']); ?></p><?php endif; ?>
                                </div>
                                <div class="field-group">
                                    <label for="completion_mode">Selesaikan Campaign</label>
                                    <select class="admin-input" id="completion_mode" name="completion_mode">
                                        <?php foreach ($completionModes as $value => $label): ?>
                                            <option value="<?= h($value); ?>" <?= $form['completion_mode'] === $value ? 'selected' : ''; ?>><?= h($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="field-help">Jika sesuai tanggal, status akan otomatis menjadi completed saat tanggal selesai tercapai. Jika sesuai jumlah, status akan otomatis menjadi completed saat nominal terkumpul mencapai target.</p>
                                    <?php if (isset($errors['completion_mode'])): ?><p class="field-error"><?= h($errors['completion_mode']); ?></p><?php endif; ?>
                                </div>
                                <div class="field-group">
                                    <label for="target_amount">Target Nominal</label>
                                    <input class="admin-input" id="target_amount" name="target_amount" type="number" min="0" step="0.01" value="<?= h($form['target_amount']); ?>">
                                    <?php if (isset($errors['target_amount'])): ?><p class="field-error"><?= h($errors['target_amount']); ?></p><?php endif; ?>
                                </div>
                                <div class="field-group">
                                    <label for="collected_amount">Nominal Terkumpul</label>
                                    <input class="admin-input" id="collected_amount" name="collected_amount" type="number" min="0" step="0.01" value="<?= h($form['collected_amount']); ?>">
                                    <?php if (isset($errors['collected_amount'])): ?><p class="field-error"><?= h($errors['collected_amount']); ?></p><?php endif; ?>
                                </div>
                                <div class="field-group">
                                    <label for="start_date">Tanggal Mulai</label>
                                    <input class="admin-input" id="start_date" name="start_date" type="date" value="<?= h($form['start_date']); ?>">
                                </div>
                                <div class="field-group">
                                    <label for="end_date">Tanggal Selesai</label>
                                    <input class="admin-input" id="end_date" name="end_date" type="date" value="<?= h($form['end_date']); ?>">
                                    <?php if (isset($errors['end_date'])): ?><p class="field-error"><?= h($errors['end_date']); ?></p><?php endif; ?>
                                </div>
                                <div class="field-group">
                                    <label for="status">Status</label>
                                    <select class="admin-input" id="status" name="status">
                                        <?php foreach (['draft' => 'Draft', 'active' => 'Active', 'completed' => 'Completed', 'archived' => 'Archived'] as $value => $label): ?>
                                            <option value="<?= h($value); ?>" <?= $form['status'] === $value ? 'selected' : ''; ?>><?= h($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="field-help">Status `completed` akan dihitung ulang otomatis sesuai metode penyelesaian campaign.</p>
                                </div>
                                <div class="field-group field-group--full">
                                    <label for="description">Deskripsi</label>
                                    <textarea class="admin-input" id="description" name="description" rows="6"><?= h($form['description']); ?></textarea>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button class="button-link" type="submit"><?= $editingId > 0 ? 'Simpan Campaign' : 'Tambah Campaign'; ?></button>
                                <?php if ($editingId > 0): ?>
                                    <a class="button-link button-link--secondary" href="<?= h(app_url('infaq.php')); ?>">Batal Edit</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </article>

                    <article class="card card--list">
                        <div class="card-heading">
                            <div>
                                <p class="eyebrow">Daftar Campaign</p>
                                <h2>Campaign infaq tersimpan</h2>
                            </div>
                            <div class="metric-chip"><?= h((string) count($records)); ?> data</div>
                        </div>

                        <?php if ($records === []): ?>
                            <div class="empty-state">
                                <h3>Belum ada campaign infaq</h3>
                                <p>Mulai dengan membuat target infaq pertama dari form di samping.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-wrap">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>Campaign</th>
                                            <th>Metode</th>
                                            <th>Target</th>
                                            <th>Terkumpul</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($records as $record): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= h($record['title']); ?></strong>
                                                    <span><?= h(format_human_date($record['start_date'] !== null ? (string) $record['start_date'] : null)); ?> - <?= h(format_human_date($record['end_date'] !== null ? (string) $record['end_date'] : null)); ?></span>
                                                </td>
                                                <td><?= h(infaq_completion_mode_label((string) ($record['completion_mode'] ?? 'date'))); ?></td>
                                                <td><?= h(format_currency((float) $record['target_amount'])); ?></td>
                                                <td><?= h(format_currency((float) $record['collected_amount'])); ?></td>
                                                <td><span class="<?= h(status_badge_class((string) $record['status'])); ?>"><?= h((string) $record['status']); ?></span></td>
                                                <td>
                                                    <div class="inline-actions">
                                                        <a class="button-small" href="<?= h(app_url('infaq.php')); ?>?edit=<?= h((string) $record['id']); ?>">Edit</a>
                                                        <form method="post" onsubmit="return confirm('Hapus campaign ini?');">
                                                            <?= csrf_input(); ?>
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="id" value="<?= h((string) $record['id']); ?>">
                                                            <button class="button-small button-small--danger" type="submit">Hapus</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </article>
                </section>
            <?php endif; ?>
<?php
render_admin_page_end();
