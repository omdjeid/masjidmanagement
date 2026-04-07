<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/site.php';

require_role(['super_admin', 'admin', 'editor']);

function gallery_upload_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran file melebihi batas upload server.',
        UPLOAD_ERR_PARTIAL => 'Upload gambar terputus. Silakan coba lagi.',
        UPLOAD_ERR_NO_FILE => 'Silakan pilih file gambar terlebih dahulu.',
        UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara upload tidak tersedia.',
        UPLOAD_ERR_CANT_WRITE => 'Server gagal menulis file upload.',
        UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh ekstensi PHP di server.',
        default => 'Upload gambar gagal diproses.',
    };
}

function gallery_supported_extensions(): array
{
    return ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'];
}

function gallery_is_supported_image(string $path): bool
{
    $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

    return in_array($extension, gallery_supported_extensions(), true);
}

function gallery_relative_upload_path(string $absolutePath): string
{
    $normalized = str_replace('\\', '/', $absolutePath);
    $marker = '/assets/uploads/';
    $position = strpos($normalized, $marker);

    if ($position === false) {
        return '';
    }

    return ltrim(substr($normalized, $position + 1), '/');
}

function gallery_human_size(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }

    if ($bytes < 1024 * 1024) {
        return number_format($bytes / 1024, 1, ',', '.') . ' KB';
    }

    return number_format($bytes / (1024 * 1024), 2, ',', '.') . ' MB';
}

function gallery_dimensions(string $path): string
{
    $info = @getimagesize($path);
    if (!is_array($info) || !isset($info[0], $info[1])) {
        return '-';
    }

    return $info[0] . ' x ' . $info[1] . ' px';
}

function gallery_folder_label(string $relativePath): string
{
    $directory = trim(str_replace('\\', '/', dirname($relativePath)), './');
    if ($directory === '' || $directory === '.') {
        return 'Tanpa folder';
    }

    return $directory;
}

function gallery_store_uploaded_image(array $file, string $preferredName = ''): string
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

    $year = date('Y');
    $month = date('m');
    $uploadRoot = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'gallery';
    $targetDir = $uploadRoot . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $month;

    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Folder upload gallery tidak bisa dibuat.');
    }

    $baseName = normalize_slug($preferredName);
    if ($baseName === '') {
        $originalName = (string) pathinfo((string) ($file['name'] ?? 'gallery-image'), PATHINFO_FILENAME);
        $baseName = normalize_slug($originalName);
    }
    if ($baseName === '') {
        $baseName = 'gallery-image';
    }

    $targetName = $baseName . '-' . date('His') . '-' . bin2hex(random_bytes(3)) . '.' . $allowedMimes[$mime];
    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $targetName;

    if (!move_uploaded_file($tmpName, $targetPath)) {
        throw new RuntimeException('Gagal menyimpan gambar upload ke server.');
    }

    return 'assets/uploads/gallery/' . $year . '/' . $month . '/' . $targetName;
}

function gallery_normalize_uploaded_files(array $files): array
{
    $normalized = [];
    $names = $files['name'] ?? [];
    $tmpNames = $files['tmp_name'] ?? [];
    $sizes = $files['size'] ?? [];
    $errors = $files['error'] ?? [];
    $types = $files['type'] ?? [];

    if (!is_array($names)) {
        return [$files];
    }

    foreach ($names as $index => $name) {
        $normalized[] = [
            'name' => $name,
            'type' => is_array($types) ? ($types[$index] ?? '') : '',
            'tmp_name' => is_array($tmpNames) ? ($tmpNames[$index] ?? '') : '',
            'error' => is_array($errors) ? ($errors[$index] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE,
            'size' => is_array($sizes) ? ($sizes[$index] ?? 0) : 0,
        ];
    }

    return $normalized;
}

function gallery_fetch_article_usage(): array
{
    try {
        $rows = db()->query(
            "SELECT id, title, slug, featured_image, featured_image_title, featured_image_alt, status,
                    COALESCE(updated_at, published_at, created_at) AS updated_label
             FROM articles
             WHERE featured_image IS NOT NULL AND featured_image <> ''"
        )->fetchAll();
    } catch (Throwable) {
        return [];
    }

    $usage = [];
    foreach ($rows as $row) {
        $path = trim((string) ($row['featured_image'] ?? ''));
        if ($path === '') {
            continue;
        }

        $usage[$path][] = [
            'id' => (int) ($row['id'] ?? 0),
            'title' => (string) ($row['title'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'featured_image_title' => (string) ($row['featured_image_title'] ?? ''),
            'featured_image_alt' => (string) ($row['featured_image_alt'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'updated_label' => format_human_date((string) ($row['updated_label'] ?? ''), true),
        ];
    }

    return $usage;
}

function gallery_scan_images(string $root): array
{
    if (!is_dir($root)) {
        return [];
    }

    $items = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }

        $absolutePath = $file->getPathname();
        if (!gallery_is_supported_image($absolutePath)) {
            continue;
        }

        $relativePath = gallery_relative_upload_path($absolutePath);
        if ($relativePath === '') {
            continue;
        }

        $items[] = [
            'relative_path' => $relativePath,
            'absolute_path' => $absolutePath,
            'url' => media_url($relativePath),
            'filename' => $file->getFilename(),
            'folder' => gallery_folder_label($relativePath),
            'size_bytes' => (int) $file->getSize(),
            'size_label' => gallery_human_size((int) $file->getSize()),
            'dimensions' => gallery_dimensions($absolutePath),
            'modified_at' => format_human_date(date('Y-m-d H:i:s', (int) $file->getMTime()), true),
            'extension' => strtolower((string) $file->getExtension()),
        ];
    }

    usort(
        $items,
        static fn (array $left, array $right): int => strcmp((string) $right['relative_path'], (string) $left['relative_path'])
    );

    return $items;
}

$pageError = null;
$uploadErrors = [];
$uploadForm = [
    'file_name' => '',
];
$images = [];
$usageByPath = [];
$totalSizeBytes = 0;
$usedImageCount = 0;
$folderCount = 0;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf_token('gallery.php');
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'upload_gallery_image') {
            $uploadForm['file_name'] = trim((string) ($_POST['file_name'] ?? ''));
            $uploadFiles = isset($_FILES['gallery_image']) && is_array($_FILES['gallery_image'])
                ? gallery_normalize_uploaded_files($_FILES['gallery_image'])
                : [];
            $storedPaths = [];
            $failedUploads = [];

            if ($uploadFiles === []) {
                $uploadErrors['gallery_image'] = 'Silakan pilih minimal satu file gambar.';
            }

            foreach ($uploadFiles as $index => $uploadFile) {
                $uploadError = (int) ($uploadFile['error'] ?? UPLOAD_ERR_NO_FILE);
                if ($uploadError !== UPLOAD_ERR_OK) {
                    if ($uploadError !== UPLOAD_ERR_NO_FILE || count($uploadFiles) === 1) {
                        $failedUploads[] = sprintf(
                            '%s: %s',
                            trim((string) ($uploadFile['name'] ?? 'File ' . ($index + 1))) !== '' ? (string) $uploadFile['name'] : 'File ' . ($index + 1),
                            gallery_upload_error_message($uploadError)
                        );
                    }
                    continue;
                }

                try {
                    $preferredName = $uploadForm['file_name'] !== ''
                        ? $uploadForm['file_name'] . '-' . ($index + 1)
                        : '';
                    $storedPaths[] = gallery_store_uploaded_image($uploadFile, $preferredName);
                } catch (Throwable $exception) {
                    $failedUploads[] = sprintf(
                        '%s: %s',
                        trim((string) ($uploadFile['name'] ?? 'File ' . ($index + 1))) !== '' ? (string) $uploadFile['name'] : 'File ' . ($index + 1),
                        $exception->getMessage()
                    );
                }
            }

            if ($storedPaths === [] && $failedUploads !== []) {
                $uploadErrors['gallery_image'] = implode(' ', $failedUploads);
            }

            if ($storedPaths !== []) {
                $message = count($storedPaths) === 1
                    ? '1 gambar berhasil diupload ke `' . $storedPaths[0] . '`.'
                    : count($storedPaths) . ' gambar berhasil diupload ke gallery.';

                if ($failedUploads !== []) {
                    $message .= ' Sebagian file gagal: ' . implode(' ', $failedUploads);
                }

                set_flash($failedUploads === [] ? 'success' : 'error', $message);
                redirect_to('/gallery.php');
            }
        }
    }

    $usageByPath = gallery_fetch_article_usage();
    $images = gallery_scan_images(__DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads');
    $folders = [];

    foreach ($images as &$image) {
        $path = (string) $image['relative_path'];
        $image['articles'] = $usageByPath[$path] ?? [];
        $image['usage_count'] = count($image['articles']);
        $totalSizeBytes += (int) $image['size_bytes'];

        if ((int) $image['usage_count'] > 0) {
            $usedImageCount++;
        }

        $folders[(string) $image['folder']] = true;
    }
    unset($image);

    $folderCount = count($folders);
} catch (Throwable) {
    $pageError = 'Gallery upload belum dapat dimuat saat ini.';
}

render_admin_page_start('Gallery Upload', 'gallery');
render_admin_page_header(
    'Media Library',
    'Gallery Gambar Upload',
    'Lihat semua gambar yang tersimpan di folder upload lokal, lengkap dengan ukuran file, folder bulan, dan relasinya ke artikel.',
    [
        ['href' => 'artikel.php', 'label' => 'Kembali ke Artikel', 'secondary' => true],
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
                                <p class="eyebrow">Upload Baru</p>
                                <h2>Upload gambar ke gallery</h2>
                            </div>
                        </div>

                        <form method="post" class="admin-form" enctype="multipart/form-data">
                            <?= csrf_input(); ?>
                            <input type="hidden" name="action" value="upload_gallery_image">

                            <div class="field-grid">
                                <div class="field-group field-group--full">
                                    <label for="gallery_image">File Gambar</label>
                                    <input class="admin-input" id="gallery_image" name="gallery_image[]" type="file" accept="image/jpeg,image/png,image/webp,image/gif,image/avif" multiple>
                                    <p class="field-help">Anda bisa memilih banyak gambar sekaligus. Semua file akan disimpan ke folder `assets/uploads/gallery/tahun/bulan/` berdasarkan bulan saat upload.</p>
                                    <?php if (isset($uploadErrors['gallery_image'])): ?><p class="field-error"><?= h($uploadErrors['gallery_image']); ?></p><?php endif; ?>
                                </div>
                                <div class="field-group field-group--full">
                                    <label for="file_name">Prefix Nama File Opsional</label>
                                    <input class="admin-input" id="file_name" name="file_name" type="text" value="<?= h((string) $uploadForm['file_name']); ?>" placeholder="contoh-banner-ramadhan">
                                    <p class="field-help">Jika diisi, prefix ini akan dipakai sebagai dasar nama file untuk semua upload. Jika kosong, sistem memakai nama file asli masing-masing.</p>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button class="button-link" type="submit">Upload Gambar Sekaligus</button>
                            </div>
                        </form>
                    </article>
                </section>

                <section class="stats-grid stats-grid--four">
                    <article class="stat-card">
                        <p>Total Gambar</p>
                        <strong><?= h((string) count($images)); ?></strong>
                        <span>Semua file gambar yang ditemukan di `assets/uploads`.</span>
                    </article>
                    <article class="stat-card">
                        <p>Dipakai Artikel</p>
                        <strong><?= h((string) $usedImageCount); ?></strong>
                        <span>Jumlah file yang sedang dipakai sebagai featured image artikel.</span>
                    </article>
                    <article class="stat-card">
                        <p>Total Ukuran</p>
                        <strong><?= h(gallery_human_size($totalSizeBytes)); ?></strong>
                        <span>Akumulasi ukuran file gambar upload lokal.</span>
                    </article>
                    <article class="stat-card">
                        <p>Total Folder</p>
                        <strong><?= h((string) $folderCount); ?></strong>
                        <span>Jumlah folder penyimpanan upload yang saat ini terisi.</span>
                    </article>
                </section>

                <section class="content-grid content-grid--single">
                    <article class="card card--list">
                        <div class="card-heading">
                            <div>
                                <p class="eyebrow">Uploaded Assets</p>
                                <h2>Library gambar</h2>
                            </div>
                            <div class="metric-chip"><?= h((string) count($images)); ?> file</div>
                        </div>

                        <?php if ($images === []): ?>
                            <div class="empty-state">
                                <h3>Belum ada gambar upload</h3>
                                <p>Mulai upload featured image dari halaman artikel, lalu semua file akan muncul di gallery ini.</p>
                            </div>
                        <?php else: ?>
                            <div class="gallery-grid">
                                <?php foreach ($images as $image): ?>
                                    <article class="gallery-card">
                                        <a class="gallery-card__media" href="<?= h((string) $image['url']); ?>" target="_blank" rel="noopener noreferrer">
                                            <img src="<?= h((string) $image['url']); ?>" alt="<?= h((string) $image['filename']); ?>" loading="lazy">
                                        </a>
                                        <div class="gallery-card__body">
                                            <div class="gallery-card__head">
                                                <h3><?= h((string) $image['filename']); ?></h3>
                                                <span class="status-chip"><?= h(strtoupper((string) $image['extension'])); ?></span>
                                            </div>
                                            <p class="gallery-card__path"><?= h((string) $image['relative_path']); ?></p>
                                            <div class="gallery-meta-list">
                                                <span><?= h((string) $image['dimensions']); ?></span>
                                                <span><?= h((string) $image['size_label']); ?></span>
                                                <span><?= h((string) $image['modified_at']); ?></span>
                                                <span><?= h((string) $image['folder']); ?></span>
                                            </div>

                                            <?php if ((int) $image['usage_count'] > 0): ?>
                                                <div class="gallery-usage">
                                                    <p class="gallery-usage__title">Dipakai di artikel</p>
                                                    <?php foreach ($image['articles'] as $article): ?>
                                                        <a class="gallery-usage__item" href="<?= h(app_url('artikel.php?edit=' . urlencode((string) $article['id']))); ?>">
                                                            <strong><?= h((string) $article['title']); ?></strong>
                                                            <span><?= h((string) $article['status']); ?> · <?= h((string) $article['updated_label']); ?></span>
                                                            <?php if (trim((string) $article['featured_image_alt']) !== ''): ?>
                                                                <span>Alt: <?= h((string) $article['featured_image_alt']); ?></span>
                                                            <?php endif; ?>
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <p class="gallery-card__unused">Belum terhubung ke artikel mana pun.</p>
                                            <?php endif; ?>

                                            <div class="gallery-card__actions">
                                                <a class="button-small" href="<?= h((string) $image['url']); ?>" target="_blank" rel="noopener noreferrer">Buka Gambar</a>
                                            </div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                </section>
            <?php endif; ?>
<?php
render_admin_page_end();
