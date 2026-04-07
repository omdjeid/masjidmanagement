<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/admin.php';

require_role(['super_admin', 'admin', 'editor']);

function sanitize_report_html(string $html): string
{
    return sanitize_rich_text_html($html);
}

function generate_report_excerpt(string $html, int $limit = 180): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');

    if ($text === '') {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $limit - 1)) . '...';
    }

    if (strlen($text) <= $limit) {
        return $text;
    }

    return rtrim(substr($text, 0, $limit - 1)) . '...';
}

function normalize_gallery_urls(string $value): string
{
    $lines = preg_split('/\R+/', trim($value)) ?: [];
    $urls = [];

    foreach ($lines as $line) {
        $url = trim($line);
        if ($url === '') {
            continue;
        }

        if (
            preg_match('/^(https?:\/\/|\/)/i', $url) !== 1
            && preg_match('/^[a-z0-9._-]+\.[a-z]{2,}/i', $url) !== 1
        ) {
            continue;
        }

        $urls[] = $url;
    }

    return implode("\n", array_values(array_unique($urls)));
}

$errors = [];
$pageError = null;
$editingId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$reportCategories = fetch_master_categories('report');
$form = [
    'title' => '',
    'slug' => '',
    'author' => '',
    'category' => '',
    'period_label' => '',
    'excerpt' => '',
    'body' => '',
    'featured_image' => '',
    'attachment_url' => '',
    'gallery_urls' => '',
    'published_at' => '',
    'status' => 'draft',
];
$records = [];
$siteName = configured_site_name();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf_token('laporan-admin.php');
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            db()->prepare('DELETE FROM reports WHERE id = :id')->execute(['id' => $id]);
            set_flash('success', 'Laporan berhasil dihapus.');
            redirect_to('/laporan-admin.php');
        }

        $editingId = (int) ($_POST['id'] ?? 0);
        $form = [
            'title' => trim((string) ($_POST['title'] ?? '')),
            'slug' => trim((string) ($_POST['slug'] ?? '')),
            'author' => trim((string) ($_POST['author'] ?? '')),
            'category' => trim((string) ($_POST['category'] ?? '')),
            'period_label' => trim((string) ($_POST['period_label'] ?? '')),
            'excerpt' => trim((string) ($_POST['excerpt'] ?? '')),
            'body' => trim((string) ($_POST['body'] ?? '')),
            'featured_image' => trim((string) ($_POST['featured_image'] ?? '')),
            'attachment_url' => trim((string) ($_POST['attachment_url'] ?? '')),
            'gallery_urls' => trim((string) ($_POST['gallery_urls'] ?? '')),
            'published_at' => trim((string) ($_POST['published_at'] ?? '')),
            'status' => trim((string) ($_POST['status'] ?? 'draft')),
        ];

        if ($form['title'] === '') {
            $errors['title'] = 'Judul laporan wajib diisi.';
        }
        if ($form['author'] === '') {
            $errors['author'] = 'Nama penulis atau unit wajib diisi.';
        }

        $sanitizedBody = sanitize_report_html($form['body']);
        if (trim(strip_tags($sanitizedBody)) === '') {
            $errors['body'] = 'Isi laporan wajib diisi.';
        }

        $slug = normalize_slug($form['slug'] !== '' ? $form['slug'] : $form['title']);
        $publishedAt = $form['published_at'] !== '' ? date('Y-m-d H:i:s', strtotime($form['published_at'])) : null;

        if ($errors === []) {
            $slugCheck = db()->prepare('SELECT id FROM reports WHERE slug = :slug LIMIT 1');
            $slugCheck->execute(['slug' => $slug]);
            $existingSlug = $slugCheck->fetch();

            if ($existingSlug !== false && (int) $existingSlug['id'] !== $editingId) {
                $errors['slug'] = 'Slug ini sudah dipakai laporan lain.';
            }
        }

        if ($errors === []) {
            $generatedExcerpt = generate_report_excerpt($sanitizedBody);
            $galleryUrls = normalize_gallery_urls($form['gallery_urls']);
            $payload = [
                'title' => $form['title'],
                'slug' => $slug,
                'author' => $form['author'],
                'category' => $form['category'] !== '' ? $form['category'] : null,
                'period_label' => $form['period_label'] !== '' ? $form['period_label'] : null,
                'excerpt' => $form['excerpt'] !== '' ? $form['excerpt'] : ($generatedExcerpt !== '' ? $generatedExcerpt : null),
                'body' => $sanitizedBody,
                'featured_image' => $form['featured_image'] !== '' ? $form['featured_image'] : null,
                'attachment_url' => $form['attachment_url'] !== '' ? $form['attachment_url'] : null,
                'gallery_urls' => $galleryUrls !== '' ? $galleryUrls : null,
                'published_at' => $publishedAt,
                'status' => $form['status'],
            ];

            if ($action === 'update' && $editingId > 0) {
                $payload['id'] = $editingId;
                db()->prepare(
                    'UPDATE reports
                     SET title = :title, slug = :slug, author = :author, category = :category, period_label = :period_label,
                         excerpt = :excerpt, body = :body, featured_image = :featured_image, attachment_url = :attachment_url,
                         gallery_urls = :gallery_urls,
                         published_at = :published_at, status = :status
                     WHERE id = :id'
                )->execute($payload);
                set_flash('success', 'Laporan berhasil diperbarui.');
            } else {
                db()->prepare(
                    'INSERT INTO reports (title, slug, author, category, period_label, excerpt, body, featured_image, attachment_url, gallery_urls, published_at, status)
                     VALUES (:title, :slug, :author, :category, :period_label, :excerpt, :body, :featured_image, :attachment_url, :gallery_urls, :published_at, :status)'
                )->execute($payload);
                set_flash('success', 'Laporan baru berhasil ditambahkan.');
            }

            redirect_to('/laporan-admin.php');
        }
    }

    if ($editingId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $statement = db()->prepare('SELECT * FROM reports WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $editingId]);
        $record = $statement->fetch();

        if ($record !== false) {
            $form = [
                'title' => (string) $record['title'],
                'slug' => (string) $record['slug'],
                'author' => (string) $record['author'],
                'category' => (string) ($record['category'] ?? ''),
                'period_label' => (string) ($record['period_label'] ?? ''),
                'excerpt' => (string) ($record['excerpt'] ?? ''),
                'body' => (string) $record['body'],
                'featured_image' => (string) ($record['featured_image'] ?? ''),
                'attachment_url' => (string) ($record['attachment_url'] ?? ''),
                'gallery_urls' => (string) ($record['gallery_urls'] ?? ''),
                'published_at' => format_datetime_local($record['published_at'] !== null ? (string) $record['published_at'] : null),
                'status' => (string) $record['status'],
            ];
        }
    }

    $records = db()->query(
        'SELECT * FROM reports ORDER BY COALESCE(published_at, created_at) DESC, id DESC'
    )->fetchAll();
} catch (Throwable) {
    $pageError = 'Modul laporan belum siap dipakai. Jalankan ulang `sql/schema.sql` agar tabel `reports` tersedia.';
}

render_admin_page_start('Laporan', 'laporan');
render_admin_page_header(
    'Public Reports',
    'Kelola Laporan',
    'Atur laporan kegiatan, laporan keuangan bulanan, dan dokumen publik lain yang akan tampil di halaman laporan website.',
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
                                <p class="eyebrow"><?= $editingId > 0 ? 'Edit Laporan' : 'Tambah Laporan'; ?></p>
                                <h2><?= $editingId > 0 ? 'Perbarui laporan' : 'Tulis laporan baru'; ?></h2>
                            </div>
                        </div>

                        <form method="post" class="admin-form article-editor-form">
                            <?= csrf_input(); ?>
                            <input type="hidden" name="action" value="<?= $editingId > 0 ? 'update' : 'create'; ?>">
                            <input type="hidden" name="id" value="<?= h((string) $editingId); ?>">

                            <div class="field-grid">
                                <div class="field-group field-group--full">
                                    <label for="title">Judul</label>
                                    <input class="admin-input" id="title" name="title" type="text" value="<?= h($form['title']); ?>">
                                    <?php if (isset($errors['title'])): ?><p class="field-error"><?= h($errors['title']); ?></p><?php endif; ?>
                                </div>
                                <div class="field-group">
                                    <label for="slug">Slug</label>
                                    <input class="admin-input" id="slug" name="slug" type="text" value="<?= h($form['slug']); ?>" placeholder="otomatis-jika-dikosongkan">
                                    <?php if (isset($errors['slug'])): ?><p class="field-error"><?= h($errors['slug']); ?></p><?php endif; ?>
                                </div>
                                <div class="field-group">
                                    <label for="author">Penulis / Unit</label>
                                    <input class="admin-input" id="author" name="author" type="text" value="<?= h($form['author']); ?>">
                                    <?php if (isset($errors['author'])): ?><p class="field-error"><?= h($errors['author']); ?></p><?php endif; ?>
                                </div>
                                <div class="field-group">
                                    <label for="category">Kategori Laporan</label>
                                    <select class="admin-input" id="category" name="category">
                                        <option value="">Pilih kategori</option>
                                        <?php foreach ($reportCategories as $category): ?>
                                            <option value="<?= h($category); ?>" <?= $form['category'] === $category ? 'selected' : ''; ?>><?= h($category); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="field-group">
                                    <label for="period_label">Periode</label>
                                    <input class="admin-input" id="period_label" name="period_label" type="text" value="<?= h($form['period_label']); ?>" placeholder="Contoh: Maret 2026">
                                </div>
                                <div class="field-group">
                                    <label for="published_at">Waktu Publikasi</label>
                                    <input class="admin-input" id="published_at" name="published_at" type="datetime-local" value="<?= h($form['published_at']); ?>">
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
                                    <label for="featured_image">URL Featured Image</label>
                                    <input class="admin-input" id="featured_image" name="featured_image" type="url" value="<?= h($form['featured_image']); ?>">
                                </div>
                                <div class="field-group field-group--full">
                                    <label for="attachment_url">URL Lampiran</label>
                                    <input class="admin-input" id="attachment_url" name="attachment_url" type="url" value="<?= h($form['attachment_url']); ?>" placeholder="Opsional: link PDF atau dokumen lain">
                                    <p class="field-help">Jika lampiran berupa PDF atau gambar, halaman detail laporan akan menampilkan preview otomatis.</p>
                                </div>
                                <div class="field-group field-group--full">
                                    <label for="gallery_urls">Gallery Dokumentasi</label>
                                    <textarea class="admin-input" id="gallery_urls" name="gallery_urls" rows="5" placeholder="Satu URL gambar per baris"><?= h($form['gallery_urls']); ?></textarea>
                                    <p class="field-help">Gunakan untuk dokumentasi kegiatan. Setiap baris akan ditampilkan sebagai item gallery di halaman detail laporan.</p>
                                </div>
                                <div class="field-group field-group--full">
                                    <label for="excerpt">Ringkasan</label>
                                    <textarea class="admin-input" id="excerpt" name="excerpt" rows="4"><?= h($form['excerpt']); ?></textarea>
                                    <p class="field-help">Jika dikosongkan, sistem akan membuat ringkasan otomatis dari isi laporan saat disimpan.</p>
                                </div>
                                <div class="field-group field-group--full">
                                    <label for="body">Isi Laporan</label>
                                    <div class="editor-shell">
                                        <div class="editor-toolbar" data-editor-toolbar>
                                            <div class="editor-toolbar__group">
                                                <button class="editor-toolbar__button" type="button" data-command="bold"><strong>B</strong></button>
                                                <button class="editor-toolbar__button" type="button" data-command="italic"><em>I</em></button>
                                                <button class="editor-toolbar__button" type="button" data-command="underline"><u>U</u></button>
                                            </div>
                                            <div class="editor-toolbar__group">
                                                <button class="editor-toolbar__button" type="button" data-command="formatBlock" data-value="h2">H2</button>
                                                <button class="editor-toolbar__button" type="button" data-command="formatBlock" data-value="h3">H3</button>
                                                <button class="editor-toolbar__button" type="button" data-command="formatBlock" data-value="blockquote">Quote</button>
                                            </div>
                                            <div class="editor-toolbar__group">
                                                <button class="editor-toolbar__button" type="button" data-command="insertUnorderedList">Bullet</button>
                                                <button class="editor-toolbar__button" type="button" data-command="insertOrderedList">Number</button>
                                                <button class="editor-toolbar__button" type="button" data-command="createLink">Link</button>
                                                <button class="editor-toolbar__button" type="button" data-command="insertImage">Gambar</button>
                                                <button class="editor-toolbar__button" type="button" data-command="removeFormat">Clear</button>
                                            </div>
                                        </div>
                                        <div class="rich-editor" id="body-editor" contenteditable="true" data-rich-editor></div>
                                        <textarea class="admin-input editor-source" id="body" name="body" rows="10"><?= h($form['body']); ?></textarea>
                                    </div>
                                    <p class="field-help">Gunakan editor untuk menyusun laporan kegiatan, laporan keuangan, atau informasi dokumen publik lain.</p>
                                    <?php if (isset($errors['body'])): ?><p class="field-error"><?= h($errors['body']); ?></p><?php endif; ?>
                                </div>
                            </div>

                            <section class="article-preview" aria-label="Preview laporan">
                                <div class="article-preview__header">
                                    <div>
                                        <p class="eyebrow">Preview</p>
                                        <h3>Lihat hasil laporan sebelum disimpan</h3>
                                    </div>
                                    <div class="metric-chip">Live</div>
                                </div>

                                <article class="article-preview__card">
                                    <div class="article-preview__media" data-preview-image-wrap hidden>
                                        <img src="" alt="" data-preview-image>
                                    </div>
                                    <div class="article-preview__body">
                                        <div class="article-preview__meta">
                                            <span data-preview-author><?= h($form['author'] !== '' ? $form['author'] : 'Penulis / Unit'); ?></span>
                                            <span>&middot;</span>
                                            <span data-preview-status><?= h($form['status']); ?></span>
                                            <span>&middot;</span>
                                            <span data-preview-period><?= h($form['period_label'] !== '' ? $form['period_label'] : 'Periode'); ?></span>
                                        </div>
                                        <h4 data-preview-title><?= h($form['title'] !== '' ? $form['title'] : 'Judul laporan akan tampil di sini'); ?></h4>
                                        <p class="article-preview__excerpt" data-preview-excerpt"><?= h($form['excerpt'] !== '' ? $form['excerpt'] : (generate_report_excerpt(sanitize_report_html($form['body'])) !== '' ? generate_report_excerpt(sanitize_report_html($form['body'])) : 'Ringkasan laporan akan dibuat otomatis dari isi tulisan jika kolom ringkasan dikosongkan.')); ?></p>
                                        <div class="article-preview__content" data-preview-body>
                                            <?= $form['body'] !== '' ? sanitize_report_html($form['body']) : '<p>Mulai menulis isi laporan untuk melihat preview konten di sini.</p>'; ?>
                                        </div>
                                    </div>
                                </article>
                            </section>

                            <div class="form-actions">
                                <button class="button-link" type="submit"><?= $editingId > 0 ? 'Simpan Laporan' : 'Tambah Laporan'; ?></button>
                                <?php if ($editingId > 0): ?>
                                    <a class="button-link button-link--secondary" href="<?= h(app_url('laporan-admin.php')); ?>">Batal Edit</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </article>

                    <article class="card card--list">
                        <div class="card-heading">
                            <div>
                                <p class="eyebrow">Arsip Laporan</p>
                                <h2>Dokumen publik</h2>
                            </div>
                            <div class="metric-chip"><?= h((string) count($records)); ?> data</div>
                        </div>

                        <?php if ($records === []): ?>
                            <div class="empty-state">
                                <h3>Belum ada laporan</h3>
                                <p>Tambah laporan pertama untuk mulai mengisi halaman laporan publik <?= h($siteName); ?>.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-wrap">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>Judul</th>
                                            <th>Kategori</th>
                                            <th>Periode</th>
                                            <th>Penulis</th>
                                            <th>Publikasi</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($records as $record): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= h($record['title']); ?></strong>
                                                    <span>/<?= h($record['slug']); ?></span>
                                                </td>
                                                <td><?= h((string) ($record['category'] ?? '-')); ?></td>
                                                <td><?= h((string) ($record['period_label'] ?? '-')); ?></td>
                                                <td><?= h($record['author']); ?></td>
                                                <td><?= h(format_human_date($record['published_at'] !== null ? (string) $record['published_at'] : null, true)); ?></td>
                                                <td><span class="<?= h(status_badge_class((string) $record['status'])); ?>"><?= h((string) $record['status']); ?></span></td>
                                                <td>
                                                    <div class="inline-actions">
                                                        <a class="button-small" href="<?= h(app_url('laporan-admin.php')); ?>?edit=<?= h((string) $record['id']); ?>">Edit</a>
                                                        <form method="post" onsubmit="return confirm('Hapus laporan ini?');">
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
            <script>
                (() => {
                    const form = document.querySelector('.article-editor-form');
                    if (!form) {
                        return;
                    }

                    const editor = form.querySelector('[data-rich-editor]');
                    const source = form.querySelector('#body');
                    const toolbar = form.querySelector('[data-editor-toolbar]');
                    const titleInput = form.querySelector('#title');
                    const authorInput = form.querySelector('#author');
                    const excerptInput = form.querySelector('#excerpt');
                    const featuredImageInput = form.querySelector('#featured_image');
                    const statusInput = form.querySelector('#status');
                    const periodInput = form.querySelector('#period_label');
                    const previewTitle = form.querySelector('[data-preview-title]');
                    const previewAuthor = form.querySelector('[data-preview-author]');
                    const previewStatus = form.querySelector('[data-preview-status]');
                    const previewPeriod = form.querySelector('[data-preview-period]');
                    const previewExcerpt = form.querySelector('[data-preview-excerpt]');
                    const previewBody = form.querySelector('[data-preview-body]');
                    const previewImageWrap = form.querySelector('[data-preview-image-wrap]');
                    const previewImage = form.querySelector('[data-preview-image]');

                    if (!editor || !source || !toolbar) {
                        return;
                    }

                    const initialValue = source.value.trim();
                    editor.innerHTML = initialValue !== '' ? initialValue : '<p></p>';

                    const plainTextFromHtml = (html) => {
                        const parser = document.createElement('div');
                        parser.innerHTML = html;
                        return (parser.textContent || parser.innerText || '').replace(/\s+/g, ' ').trim();
                    };

                    const excerptFromHtml = (html) => {
                        const text = plainTextFromHtml(html);
                        if (!text) {
                            return 'Ringkasan laporan akan dibuat otomatis dari isi tulisan jika kolom ringkasan dikosongkan.';
                        }

                        return text.length > 180 ? `${text.slice(0, 179).trim()}...` : text;
                    };

                    const syncEditor = () => {
                        source.value = editor.innerHTML.trim();
                    };

                    const syncPreview = () => {
                        const bodyHtml = editor.innerHTML.trim();
                        const imageUrl = featuredImageInput ? featuredImageInput.value.trim() : '';

                        if (previewTitle) {
                            previewTitle.textContent = titleInput && titleInput.value.trim() !== ''
                                ? titleInput.value.trim()
                                : 'Judul laporan akan tampil di sini';
                        }

                        if (previewAuthor) {
                            previewAuthor.textContent = authorInput && authorInput.value.trim() !== ''
                                ? authorInput.value.trim()
                                : 'Penulis / Unit';
                        }

                        if (previewStatus && statusInput) {
                            previewStatus.textContent = statusInput.value.trim() !== ''
                                ? statusInput.value.trim()
                                : 'draft';
                        }

                        if (previewPeriod && periodInput) {
                            previewPeriod.textContent = periodInput.value.trim() !== ''
                                ? periodInput.value.trim()
                                : 'Periode';
                        }

                        if (previewExcerpt) {
                            previewExcerpt.textContent = excerptInput && excerptInput.value.trim() !== ''
                                ? excerptInput.value.trim()
                                : excerptFromHtml(bodyHtml);
                        }

                        if (previewBody) {
                            previewBody.innerHTML = bodyHtml !== '' ? bodyHtml : '<p>Mulai menulis isi laporan untuk melihat preview konten di sini.</p>';
                        }

                        if (previewImageWrap && previewImage) {
                            if (imageUrl !== '') {
                                previewImage.src = imageUrl;
                                previewImage.alt = previewTitle ? previewTitle.textContent : 'Featured image';
                                previewImageWrap.hidden = false;
                            } else {
                                previewImage.src = '';
                                previewImage.alt = '';
                                previewImageWrap.hidden = true;
                            }
                        }
                    };

                    toolbar.addEventListener('click', (event) => {
                        const button = event.target.closest('[data-command]');
                        if (!button) {
                            return;
                        }

                        event.preventDefault();
                        editor.focus();

                        const command = button.dataset.command;
                        const value = button.dataset.value || null;

                        if (command === 'createLink') {
                            const url = window.prompt('Masukkan URL link:', 'https://');
                            if (url && url.trim() !== '') {
                                document.execCommand('createLink', false, url.trim());
                            }
                        } else if (command === 'insertImage') {
                            const url = window.prompt('Masukkan URL gambar:', 'https://');
                            if (url && url.trim() !== '') {
                                document.execCommand('insertImage', false, url.trim());
                            }
                        } else if (command === 'formatBlock' && value) {
                            document.execCommand('formatBlock', false, value);
                        } else if (command) {
                            document.execCommand(command, false, value);
                        }

                        syncEditor();
                        syncPreview();
                    });

                    editor.addEventListener('input', () => {
                        syncEditor();
                        syncPreview();
                    });

                    [titleInput, authorInput, excerptInput, featuredImageInput, statusInput, periodInput].forEach((input) => {
                        if (!input) {
                            return;
                        }

                        input.addEventListener('input', syncPreview);
                        input.addEventListener('change', syncPreview);
                    });

                    form.addEventListener('submit', () => {
                        syncEditor();
                        syncPreview();
                    });
                    syncPreview();
                })();
            </script>
<?php
render_admin_page_end();
