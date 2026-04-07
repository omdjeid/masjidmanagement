<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/admin.php';

require_role(['super_admin', 'admin', 'editor']);
sync_study_schedule_statuses();

$errors = [];
$pageError = null;
$editingId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$kajianCategories = fetch_master_categories('study_schedule');
$form = [
    'title' => '',
    'speaker' => '',
    'category' => '',
    'session_date' => '',
    'start_time' => '',
    'end_time' => '',
    'live_url' => '',
    'location' => '',
    'summary' => '',
    'status' => 'scheduled',
];
$records = [];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf_token('kajian.php');
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            db()->prepare('DELETE FROM study_schedules WHERE id = :id')->execute(['id' => $id]);
            set_flash('success', 'Jadwal kajian berhasil dihapus.');
            redirect_to('/kajian.php');
        }

        $editingId = (int) ($_POST['id'] ?? 0);
        $form = [
            'title' => trim((string) ($_POST['title'] ?? '')),
            'speaker' => trim((string) ($_POST['speaker'] ?? '')),
            'category' => trim((string) ($_POST['category'] ?? '')),
            'session_date' => trim((string) ($_POST['session_date'] ?? '')),
            'start_time' => trim((string) ($_POST['start_time'] ?? '')),
            'end_time' => trim((string) ($_POST['end_time'] ?? '')),
            'live_url' => trim((string) ($_POST['live_url'] ?? '')),
            'location' => trim((string) ($_POST['location'] ?? '')),
            'summary' => trim((string) ($_POST['summary'] ?? '')),
            'status' => trim((string) ($_POST['status'] ?? 'scheduled')),
        ];

        if ($form['title'] === '') {
            $errors['title'] = 'Judul kajian wajib diisi.';
        }
        if ($form['speaker'] === '') {
            $errors['speaker'] = 'Nama pembicara wajib diisi.';
        }
        if ($form['session_date'] === '') {
            $errors['session_date'] = 'Tanggal kajian wajib diisi.';
        }
        if ($form['start_time'] === '') {
            $errors['start_time'] = 'Jam mulai wajib diisi.';
        }
        if ($form['location'] === '') {
            $errors['location'] = 'Lokasi kajian wajib diisi.';
        }
        if ($form['live_url'] !== '' && filter_var($form['live_url'], FILTER_VALIDATE_URL) === false) {
            $errors['live_url'] = 'Link live harus berupa URL yang valid.';
        }

        if ($errors === []) {
            $draftPayload = [
                'session_date' => $form['session_date'],
                'start_time' => $form['start_time'] !== '' ? $form['start_time'] . ':00' : '',
                'end_time' => $form['end_time'] !== '' ? $form['end_time'] . ':00' : null,
                'status' => $form['status'],
            ];

            $payload = [
                'title' => $form['title'],
                'speaker' => $form['speaker'],
                'category' => $form['category'] !== '' ? $form['category'] : null,
                'session_date' => $form['session_date'],
                'start_time' => $form['start_time'],
                'end_time' => $form['end_time'] !== '' ? $form['end_time'] : null,
                'live_url' => $form['live_url'] !== '' ? $form['live_url'] : null,
                'location' => $form['location'],
                'summary' => $form['summary'] !== '' ? $form['summary'] : null,
                'status' => resolve_study_schedule_status($draftPayload),
            ];

            if ($action === 'update' && $editingId > 0) {
                $payload['id'] = $editingId;
                db()->prepare(
                    'UPDATE study_schedules
                     SET title = :title, speaker = :speaker, category = :category, session_date = :session_date,
                         start_time = :start_time, end_time = :end_time, live_url = :live_url, location = :location, summary = :summary, status = :status
                     WHERE id = :id'
                )->execute($payload);
                set_flash('success', 'Jadwal kajian berhasil diperbarui.');
            } else {
                db()->prepare(
                    'INSERT INTO study_schedules (title, speaker, category, session_date, start_time, end_time, live_url, location, summary, status)
                     VALUES (:title, :speaker, :category, :session_date, :start_time, :end_time, :live_url, :location, :summary, :status)'
                )->execute($payload);
                set_flash('success', 'Jadwal kajian baru berhasil ditambahkan.');
            }

            redirect_to('/kajian.php');
        }
    }

    if ($editingId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $statement = db()->prepare('SELECT * FROM study_schedules WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $editingId]);
        $record = $statement->fetch();

        if ($record !== false) {
            $form = [
                'title' => (string) $record['title'],
                'speaker' => (string) $record['speaker'],
                'category' => (string) ($record['category'] ?? ''),
                'session_date' => (string) $record['session_date'],
                'start_time' => substr((string) $record['start_time'], 0, 5),
                'end_time' => $record['end_time'] !== null ? substr((string) $record['end_time'], 0, 5) : '',
                'live_url' => (string) ($record['live_url'] ?? ''),
                'location' => (string) $record['location'],
                'summary' => (string) ($record['summary'] ?? ''),
                'status' => (string) $record['status'],
            ];
        }
    }

    $records = db()->query(
        'SELECT * FROM study_schedules ORDER BY session_date DESC, start_time DESC, id DESC'
    )->fetchAll();
} catch (Throwable) {
    $pageError = 'Modul jadwal kajian belum siap dipakai. Jalankan ulang `sql/schema.sql` agar tabel `study_schedules` tersedia.';
}

render_admin_page_start('Jadwal Kajian', 'kajian');
render_admin_page_header(
    'Agenda Ibadah',
    'Kelola Jadwal Kajian',
    'Tambahkan agenda baru, perbarui detail pembicara dan waktu, lalu rapikan arsip kegiatan kajian dari panel admin.',
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
                                <p class="eyebrow"><?= $editingId > 0 ? 'Edit Kajian' : 'Tambah Kajian'; ?></p>
                                <h2><?= $editingId > 0 ? 'Perbarui agenda' : 'Buat agenda baru'; ?></h2>
                            </div>
                        </div>

                        <form method="post" class="admin-form">
                            <?= csrf_input(); ?>
                            <input type="hidden" name="action" value="<?= $editingId > 0 ? 'update' : 'create'; ?>">
                            <input type="hidden" name="id" value="<?= h((string) $editingId); ?>">

                            <div class="field-grid">
                                <div class="field-group field-group--full">
                                    <label for="title">Judul Kajian</label>
                                    <input class="admin-input" id="title" name="title" type="text" value="<?= h($form['title']); ?>">
                                    <?php if (isset($errors['title'])): ?><p class="field-error"><?= h($errors['title']); ?></p><?php endif; ?>
                                </div>
                                <div class="field-group">
                                    <label for="speaker">Pembicara</label>
                                    <input class="admin-input" id="speaker" name="speaker" type="text" value="<?= h($form['speaker']); ?>">
                                    <?php if (isset($errors['speaker'])): ?><p class="field-error"><?= h($errors['speaker']); ?></p><?php endif; ?>
                                </div>
                                <div class="field-group">
                                    <label for="category">Kategori</label>
                                    <select class="admin-input" id="category" name="category">
                                        <option value="">Pilih kategori</option>
                                        <?php foreach ($kajianCategories as $category): ?>
                                            <option value="<?= h($category); ?>" <?= $form['category'] === $category ? 'selected' : ''; ?>><?= h($category); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="field-group">
                                    <label for="session_date">Tanggal</label>
                                    <input class="admin-input" id="session_date" name="session_date" type="date" value="<?= h($form['session_date']); ?>">
                                    <?php if (isset($errors['session_date'])): ?><p class="field-error"><?= h($errors['session_date']); ?></p><?php endif; ?>
                                </div>
                                <div class="field-group">
                                    <label for="start_time">Jam Mulai</label>
                                    <input class="admin-input" id="start_time" name="start_time" type="time" value="<?= h($form['start_time']); ?>">
                                    <?php if (isset($errors['start_time'])): ?><p class="field-error"><?= h($errors['start_time']); ?></p><?php endif; ?>
                                </div>
                                <div class="field-group">
                                    <label for="end_time">Jam Selesai</label>
                                    <input class="admin-input" id="end_time" name="end_time" type="time" value="<?= h($form['end_time']); ?>">
                                </div>
                                <div class="field-group">
                                    <label for="live_url">Link Live</label>
                                    <input class="admin-input" id="live_url" name="live_url" type="url" value="<?= h($form['live_url']); ?>" placeholder="https://youtube.com/...">
                                    <?php if (isset($errors['live_url'])): ?><p class="field-error"><?= h($errors['live_url']); ?></p><?php endif; ?>
                                </div>
                                <div class="field-group">
                                    <label for="location">Lokasi</label>
                                    <input class="admin-input" id="location" name="location" type="text" value="<?= h($form['location']); ?>">
                                    <?php if (isset($errors['location'])): ?><p class="field-error"><?= h($errors['location']); ?></p><?php endif; ?>
                                </div>
                                <div class="field-group">
                                    <label for="status">Status</label>
                                    <select class="admin-input" id="status" name="status">
                                        <?php foreach (['draft' => 'Draft', 'scheduled' => 'Scheduled', 'completed' => 'Completed', 'archived' => 'Archived'] as $value => $label): ?>
                                            <option value="<?= h($value); ?>" <?= $form['status'] === $value ? 'selected' : ''; ?>><?= h($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="field-group field-group--full">
                                    <label for="summary">Ringkasan</label>
                                    <textarea class="admin-input" id="summary" name="summary" rows="5"><?= h($form['summary']); ?></textarea>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button class="button-link" type="submit"><?= $editingId > 0 ? 'Simpan Perubahan' : 'Tambah Kajian'; ?></button>
                                <?php if ($editingId > 0): ?>
                                    <a class="button-link button-link--secondary" href="<?= h(app_url('kajian.php')); ?>">Batal Edit</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </article>

                    <article class="card card--list">
                        <div class="card-heading">
                            <div>
                                <p class="eyebrow">Daftar Kajian</p>
                                <h2>Agenda tersimpan</h2>
                            </div>
                            <div class="metric-chip"><?= h((string) count($records)); ?> data</div>
                        </div>

                        <?php if ($records === []): ?>
                            <div class="empty-state">
                                <h3>Belum ada jadwal kajian</h3>
                                <p>Mulai dengan menambahkan agenda pertama melalui form di samping.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-wrap">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>Judul</th>
                                            <th>Kategori</th>
                                            <th>Pembicara</th>
                                            <th>Jadwal</th>
                                            <th>Live</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($records as $record): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= h($record['title']); ?></strong>
                                                    <span><?= h($record['location']); ?></span>
                                                </td>
                                                <td><?= h((string) ($record['category'] ?? '-')); ?></td>
                                                <td><?= h($record['speaker']); ?></td>
                                                <td><?= h(format_human_date((string) $record['session_date'])); ?> • <?= h(substr((string) $record['start_time'], 0, 5)); ?></td>
                                                <td><?= trim((string) ($record['live_url'] ?? '')) !== '' ? 'Tersedia' : '-'; ?></td>
                                                <td><span class="<?= h(status_badge_class((string) $record['status'])); ?>"><?= h((string) $record['status']); ?></span></td>
                                                <td>
                                                    <div class="inline-actions">
                                                        <a class="button-small" href="<?= h(app_url('kajian.php')); ?>?edit=<?= h((string) $record['id']); ?>">Edit</a>
                                                        <form method="post" onsubmit="return confirm('Hapus jadwal ini?');">
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
