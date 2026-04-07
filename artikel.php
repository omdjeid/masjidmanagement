<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/admin.php';

require_role(['super_admin', 'admin', 'editor']);

function sanitize_article_html(string $html): string
{
    return sanitize_rich_text_html($html);
}

function generate_article_excerpt(string $html, int $limit = 180): string
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

function upload_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran file melebihi batas upload yang diizinkan server.',
        UPLOAD_ERR_PARTIAL => 'Upload gambar terputus. Silakan coba lagi.',
        UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara upload tidak tersedia di server.',
        UPLOAD_ERR_CANT_WRITE => 'Server gagal menulis file upload.',
        UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh ekstensi PHP di server.',
        default => 'Upload gambar gagal diproses.',
    };
}

function article_upload_period(string $publishedAt): array
{
    $timestamp = $publishedAt !== '' ? strtotime($publishedAt) : false;
    if ($timestamp === false) {
        $timestamp = time();
    }

    return [
        date('Y', $timestamp),
        date('m', $timestamp),
    ];
}

function store_article_featured_image(array $file, string $preferredName, string $publishedAt): string
{
    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('File upload gambar tidak valid.');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        throw new RuntimeException('File gambar kosong atau gagal terbaca.');
    }

    if ($size > 5 * 1024 * 1024) {
        throw new RuntimeException('Ukuran gambar maksimal 5 MB.');
    }

    $mime = '';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmpName);
    } elseif (function_exists('mime_content_type')) {
        $mime = (string) mime_content_type($tmpName);
    }

    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/avif' => 'avif',
    ];

    if (!isset($allowedMimes[$mime])) {
        throw new RuntimeException('Format gambar belum didukung. Gunakan JPG, PNG, WEBP, GIF, atau AVIF.');
    }

    [$year, $month] = article_upload_period($publishedAt);
    $uploadRoot = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'articles';
    $targetDir = $uploadRoot . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $month;

    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Folder upload gambar tidak bisa dibuat.');
    }

    $baseName = normalize_slug($preferredName);
    if ($baseName === '') {
        $originalName = (string) pathinfo((string) ($file['name'] ?? 'featured-image'), PATHINFO_FILENAME);
        $baseName = normalize_slug($originalName);
    }
    if ($baseName === '') {
        $baseName = 'featured-image';
    }

    $targetName = $baseName . '-' . date('His') . '-' . bin2hex(random_bytes(3)) . '.' . $allowedMimes[$mime];
    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $targetName;

    if (!move_uploaded_file($tmpName, $targetPath)) {
        throw new RuntimeException('Gagal menyimpan gambar upload ke server.');
    }

    return 'assets/uploads/articles/' . $year . '/' . $month . '/' . $targetName;
}

$errors = [];
$pageError = null;
$editingId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$articleCategories = fetch_master_categories('article');
$form = [
    'title' => '',
    'slug' => '',
    'author' => '',
    'category' => '',
    'excerpt' => '',
    'body' => '',
    'featured_image' => '',
    'featured_image_title' => '',
    'featured_image_alt' => '',
    'published_at' => '',
    'status' => 'draft',
];
$records = [];
$siteName = configured_site_name();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf_token('artikel.php');
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            db()->prepare('DELETE FROM articles WHERE id = :id')->execute(['id' => $id]);
            set_flash('success', 'Artikel berhasil dihapus.');
            redirect_to('/artikel.php');
        }

        $editingId = (int) ($_POST['id'] ?? 0);
        $featuredImageUpload = $_FILES['featured_image_upload'] ?? null;
        $form = [
            'title' => trim((string) ($_POST['title'] ?? '')),
            'slug' => trim((string) ($_POST['slug'] ?? '')),
            'author' => trim((string) ($_POST['author'] ?? '')),
            'category' => trim((string) ($_POST['category'] ?? '')),
            'excerpt' => trim((string) ($_POST['excerpt'] ?? '')),
            'body' => trim((string) ($_POST['body'] ?? '')),
            'featured_image' => trim((string) ($_POST['featured_image'] ?? '')),
            'featured_image_title' => trim((string) ($_POST['featured_image_title'] ?? '')),
            'featured_image_alt' => trim((string) ($_POST['featured_image_alt'] ?? '')),
            'published_at' => trim((string) ($_POST['published_at'] ?? '')),
            'status' => trim((string) ($_POST['status'] ?? 'draft')),
        ];

        if ($form['title'] === '') {
            $errors['title'] = 'Judul artikel wajib diisi.';
        }
        if ($form['author'] === '') {
            $errors['author'] = 'Nama penulis wajib diisi.';
        }
        $sanitizedBody = sanitize_article_html($form['body']);

        if (trim(strip_tags($sanitizedBody)) === '') {
            $errors['body'] = 'Isi artikel wajib diisi.';
        }

        if (
            $form['featured_image'] !== ''
            && preg_match('/^(https?:\/\/|[A-Za-z0-9_\/.\-]+)$/', $form['featured_image']) !== 1
        ) {
            $errors['featured_image'] = 'Path atau URL featured image tidak valid.';
        }

        $uploadError = is_array($featuredImageUpload) ? (int) ($featuredImageUpload['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
        $hasNewUpload = $uploadError !== UPLOAD_ERR_NO_FILE;
        if ($hasNewUpload && $uploadError !== UPLOAD_ERR_OK) {
            $errors['featured_image_upload'] = upload_error_message($uploadError);
        }

        $slug = normalize_slug($form['slug'] !== '' ? $form['slug'] : $form['title']);
        $publishedAt = $form['published_at'] !== '' ? date('Y-m-d H:i:s', strtotime($form['published_at'])) : null;

        if ($errors === []) {
            $slugCheck = db()->prepare('SELECT id FROM articles WHERE slug = :slug LIMIT 1');
            $slugCheck->execute(['slug' => $slug]);
            $existingSlug = $slugCheck->fetch();

            if ($existingSlug !== false && (int) $existingSlug['id'] !== $editingId) {
                $errors['slug'] = 'Slug ini sudah dipakai artikel lain.';
            }
        }

        if ($errors === [] && $hasNewUpload && is_array($featuredImageUpload)) {
            try {
                $form['featured_image'] = store_article_featured_image(
                    $featuredImageUpload,
                    $form['slug'] !== '' ? $form['slug'] : $form['title'],
                    $form['published_at']
                );
            } catch (Throwable $exception) {
                $errors['featured_image_upload'] = $exception->getMessage();
            }
        }

        if ($errors === []) {
            $generatedExcerpt = generate_article_excerpt($sanitizedBody);
            $featuredImageTitle = $form['featured_image_title'] !== ''
                ? $form['featured_image_title']
                : ($form['title'] !== '' ? $form['title'] : null);
            $featuredImageAlt = $form['featured_image_alt'] !== ''
                ? $form['featured_image_alt']
                : ($form['title'] !== '' ? $form['title'] : null);
            $payload = [
                'title' => $form['title'],
                'slug' => $slug,
                'author' => $form['author'],
                'category' => $form['category'] !== '' ? $form['category'] : null,
                'excerpt' => $form['excerpt'] !== '' ? $form['excerpt'] : ($generatedExcerpt !== '' ? $generatedExcerpt : null),
                'body' => $sanitizedBody,
                'featured_image' => $form['featured_image'] !== '' ? $form['featured_image'] : null,
                'featured_image_title' => $form['featured_image'] !== '' ? $featuredImageTitle : null,
                'featured_image_alt' => $form['featured_image'] !== '' ? $featuredImageAlt : null,
                'published_at' => $publishedAt,
                'status' => $form['status'],
            ];

            if ($action === 'update' && $editingId > 0) {
                $payload['id'] = $editingId;
                db()->prepare(
                    'UPDATE articles
                     SET title = :title, slug = :slug, author = :author, category = :category, excerpt = :excerpt, body = :body,
                         featured_image = :featured_image, featured_image_title = :featured_image_title,
                         featured_image_alt = :featured_image_alt, published_at = :published_at, status = :status
                     WHERE id = :id'
                )->execute($payload);
                set_flash('success', 'Artikel berhasil diperbarui.');
            } else {
                db()->prepare(
                    'INSERT INTO articles (title, slug, author, category, excerpt, body, featured_image, featured_image_title, featured_image_alt, published_at, status)
                     VALUES (:title, :slug, :author, :category, :excerpt, :body, :featured_image, :featured_image_title, :featured_image_alt, :published_at, :status)'
                )->execute($payload);
                set_flash('success', 'Artikel baru berhasil ditambahkan.');
            }

            redirect_to('/artikel.php');
        }
    }

    if ($editingId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $statement = db()->prepare('SELECT * FROM articles WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $editingId]);
        $record = $statement->fetch();

        if ($record !== false) {
            $form = [
                'title' => (string) $record['title'],
                'slug' => (string) $record['slug'],
                'author' => (string) $record['author'],
                'category' => (string) ($record['category'] ?? ''),
                'excerpt' => (string) ($record['excerpt'] ?? ''),
                'body' => (string) $record['body'],
                'featured_image' => (string) ($record['featured_image'] ?? ''),
                'featured_image_title' => (string) ($record['featured_image_title'] ?? ''),
                'featured_image_alt' => (string) ($record['featured_image_alt'] ?? ''),
                'published_at' => format_datetime_local($record['published_at'] !== null ? (string) $record['published_at'] : null),
                'status' => (string) $record['status'],
            ];
        }
    }

    $records = db()->query(
        'SELECT * FROM articles ORDER BY COALESCE(published_at, created_at) DESC, id DESC'
    )->fetchAll();
} catch (Throwable) {
    $pageError = 'Modul artikel belum siap dipakai. Jalankan ulang `sql/schema.sql` agar tabel `articles` tersedia.';
}

render_admin_page_start('Artikel', 'artikel');
render_admin_page_header(
    'Editorial',
    'Kelola Artikel',
    'Atur konten long-form untuk ' . $siteName . ': judul, slug, body artikel, status publikasi, dan waktu tayang.',
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
                                <p class="eyebrow"><?= $editingId > 0 ? 'Edit Artikel' : 'Tambah Artikel'; ?></p>
                                <h2><?= $editingId > 0 ? 'Perbarui artikel' : 'Tulis artikel baru'; ?></h2>
                            </div>
                        </div>

                        <form method="post" class="admin-form article-editor-form" enctype="multipart/form-data">
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
                                    <label for="author">Penulis</label>
                                    <input class="admin-input" id="author" name="author" type="text" value="<?= h($form['author']); ?>">
                                    <?php if (isset($errors['author'])): ?><p class="field-error"><?= h($errors['author']); ?></p><?php endif; ?>
                                </div>
                                <div class="field-group">
                                    <label for="category">Kategori</label>
                                    <select class="admin-input" id="category" name="category">
                                        <option value="">Pilih kategori</option>
                                        <?php foreach ($articleCategories as $category): ?>
                                            <option value="<?= h($category); ?>" <?= $form['category'] === $category ? 'selected' : ''; ?>><?= h($category); ?></option>
                                        <?php endforeach; ?>
                                    </select>
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
                                    <label for="featured_image_upload">Upload Featured Image</label>
                                    <input class="admin-input" id="featured_image_upload" name="featured_image_upload" type="file" accept="image/jpeg,image/png,image/webp,image/gif,image/avif">
                                    <p class="field-help">Gambar akan disimpan ke folder `assets/uploads/articles/tahun/bulan/` sesuai bulan publikasi atau bulan saat upload.</p>
                                    <?php if (isset($errors['featured_image_upload'])): ?><p class="field-error"><?= h($errors['featured_image_upload']); ?></p><?php endif; ?>
                                </div>
                                <div class="field-group field-group--full">
                                    <label for="featured_image">Path / URL Featured Image</label>
                                    <input class="admin-input" id="featured_image" name="featured_image" type="text" value="<?= h($form['featured_image']); ?>" placeholder="Akan terisi otomatis setelah upload atau bisa diisi manual">
                                    <p class="field-help">Boleh tetap diisi manual jika memakai gambar eksternal atau file yang sudah ada.</p>
                                    <?php if (isset($errors['featured_image'])): ?><p class="field-error"><?= h($errors['featured_image']); ?></p><?php endif; ?>
                                </div>
                                <div class="field-group">
                                    <label for="featured_image_title">Judul Gambar</label>
                                    <input class="admin-input" id="featured_image_title" name="featured_image_title" type="text" value="<?= h($form['featured_image_title']); ?>" placeholder="Judul internal gambar">
                                </div>
                                <div class="field-group">
                                    <label for="featured_image_alt">Alt Text Gambar</label>
                                    <input class="admin-input" id="featured_image_alt" name="featured_image_alt" type="text" value="<?= h($form['featured_image_alt']); ?>" placeholder="Deskripsi gambar untuk SEO dan aksesibilitas">
                                </div>
                                <div class="field-group field-group--full">
                                    <label for="excerpt">Excerpt</label>
                                    <textarea class="admin-input" id="excerpt" name="excerpt" rows="4"><?= h($form['excerpt']); ?></textarea>
                                    <p class="field-help">Jika dikosongkan, sistem akan membuat ringkasan otomatis dari isi artikel saat disimpan.</p>
                                </div>
                                <div class="field-group field-group--full">
                                    <label for="body">Isi Artikel</label>
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
                                        <div
                                            class="rich-editor"
                                            id="body-editor"
                                            contenteditable="true"
                                            data-rich-editor
                                        ></div>
                                        <textarea class="admin-input editor-source" id="body" name="body" rows="10"><?= h($form['body']); ?></textarea>
                                    </div>
                                    <p class="field-help">Gunakan toolbar untuk menulis seperti editor WordPress: bold, italic, heading, list, quote, dan link.</p>
                                    <?php if (isset($errors['body'])): ?><p class="field-error"><?= h($errors['body']); ?></p><?php endif; ?>
                                </div>
                            </div>

                            <section class="article-preview" aria-label="Preview artikel">
                                <div class="article-preview__header">
                                    <div>
                                        <p class="eyebrow">Preview</p>
                                        <h3>Lihat hasil artikel sebelum disimpan</h3>
                                    </div>
                                    <div class="metric-chip">Live</div>
                                </div>

                                <article class="article-preview__card">
                                    <div class="article-preview__media" data-preview-image-wrap hidden>
                                        <img src="" alt="" data-preview-image>
                                    </div>
                                    <div class="article-preview__body">
                                        <div class="article-preview__meta">
                                            <span data-preview-author><?= h($form['author'] !== '' ? $form['author'] : 'Nama penulis'); ?></span>
                                            <span>&middot;</span>
                                            <span data-preview-status><?= h($form['status']); ?></span>
                                        </div>
                                        <h4 data-preview-title><?= h($form['title'] !== '' ? $form['title'] : 'Judul artikel akan tampil di sini'); ?></h4>
                                        <p class="article-preview__excerpt" data-preview-excerpt"><?= h($form['excerpt'] !== '' ? $form['excerpt'] : (generate_article_excerpt(sanitize_article_html($form['body'])) !== '' ? generate_article_excerpt(sanitize_article_html($form['body'])) : 'Ringkasan artikel akan dibuat otomatis dari isi tulisan jika kolom excerpt dikosongkan.')); ?></p>
                                        <div class="article-preview__content" data-preview-body>
                                            <?= $form['body'] !== '' ? sanitize_article_html($form['body']) : '<p>Mulai menulis isi artikel untuk melihat preview konten di sini.</p>'; ?>
                                        </div>
                                    </div>
                                </article>
                            </section>

                            <div class="form-actions">
                                <button class="button-link" type="submit"><?= $editingId > 0 ? 'Simpan Artikel' : 'Tambah Artikel'; ?></button>
                                <?php if ($editingId > 0): ?>
                                    <a class="button-link button-link--secondary" href="<?= h(app_url('artikel.php')); ?>">Batal Edit</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </article>

                    <article class="card card--list">
                        <div class="card-heading">
                            <div>
                                <p class="eyebrow">Arsip Artikel</p>
                                <h2>Konten editorial</h2>
                            </div>
                            <div class="metric-chip"><?= h((string) count($records)); ?> data</div>
                        </div>

                        <?php if ($records === []): ?>
                            <div class="empty-state">
                                <h3>Belum ada artikel</h3>
                                <p>Tambah artikel pertama untuk mulai membangun kanal editorial <?= h($siteName); ?>.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-wrap">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>Judul</th>
                                            <th>Kategori</th>
                                            <th>Author</th>
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
                                                <td><?= h($record['author']); ?></td>
                                                <td><?= h(format_human_date($record['published_at'] !== null ? (string) $record['published_at'] : null, true)); ?></td>
                                                <td><span class="<?= h(status_badge_class((string) $record['status'])); ?>"><?= h((string) $record['status']); ?></span></td>
                                                <td>
                                                    <div class="inline-actions">
                                                        <a class="button-small" href="<?= h(app_url('artikel.php')); ?>?edit=<?= h((string) $record['id']); ?>">Edit</a>
                                                        <form method="post" onsubmit="return confirm('Hapus artikel ini?');">
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
                    const featuredImageFileInput = form.querySelector('#featured_image_upload');
                    const statusInput = form.querySelector('#status');
                    const previewTitle = form.querySelector('[data-preview-title]');
                    const previewAuthor = form.querySelector('[data-preview-author]');
                    const previewStatus = form.querySelector('[data-preview-status]');
                    const previewExcerpt = form.querySelector('[data-preview-excerpt]');
                    const previewBody = form.querySelector('[data-preview-body]');
                    const previewImageWrap = form.querySelector('[data-preview-image-wrap]');
                    const previewImage = form.querySelector('[data-preview-image]');
                    let previewObjectUrl = '';

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
                            return 'Ringkasan artikel akan dibuat otomatis dari isi tulisan jika kolom excerpt dikosongkan.';
                        }

                        return text.length > 180 ? `${text.slice(0, 179).trim()}...` : text;
                    };

                    const syncEditor = () => {
                        source.value = editor.innerHTML.trim();
                    };

                    const syncPreview = () => {
                        const bodyHtml = editor.innerHTML.trim();
                        let imageUrl = featuredImageInput ? featuredImageInput.value.trim() : '';

                        if (featuredImageFileInput && featuredImageFileInput.files && featuredImageFileInput.files[0]) {
                            if (previewObjectUrl !== '') {
                                URL.revokeObjectURL(previewObjectUrl);
                            }

                            previewObjectUrl = URL.createObjectURL(featuredImageFileInput.files[0]);
                            imageUrl = previewObjectUrl;
                        } else if (previewObjectUrl !== '') {
                            URL.revokeObjectURL(previewObjectUrl);
                            previewObjectUrl = '';
                        }

                        if (previewTitle) {
                            previewTitle.textContent = titleInput && titleInput.value.trim() !== ''
                                ? titleInput.value.trim()
                                : 'Judul artikel akan tampil di sini';
                        }

                        if (previewAuthor) {
                            previewAuthor.textContent = authorInput && authorInput.value.trim() !== ''
                                ? authorInput.value.trim()
                                : 'Nama penulis';
                        }

                        if (previewStatus && statusInput) {
                            previewStatus.textContent = statusInput.value.trim() !== ''
                                ? statusInput.value.trim()
                                : 'draft';
                        }

                        if (previewExcerpt) {
                            previewExcerpt.textContent = excerptInput && excerptInput.value.trim() !== ''
                                ? excerptInput.value.trim()
                                : excerptFromHtml(bodyHtml);
                        }

                        if (previewBody) {
                            previewBody.innerHTML = bodyHtml !== '' ? bodyHtml : '<p>Mulai menulis isi artikel untuk melihat preview konten di sini.</p>';
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

                    [titleInput, authorInput, excerptInput, featuredImageInput, featuredImageFileInput, statusInput].forEach((input) => {
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
