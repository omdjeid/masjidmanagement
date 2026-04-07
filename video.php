<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/admin.php';

require_role(['super_admin', 'admin', 'editor']);

function is_valid_youtube_url(string $url): bool
{
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }

    $parts = parse_url($url);
    if (!is_array($parts)) {
        return false;
    }

    $host = strtolower((string) ($parts['host'] ?? ''));

    return in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be', 'www.youtu.be', 'youtube-nocookie.com', 'www.youtube-nocookie.com'], true);
}

$errors = [];
$pageError = null;
$editingId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$videoCategories = fetch_master_categories('video');
$form = [
    'title' => '',
    'speaker' => '',
    'category' => '',
    'youtube_url' => '',
    'video_date' => '',
    'duration_minutes' => '',
    'faidah_points' => '',
    'status' => 'draft',
];
$records = [];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf_token('video.php');
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            db()->prepare('DELETE FROM videos WHERE id = :id')->execute(['id' => $id]);
            set_flash('success', 'Video berhasil dihapus.');
            redirect_to('/video.php');
        }

        $editingId = (int) ($_POST['id'] ?? 0);
        $form = [
            'title' => trim((string) ($_POST['title'] ?? '')),
            'speaker' => trim((string) ($_POST['speaker'] ?? '')),
            'category' => trim((string) ($_POST['category'] ?? '')),
            'youtube_url' => trim((string) ($_POST['youtube_url'] ?? '')),
            'video_date' => trim((string) ($_POST['video_date'] ?? '')),
            'duration_minutes' => trim((string) ($_POST['duration_minutes'] ?? '')),
            'faidah_points' => trim((string) ($_POST['faidah_points'] ?? '')),
            'status' => trim((string) ($_POST['status'] ?? 'draft')),
        ];

        if ($form['title'] === '') {
            $errors['title'] = 'Judul video wajib diisi.';
        }
        if ($form['speaker'] === '') {
            $errors['speaker'] = 'Nama pembicara wajib diisi.';
        }
        if ($form['youtube_url'] === '' || !is_valid_youtube_url($form['youtube_url'])) {
            $errors['youtube_url'] = 'Gunakan URL YouTube yang valid.';
        }

        if ($errors === []) {
            $payload = [
                'title' => $form['title'],
                'speaker' => $form['speaker'],
                'category' => $form['category'] !== '' ? $form['category'] : null,
                'youtube_url' => $form['youtube_url'],
                'video_date' => $form['video_date'] !== '' ? $form['video_date'] : null,
                'duration_minutes' => $form['duration_minutes'] !== '' ? (int) $form['duration_minutes'] : null,
                'summary' => $form['faidah_points'] !== '' ? $form['faidah_points'] : null,
                'status' => $form['status'],
            ];

            if ($action === 'update' && $editingId > 0) {
                $payload['id'] = $editingId;
                db()->prepare(
                    'UPDATE videos
                     SET title = :title, speaker = :speaker, category = :category, youtube_url = :youtube_url, video_date = :video_date,
                         duration_minutes = :duration_minutes, summary = :summary, status = :status
                     WHERE id = :id'
                )->execute($payload);
                set_flash('success', 'Video berhasil diperbarui.');
            } else {
                db()->prepare(
                    'INSERT INTO videos (title, speaker, category, youtube_url, video_date, duration_minutes, summary, status)
                     VALUES (:title, :speaker, :category, :youtube_url, :video_date, :duration_minutes, :summary, :status)'
                )->execute($payload);
                set_flash('success', 'Video baru berhasil ditambahkan.');
            }

            redirect_to('/video.php');
        }
    }

    if ($editingId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $statement = db()->prepare('SELECT * FROM videos WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $editingId]);
        $record = $statement->fetch();

        if ($record !== false) {
            $form = [
                'title' => (string) $record['title'],
                'speaker' => (string) $record['speaker'],
                'category' => (string) ($record['category'] ?? ''),
                'youtube_url' => (string) $record['youtube_url'],
                'video_date' => (string) ($record['video_date'] ?? ''),
                'duration_minutes' => $record['duration_minutes'] !== null ? (string) $record['duration_minutes'] : '',
                'faidah_points' => (string) ($record['summary'] ?? ''),
                'status' => (string) $record['status'],
            ];
        }
    }

    $records = db()->query(
        'SELECT * FROM videos ORDER BY video_date IS NULL, video_date DESC, id DESC'
    )->fetchAll();
} catch (Throwable) {
    $pageError = 'Modul video belum siap dipakai. Jalankan ulang `sql/schema.sql` agar tabel `videos` tersedia.';
}

render_admin_page_start('Video', 'video');
render_admin_page_header(
    'Arsip Kajian',
    'Kelola Video',
    'Simpan tautan video YouTube, pembicara, durasi, dan poin-poin faidah untuk kebutuhan archive masjid.',
    [
        ['href' => 'dashboard.php', 'label' => 'Kembali ke Dashboard', 'secondary' => true],
    ]
);
?>

            <?php if ($pageError !== null): ?>
                <div class="flash-message flash-message--error"><?= h($pageError); ?></div>
            <?php else: ?>
                <section class="content-grid content-grid--single">
                    <article class="card card--form">
                        <div class="card-heading">
                            <div>
                                <p class="eyebrow"><?= $editingId > 0 ? 'Edit Video' : 'Tambah Video'; ?></p>
                                <h2><?= $editingId > 0 ? 'Perbarui video' : 'Tambahkan video baru'; ?></h2>
                            </div>
                        </div>

                        <form method="post" class="admin-form">
                            <?= csrf_input(); ?>
                            <input type="hidden" name="action" value="<?= $editingId > 0 ? 'update' : 'create'; ?>">
                            <input type="hidden" name="id" value="<?= h((string) $editingId); ?>">

                            <div class="field-grid">
                                <div class="field-group field-group--full">
                                    <label for="title">Judul Video</label>
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
                                        <?php foreach ($videoCategories as $category): ?>
                                            <option value="<?= h($category); ?>" <?= $form['category'] === $category ? 'selected' : ''; ?>><?= h($category); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="field-group">
                                    <label for="video_date">Tanggal Video</label>
                                    <input class="admin-input" id="video_date" name="video_date" type="date" value="<?= h($form['video_date']); ?>">
                                </div>
                                <div class="field-group field-group--full">
                                    <label for="youtube_url">URL YouTube</label>
                                    <input class="admin-input" id="youtube_url" name="youtube_url" type="url" value="<?= h($form['youtube_url']); ?>">
                                    <?php if (isset($errors['youtube_url'])): ?><p class="field-error"><?= h($errors['youtube_url']); ?></p><?php endif; ?>
                                </div>
                                <div class="field-group">
                                    <label for="duration_minutes">Durasi (menit)</label>
                                    <input class="admin-input" id="duration_minutes" name="duration_minutes" type="number" min="0" value="<?= h($form['duration_minutes']); ?>">
                                </div>
                                <div class="field-group">
                                    <label for="status">Status</label>
                                    <select class="admin-input" id="status" name="status">
                                        <?php foreach (['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'] as $value => $label): ?>
                                            <option value="<?= h($value); ?>" <?= $form['status'] === $value ? 'selected' : ''; ?>><?= h($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="field-group field-group--full">
                                    <label for="faidah_points">Poin-Poin Faidah</label>
                                    <textarea
                                        class="admin-input"
                                        id="faidah_points"
                                        name="faidah_points"
                                        rows="7"
                                        placeholder="Tulis satu poin faidah per baris, misalnya:&#10;Pentingnya meluruskan niat dalam berumah tangga.&#10;Komunikasi dengan adab dan kasih sayang.&#10;Sabar dan syukur sebagai pilar utama rumah tangga."
                                    ><?= h($form['faidah_points']); ?></textarea>
                                    <p class="field-help">Setiap baris akan dibaca sebagai poin faidah pada halaman video publik.</p>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button class="button-link" type="submit"><?= $editingId > 0 ? 'Simpan Video' : 'Tambah Video'; ?></button>
                                <?php if ($editingId > 0): ?>
                                    <a class="button-link button-link--secondary" href="<?= h(app_url('video.php')); ?>">Batal Edit</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </article>
                </section>

                <section class="content-grid content-grid--single">
                    <article class="card card--list">
                        <div class="card-heading">
                            <div>
                                <p class="eyebrow">Arsip Video</p>
                                <h2>Video tersimpan</h2>
                            </div>
                            <div class="metric-chip"><?= h((string) count($records)); ?> data</div>
                        </div>

                        <?php if ($records === []): ?>
                            <div class="empty-state">
                                <h3>Belum ada video</h3>
                                <p>Tambahkan video pertama untuk mulai mengisi arsip kajian.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-wrap">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>Judul</th>
                                            <th>Kategori</th>
                                            <th>Pembicara</th>
                                            <th>Tanggal</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($records as $record): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= h($record['title']); ?></strong>
                                                    <span><?= h((string) $record['youtube_url']); ?></span>
                                                </td>
                                                <td><?= h((string) ($record['category'] ?? '-')); ?></td>
                                                <td><?= h($record['speaker']); ?></td>
                                                <td><?= h(format_human_date($record['video_date'] !== null ? (string) $record['video_date'] : null)); ?></td>
                                                <td><span class="<?= h(status_badge_class((string) $record['status'])); ?>"><?= h((string) $record['status']); ?></span></td>
                                                <td>
                                                    <div class="inline-actions">
                                                        <a class="button-small" href="<?= h(app_url('video.php')); ?>?edit=<?= h((string) $record['id']); ?>">Edit</a>
                                                        <form method="post" onsubmit="return confirm('Hapus video ini?');">
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
