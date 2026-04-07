<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/admin.php';

require_role('super_admin');

function settings_tab_url(string $tab): string
{
    return app_url('settings.php') . '?tab=' . urlencode($tab);
}

function normalize_ticker_text(string $text): string
{
    $lines = preg_split('/\R+/', trim($text)) ?: [];
    $lines = array_values(array_filter(array_map(static fn (string $line): string => trim($line), $lines), static fn (string $line): bool => $line !== ''));

    return implode("\n", $lines);
}

function is_valid_setting_asset_reference(string $value): bool
{
    $value = trim($value);

    if ($value === '') {
        return true;
    }

    if (preg_match('/^https?:\/\//i', $value) === 1) {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    return preg_match('/^[A-Za-z0-9_\/.\-]+$/', $value) === 1;
}

$availableTabs = [
    'general' => 'General',
    'meta' => 'Meta & SEO',
    'prayer' => 'Jadwal Shalat',
    'categories' => 'Master Kategori',
    'users' => 'User',
    'ticker' => 'Teks Berjalan',
];

$activeTab = (string) ($_GET['tab'] ?? 'general');
if (!array_key_exists($activeTab, $availableTabs)) {
    $activeTab = 'general';
}

$categoryGroups = master_category_labels();
$currentUser = current_user();
$categoryEditId = isset($_GET['category_edit']) ? (int) $_GET['category_edit'] : 0;
$userEditId = isset($_GET['user_edit']) ? (int) $_GET['user_edit'] : 0;

$generalDefaults = general_setting_defaults();
$generalForm = [];
foreach ($generalDefaults as $key => $default) {
    $generalForm[$key] = configuration_get($key, $default) ?? $default;
}

$metaDefaults = meta_setting_defaults();
$metaForm = [];
foreach ($metaDefaults as $key => $default) {
    $metaForm[$key] = configuration_get($key, $default) ?? $default;
}

$prayerDefaults = prayer_setting_defaults();
$prayerForm = [];
foreach ($prayerDefaults as $key => $default) {
    $prayerForm[$key] = configuration_get($key, $default) ?? $default;
}

$tickerForm = [
    'ticker_text' => implode("\n", configuration_lines('homepage_ticker_text', homepage_ticker_defaults())),
];

$categoryForm = [
    'id' => '0',
    'group_key' => 'study_schedule',
    'name' => '',
    'sort_order' => '0',
    'is_active' => '1',
];

$userForm = [
    'id' => '0',
    'full_name' => '',
    'email' => '',
    'password' => '',
    'role' => 'admin',
    'is_active' => '1',
];

$categoriesByGroup = [];
$users = [];
$settingsError = null;
$errors = [
    'general' => [],
    'meta' => [],
    'prayer' => [],
    'categories' => [],
    'users' => [],
    'ticker' => [],
];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf_token('settings.php?tab=' . urlencode((string) ($_POST['tab'] ?? $activeTab)));
        $action = (string) ($_POST['action'] ?? '');
        $redirectTab = (string) ($_POST['tab'] ?? $activeTab);

        if (!array_key_exists($redirectTab, $availableTabs)) {
            $redirectTab = 'general';
        }

        if ($action === 'save_general_settings') {
            $generalForm = [
                'site_name' => trim((string) ($_POST['site_name'] ?? '')),
                'site_tagline' => trim((string) ($_POST['site_tagline'] ?? '')),
                'site_address' => trim((string) ($_POST['site_address'] ?? '')),
                'google_analytics_code' => strtoupper(trim((string) ($_POST['google_analytics_code'] ?? ''))),
                'google_maps_url' => trim((string) ($_POST['google_maps_url'] ?? '')),
                'google_maps_view' => trim((string) ($_POST['google_maps_view'] ?? 'satellite')),
                'whatsapp_channel_url' => trim((string) ($_POST['whatsapp_channel_url'] ?? '')),
            ];

            if ($generalForm['site_name'] === '') {
                $errors['general']['site_name'] = 'Nama website wajib diisi.';
            }

            if ($generalForm['site_address'] === '') {
                $errors['general']['site_address'] = 'Alamat wajib diisi.';
            }

            if (
                $generalForm['google_analytics_code'] !== ''
                && preg_match('/^(G-[A-Z0-9]+|UA-\d+-\d+)$/', $generalForm['google_analytics_code']) !== 1
            ) {
                $errors['general']['google_analytics_code'] = 'Kode Google Analytics harus berupa Measurement ID yang valid, misalnya G-XXXXXXXXXX.';
            }

            if (
                $generalForm['google_maps_url'] !== ''
                && filter_var($generalForm['google_maps_url'], FILTER_VALIDATE_URL) === false
            ) {
                $errors['general']['google_maps_url'] = 'URL Google Maps harus valid.';
            }

            if (
                $generalForm['whatsapp_channel_url'] !== ''
                && filter_var($generalForm['whatsapp_channel_url'], FILTER_VALIDATE_URL) === false
            ) {
                $errors['general']['whatsapp_channel_url'] = 'URL WhatsApp Channel harus valid.';
            }

            if (!in_array($generalForm['google_maps_view'], ['roadmap', 'satellite'], true)) {
                $errors['general']['google_maps_view'] = 'Mode peta tidak valid.';
            }

            if ($errors['general'] === []) {
                configuration_set_many($generalForm);
                set_flash('success', 'Setting general berhasil disimpan.');
                redirect_to('/settings.php?tab=' . $redirectTab);
            }
        }

        if ($action === 'save_meta_settings') {
            $metaForm = [
                'meta_description' => trim((string) ($_POST['meta_description'] ?? '')),
                'meta_keywords' => trim((string) ($_POST['meta_keywords'] ?? '')),
                'og_type' => trim((string) ($_POST['og_type'] ?? 'website')),
                'og_title' => trim((string) ($_POST['og_title'] ?? '')),
                'og_description' => trim((string) ($_POST['og_description'] ?? '')),
                'og_image' => trim((string) ($_POST['og_image'] ?? '')),
                'twitter_card' => trim((string) ($_POST['twitter_card'] ?? 'summary_large_image')),
                'twitter_title' => trim((string) ($_POST['twitter_title'] ?? '')),
                'twitter_description' => trim((string) ($_POST['twitter_description'] ?? '')),
                'twitter_image' => trim((string) ($_POST['twitter_image'] ?? '')),
                'favicon_url' => trim((string) ($_POST['favicon_url'] ?? '')),
            ];

            if (!in_array($metaForm['og_type'], ['website', 'article', 'profile'], true)) {
                $errors['meta']['og_type'] = 'Tipe Open Graph tidak valid.';
            }

            if (!in_array($metaForm['twitter_card'], ['summary', 'summary_large_image'], true)) {
                $errors['meta']['twitter_card'] = 'Tipe Twitter card tidak valid.';
            }

            foreach ([
                'og_image' => 'Gambar Open Graph',
                'twitter_image' => 'Gambar Twitter',
                'favicon_url' => 'Favicon',
            ] as $field => $label) {
                if (!is_valid_setting_asset_reference($metaForm[$field])) {
                    $errors['meta'][$field] = $label . ' harus berupa URL valid atau path file relatif, misalnya `assets/img/logo.png`.';
                }
            }

            if ($errors['meta'] === []) {
                configuration_set_many($metaForm);
                set_flash('success', 'Setting Meta & SEO berhasil disimpan.');
                redirect_to('/settings.php?tab=' . $redirectTab);
            }
        }

        if ($action === 'save_prayer_settings') {
            $prayerForm = [
                'prayer_api_province' => trim((string) ($_POST['prayer_api_province'] ?? '')),
                'prayer_api_city' => trim((string) ($_POST['prayer_api_city'] ?? '')),
                'prayer_offset_subuh' => trim((string) ($_POST['prayer_offset_subuh'] ?? '0')),
                'prayer_offset_dzuhur' => trim((string) ($_POST['prayer_offset_dzuhur'] ?? '0')),
                'prayer_offset_ashar' => trim((string) ($_POST['prayer_offset_ashar'] ?? '0')),
                'prayer_offset_maghrib' => trim((string) ($_POST['prayer_offset_maghrib'] ?? '0')),
                'prayer_offset_isya' => trim((string) ($_POST['prayer_offset_isya'] ?? '0')),
            ];

            if ($prayerForm['prayer_api_province'] === '') {
                $errors['prayer']['prayer_api_province'] = 'Provinsi wajib diisi.';
            }
            if ($prayerForm['prayer_api_city'] === '') {
                $errors['prayer']['prayer_api_city'] = 'Kabupaten/kota wajib diisi.';
            }

            foreach (['subuh', 'dzuhur', 'ashar', 'maghrib', 'isya'] as $prayerKey) {
                $offsetKey = 'prayer_offset_' . $prayerKey;
                if ($prayerForm[$offsetKey] === '' || filter_var($prayerForm[$offsetKey], FILTER_VALIDATE_INT) === false) {
                    $errors['prayer'][$offsetKey] = 'Offset harus berupa bilangan bulat.';
                    continue;
                }

                $value = (int) $prayerForm[$offsetKey];
                if ($value < -120 || $value > 120) {
                    $errors['prayer'][$offsetKey] = 'Offset maksimal antara -120 sampai 120 menit.';
                }
            }

            if ($errors['prayer'] === []) {
                configuration_set_many($prayerForm);
                set_flash('success', 'Setting jadwal shalat berhasil disimpan.');
                redirect_to('/settings.php?tab=' . $redirectTab);
            }
        }

        if ($action === 'save_ticker_settings') {
            $tickerForm['ticker_text'] = trim((string) ($_POST['ticker_text'] ?? ''));

            if ($tickerForm['ticker_text'] === '') {
                $errors['ticker']['ticker_text'] = 'Isi minimal satu baris teks berjalan.';
            }

            if ($errors['ticker'] === []) {
                configuration_set('homepage_ticker_text', normalize_ticker_text($tickerForm['ticker_text']));
                set_flash('success', 'Teks berjalan homepage berhasil diperbarui.');
                redirect_to('/settings.php?tab=' . $redirectTab);
            }
        }

        if ($action === 'save_category') {
            $categoryForm = [
                'id' => trim((string) ($_POST['id'] ?? '0')),
                'group_key' => trim((string) ($_POST['group_key'] ?? 'study_schedule')),
                'name' => trim((string) ($_POST['name'] ?? '')),
                'sort_order' => trim((string) ($_POST['sort_order'] ?? '0')),
                'is_active' => isset($_POST['is_active']) ? '1' : '0',
            ];

            if (!array_key_exists($categoryForm['group_key'], $categoryGroups)) {
                $errors['categories']['group_key'] = 'Grup kategori tidak valid.';
            }
            if ($categoryForm['name'] === '') {
                $errors['categories']['name'] = 'Nama kategori wajib diisi.';
            }
            if (filter_var($categoryForm['sort_order'], FILTER_VALIDATE_INT) === false) {
                $errors['categories']['sort_order'] = 'Urutan harus berupa bilangan bulat.';
            }

            if ($errors['categories'] === []) {
                $payload = [
                    'group_key' => $categoryForm['group_key'],
                    'name' => $categoryForm['name'],
                    'sort_order' => (int) $categoryForm['sort_order'],
                    'is_active' => (int) $categoryForm['is_active'],
                ];

                if ((int) $categoryForm['id'] > 0) {
                    $payload['id'] = (int) $categoryForm['id'];
                    db()->prepare(
                        'UPDATE master_categories
                         SET group_key = :group_key, name = :name, sort_order = :sort_order, is_active = :is_active
                         WHERE id = :id'
                    )->execute($payload);
                    set_flash('success', 'Kategori berhasil diperbarui.');
                } else {
                    db()->prepare(
                        'INSERT INTO master_categories (group_key, name, sort_order, is_active)
                         VALUES (:group_key, :name, :sort_order, :is_active)'
                    )->execute($payload);
                    set_flash('success', 'Kategori baru berhasil ditambahkan.');
                }

                redirect_to('/settings.php?tab=' . $redirectTab);
            }
        }

        if ($action === 'delete_category') {
            $id = (int) ($_POST['id'] ?? 0);
            db()->prepare('DELETE FROM master_categories WHERE id = :id')->execute(['id' => $id]);
            set_flash('success', 'Kategori berhasil dihapus.');
            redirect_to('/settings.php?tab=' . $redirectTab);
        }

        if ($action === 'save_user') {
            $userForm = [
                'id' => trim((string) ($_POST['id'] ?? '0')),
                'full_name' => trim((string) ($_POST['full_name'] ?? '')),
                'email' => trim((string) ($_POST['email'] ?? '')),
                'password' => trim((string) ($_POST['password'] ?? '')),
                'role' => trim((string) ($_POST['role'] ?? 'admin')),
                'is_active' => isset($_POST['is_active']) ? '1' : '0',
            ];

            if ($userForm['full_name'] === '') {
                $errors['users']['full_name'] = 'Nama user wajib diisi.';
            }
            if ($userForm['email'] === '' || !filter_var($userForm['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['users']['email'] = 'Email wajib valid.';
            }
            if (!in_array($userForm['role'], ['super_admin', 'admin', 'editor'], true)) {
                $errors['users']['role'] = 'Role user tidak valid.';
            }
            if ((int) $userForm['id'] === 0 && $userForm['password'] === '') {
                $errors['users']['password'] = 'Password wajib diisi untuk user baru.';
            }
            if ((int) $userForm['id'] === (int) ($currentUser['id'] ?? 0) && $userForm['is_active'] !== '1') {
                $errors['users']['is_active'] = 'Akun yang sedang dipakai tidak bisa dinonaktifkan.';
            }

            if ($errors['users'] === []) {
                $emailCheck = db()->prepare('SELECT id FROM admin_users WHERE email = :email LIMIT 1');
                $emailCheck->execute(['email' => $userForm['email']]);
                $existing = $emailCheck->fetch();

                if ($existing !== false && (int) $existing['id'] !== (int) $userForm['id']) {
                    $errors['users']['email'] = 'Email ini sudah dipakai user lain.';
                }
            }

            if ($errors['users'] === []) {
                $payload = [
                    'full_name' => $userForm['full_name'],
                    'email' => $userForm['email'],
                    'role' => $userForm['role'],
                    'is_active' => (int) $userForm['is_active'],
                ];

                if ((int) $userForm['id'] > 0) {
                    $payload['id'] = (int) $userForm['id'];

                    if ($userForm['password'] !== '') {
                        $payload['password_hash'] = password_hash($userForm['password'], PASSWORD_DEFAULT);
                        db()->prepare(
                            'UPDATE admin_users
                             SET full_name = :full_name, email = :email, password_hash = :password_hash, role = :role, is_active = :is_active
                             WHERE id = :id'
                        )->execute($payload);
                    } else {
                        db()->prepare(
                            'UPDATE admin_users
                             SET full_name = :full_name, email = :email, role = :role, is_active = :is_active
                             WHERE id = :id'
                        )->execute($payload);
                    }

                    if ((int) $userForm['id'] === (int) ($currentUser['id'] ?? 0)) {
                        $_SESSION['user']['full_name'] = $userForm['full_name'];
                        $_SESSION['user']['email'] = $userForm['email'];
                        $_SESSION['user']['role'] = $userForm['role'];
                    }

                    set_flash('success', 'User berhasil diperbarui.');
                } else {
                    $payload['password_hash'] = password_hash($userForm['password'], PASSWORD_DEFAULT);
                    db()->prepare(
                        'INSERT INTO admin_users (full_name, email, password_hash, role, is_active)
                         VALUES (:full_name, :email, :password_hash, :role, :is_active)'
                    )->execute($payload);
                    set_flash('success', 'User baru berhasil ditambahkan.');
                }

                redirect_to('/settings.php?tab=' . $redirectTab);
            }
        }

        if ($action === 'delete_user') {
            $id = (int) ($_POST['id'] ?? 0);

            if ($id === (int) ($currentUser['id'] ?? 0)) {
                set_flash('error', 'Akun yang sedang dipakai tidak bisa dihapus.');
            } else {
                db()->prepare('DELETE FROM admin_users WHERE id = :id')->execute(['id' => $id]);
                set_flash('success', 'User berhasil dihapus.');
            }

            redirect_to('/settings.php?tab=' . $redirectTab);
        }
    }
} catch (Throwable) {
    $settingsError = 'Halaman setting belum siap dipakai. Jalankan ulang `sql/schema.sql` agar tabel `site_settings` dan `master_categories` tersedia.';
}

if ($settingsError === null) {
    try {
        $categoryRows = db()->query(
            'SELECT * FROM master_categories ORDER BY group_key ASC, sort_order ASC, name ASC'
        )->fetchAll();

        foreach (array_keys($categoryGroups) as $groupKey) {
            $categoriesByGroup[$groupKey] = [];
        }

        foreach ($categoryRows as $row) {
            $groupKey = (string) $row['group_key'];
            if (!isset($categoriesByGroup[$groupKey])) {
                $categoriesByGroup[$groupKey] = [];
            }
            $categoriesByGroup[$groupKey][] = $row;
        }

        $users = db()->query(
            'SELECT id, full_name, email, role, is_active, last_login_at, created_at
             FROM admin_users
             ORDER BY created_at DESC, id DESC'
        )->fetchAll();

        if ($categoryEditId > 0) {
            $statement = db()->prepare('SELECT * FROM master_categories WHERE id = :id LIMIT 1');
            $statement->execute(['id' => $categoryEditId]);
            $categoryRecord = $statement->fetch();

            if ($categoryRecord !== false) {
                $categoryForm = [
                    'id' => (string) $categoryRecord['id'],
                    'group_key' => (string) $categoryRecord['group_key'],
                    'name' => (string) $categoryRecord['name'],
                    'sort_order' => (string) $categoryRecord['sort_order'],
                    'is_active' => (string) $categoryRecord['is_active'],
                ];
                $activeTab = 'categories';
            }
        }

        if ($userEditId > 0) {
            $statement = db()->prepare('SELECT * FROM admin_users WHERE id = :id LIMIT 1');
            $statement->execute(['id' => $userEditId]);
            $userRecord = $statement->fetch();

            if ($userRecord !== false) {
                $userForm = [
                    'id' => (string) $userRecord['id'],
                    'full_name' => (string) $userRecord['full_name'],
                    'email' => (string) $userRecord['email'],
                    'password' => '',
                    'role' => (string) $userRecord['role'],
                    'is_active' => (string) $userRecord['is_active'],
                ];
                $activeTab = 'users';
            }
        }
    } catch (Throwable) {
        $settingsError = 'Data setting tidak bisa dimuat. Pastikan schema terbaru sudah diimport.';
    }
}

render_admin_page_start('Setting', 'settings');
render_admin_page_header(
    'System Settings',
    'Pengaturan Website',
    'Atur setting general, sumber jadwal shalat, master kategori, user admin, dan teks berjalan homepage dari satu halaman bertab.',
    [
        ['href' => 'dashboard.php', 'label' => 'Kembali ke Dashboard', 'secondary' => true],
    ]
);
?>
            <?php if ($settingsError !== null): ?>
                <div class="flash-message flash-message--error"><?= h($settingsError); ?></div>
            <?php else: ?>
                <nav class="tab-nav" aria-label="Tab setting">
                    <?php foreach ($availableTabs as $tabKey => $tabLabel): ?>
                        <a class="tab-link <?= $activeTab === $tabKey ? 'is-active' : ''; ?>" href="<?= h(settings_tab_url($tabKey)); ?>">
                            <?= h($tabLabel); ?>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <?php if ($activeTab === 'general'): ?>
                    <section class="content-grid content-grid--single">
                        <article class="card card--form">
                            <div class="card-heading">
                                <div>
                                    <p class="eyebrow">General Settings</p>
                                    <h2>Informasi Umum Website</h2>
                                </div>
                            </div>

                            <form method="post" class="admin-form">
                                <?= csrf_input(); ?>
                                <input type="hidden" name="action" value="save_general_settings">
                                <input type="hidden" name="tab" value="general">

                                <div class="field-grid">
                                    <div class="field-group">
                                        <label for="site_name">Nama Website / Masjid</label>
                                        <input class="admin-input" id="site_name" name="site_name" type="text" value="<?= h((string) $generalForm['site_name']); ?>">
                                        <?php if (isset($errors['general']['site_name'])): ?><p class="field-error"><?= h($errors['general']['site_name']); ?></p><?php endif; ?>
                                    </div>
                                    <div class="field-group">
                                        <label for="site_tagline">Tagline</label>
                                        <input class="admin-input" id="site_tagline" name="site_tagline" type="text" value="<?= h((string) $generalForm['site_tagline']); ?>" placeholder="Pusat Ibadah, Dakwah, dan Pelayanan Umat">
                                        <p class="field-help">Opsional. Boleh dikosongkan jika tidak ingin menampilkan tagline.</p>
                                    </div>
                                    <div class="field-group">
                                        <label for="site_address">Alamat</label>
                                        <input class="admin-input" id="site_address" name="site_address" type="text" value="<?= h((string) $generalForm['site_address']); ?>">
                                        <?php if (isset($errors['general']['site_address'])): ?><p class="field-error"><?= h($errors['general']['site_address']); ?></p><?php endif; ?>
                                    </div>
                                    <div class="field-group">
                                        <label for="google_analytics_code">Kode Google Analytics</label>
                                        <input class="admin-input" id="google_analytics_code" name="google_analytics_code" type="text" value="<?= h((string) $generalForm['google_analytics_code']); ?>" placeholder="G-XXXXXXXXXX">
                                        <p class="field-help">Opsional. Isi Measurement ID Google Analytics, misalnya `G-XXXXXXXXXX`.</p>
                                        <?php if (isset($errors['general']['google_analytics_code'])): ?><p class="field-error"><?= h($errors['general']['google_analytics_code']); ?></p><?php endif; ?>
                                    </div>
                                    <div class="field-group field-group--full">
                                        <label for="google_maps_url">URL Google Maps</label>
                                        <input class="admin-input" id="google_maps_url" name="google_maps_url" type="url" value="<?= h((string) $generalForm['google_maps_url']); ?>" placeholder="https://maps.google.com/...">
                                        <p class="field-help">Gunakan link pin/share lokasi dari Google Maps agar preview peta lebih presisi dan tidak terlalu zoom out.</p>
                                        <?php if (isset($errors['general']['google_maps_url'])): ?><p class="field-error"><?= h($errors['general']['google_maps_url']); ?></p><?php endif; ?>
                                    </div>
                                    <div class="field-group">
                                        <label for="google_maps_view">Mode Tampilan Peta</label>
                                        <select class="admin-input" id="google_maps_view" name="google_maps_view">
                                            <option value="satellite" <?= (string) $generalForm['google_maps_view'] === 'satellite' ? 'selected' : ''; ?>>Satellite Imagery</option>
                                            <option value="roadmap" <?= (string) $generalForm['google_maps_view'] === 'roadmap' ? 'selected' : ''; ?>>Roadmap</option>
                                        </select>
                                        <?php if (isset($errors['general']['google_maps_view'])): ?><p class="field-error"><?= h($errors['general']['google_maps_view']); ?></p><?php endif; ?>
                                    </div>
                                    <div class="field-group field-group--full">
                                        <label for="whatsapp_channel_url">URL WhatsApp Channel</label>
                                        <input class="admin-input" id="whatsapp_channel_url" name="whatsapp_channel_url" type="url" value="<?= h((string) $generalForm['whatsapp_channel_url']); ?>" placeholder="https://whatsapp.com/channel/...">
                                        <p class="field-help">Link ini akan ditampilkan di homepage sebagai ajakan bergabung ke channel WhatsApp.</p>
                                        <?php if (isset($errors['general']['whatsapp_channel_url'])): ?><p class="field-error"><?= h($errors['general']['whatsapp_channel_url']); ?></p><?php endif; ?>
                                    </div>
                                </div>

                                <div class="form-actions">
                                    <button class="button-link" type="submit">Simpan General</button>
                                </div>
                            </form>
                        </article>
                    </section>
                <?php endif; ?>

                <?php if ($activeTab === 'meta'): ?>
                    <section class="content-grid content-grid--single">
                        <article class="card card--form">
                            <div class="card-heading">
                                <div>
                                    <p class="eyebrow">Meta & SEO</p>
                                    <h2>Tag Meta Dasar, Sosial, dan Favicon</h2>
                                </div>
                            </div>

                            <form method="post" class="admin-form">
                                <?= csrf_input(); ?>
                                <input type="hidden" name="action" value="save_meta_settings">
                                <input type="hidden" name="tab" value="meta">

                                <div class="field-grid">
                                    <div class="field-group field-group--full">
                                        <label for="meta_description">Meta Description</label>
                                        <textarea class="admin-input" id="meta_description" name="meta_description" rows="4" placeholder="Gabung dalam kegiatan rutin dan dukung dakwah kami."><?= h((string) $metaForm['meta_description']); ?></textarea>
                                        <p class="field-help">Dipakai untuk `<meta name="description">` dan akan jadi fallback untuk deskripsi Open Graph/Twitter jika kolom khusus dikosongkan.</p>
                                    </div>
                                    <div class="field-group field-group--full">
                                        <label for="meta_keywords">Meta Keywords</label>
                                        <input class="admin-input" id="meta_keywords" name="meta_keywords" type="text" value="<?= h((string) $metaForm['meta_keywords']); ?>" placeholder="masjid, kajian islam, dakwah, payakumbuh">
                                        <p class="field-help">Opsional. Pisahkan dengan koma jika ingin menambahkan kata kunci dasar website.</p>
                                    </div>
                                </div>

                                <div class="card-heading">
                                    <div>
                                        <p class="eyebrow">Open Graph</p>
                                        <h3>SEO untuk Media Sosial</h3>
                                    </div>
                                </div>

                                <div class="field-grid">
                                    <div class="field-group">
                                        <label for="og_type">OG Type</label>
                                        <select class="admin-input" id="og_type" name="og_type">
                                            <option value="website" <?= (string) $metaForm['og_type'] === 'website' ? 'selected' : ''; ?>>website</option>
                                            <option value="article" <?= (string) $metaForm['og_type'] === 'article' ? 'selected' : ''; ?>>article</option>
                                            <option value="profile" <?= (string) $metaForm['og_type'] === 'profile' ? 'selected' : ''; ?>>profile</option>
                                        </select>
                                        <?php if (isset($errors['meta']['og_type'])): ?><p class="field-error"><?= h($errors['meta']['og_type']); ?></p><?php endif; ?>
                                    </div>
                                    <div class="field-group field-group--full">
                                        <label for="og_title">OG Title</label>
                                        <input class="admin-input" id="og_title" name="og_title" type="text" value="<?= h((string) $metaForm['og_title']); ?>" placeholder="Nama Masjid - Tagline atau pesan utama">
                                        <p class="field-help">Kosongkan jika ingin memakai judul halaman otomatis.</p>
                                    </div>
                                    <div class="field-group field-group--full">
                                        <label for="og_description">OG Description</label>
                                        <textarea class="admin-input" id="og_description" name="og_description" rows="4" placeholder="Gabung dalam kegiatan rutin dan dukung dakwah kami."><?= h((string) $metaForm['og_description']); ?></textarea>
                                    </div>
                                    <div class="field-group field-group--full">
                                        <label for="og_image">OG Image</label>
                                        <input class="admin-input" id="og_image" name="og_image" type="text" value="<?= h((string) $metaForm['og_image']); ?>" placeholder="https://domain-anda.com/gambar-masjid.jpg atau assets/img/masjid.jpg">
                                        <p class="field-help">Boleh isi URL penuh atau path relatif dari proyek. Disarankan gambar horizontal untuk preview yang lebih baik.</p>
                                        <?php if (isset($errors['meta']['og_image'])): ?><p class="field-error"><?= h($errors['meta']['og_image']); ?></p><?php endif; ?>
                                    </div>
                                </div>

                                <div class="card-heading">
                                    <div>
                                        <p class="eyebrow">Twitter Card</p>
                                        <h3>Preview untuk Twitter / X</h3>
                                    </div>
                                </div>

                                <div class="field-grid">
                                    <div class="field-group">
                                        <label for="twitter_card">Twitter Card</label>
                                        <select class="admin-input" id="twitter_card" name="twitter_card">
                                            <option value="summary_large_image" <?= (string) $metaForm['twitter_card'] === 'summary_large_image' ? 'selected' : ''; ?>>summary_large_image</option>
                                            <option value="summary" <?= (string) $metaForm['twitter_card'] === 'summary' ? 'selected' : ''; ?>>summary</option>
                                        </select>
                                        <?php if (isset($errors['meta']['twitter_card'])): ?><p class="field-error"><?= h($errors['meta']['twitter_card']); ?></p><?php endif; ?>
                                    </div>
                                    <div class="field-group field-group--full">
                                        <label for="twitter_title">Twitter Title</label>
                                        <input class="admin-input" id="twitter_title" name="twitter_title" type="text" value="<?= h((string) $metaForm['twitter_title']); ?>" placeholder="Nama Masjid">
                                        <p class="field-help">Kosongkan jika ingin mengikuti judul Open Graph.</p>
                                    </div>
                                    <div class="field-group field-group--full">
                                        <label for="twitter_description">Twitter Description</label>
                                        <textarea class="admin-input" id="twitter_description" name="twitter_description" rows="4"><?= h((string) $metaForm['twitter_description']); ?></textarea>
                                    </div>
                                    <div class="field-group field-group--full">
                                        <label for="twitter_image">Twitter Image</label>
                                        <input class="admin-input" id="twitter_image" name="twitter_image" type="text" value="<?= h((string) $metaForm['twitter_image']); ?>" placeholder="Kosongkan untuk memakai OG Image">
                                        <p class="field-help">Jika kosong, sistem otomatis memakai gambar Open Graph.</p>
                                        <?php if (isset($errors['meta']['twitter_image'])): ?><p class="field-error"><?= h($errors['meta']['twitter_image']); ?></p><?php endif; ?>
                                    </div>
                                </div>

                                <div class="card-heading">
                                    <div>
                                        <p class="eyebrow">Brand Asset</p>
                                        <h3>Favicon Website</h3>
                                    </div>
                                </div>

                                <div class="field-grid">
                                    <div class="field-group field-group--full">
                                        <label for="favicon_url">Favicon</label>
                                        <input class="admin-input" id="favicon_url" name="favicon_url" type="text" value="<?= h((string) $metaForm['favicon_url']); ?>" placeholder="assets/img/favicon.png atau https://domain-anda.com/favicon.ico">
                                        <p class="field-help">Mendukung file `.ico`, `.png`, atau format gambar lain yang dibaca browser sebagai icon.</p>
                                        <?php if (isset($errors['meta']['favicon_url'])): ?><p class="field-error"><?= h($errors['meta']['favicon_url']); ?></p><?php endif; ?>
                                    </div>
                                </div>

                                <div class="form-actions">
                                    <button class="button-link" type="submit">Simpan Meta & SEO</button>
                                </div>
                            </form>
                        </article>
                    </section>
                <?php endif; ?>

                <?php if ($activeTab === 'prayer'): ?>
                    <section class="content-grid content-grid--single">
                        <article class="card card--form">
                            <div class="card-heading">
                                <div>
                                    <p class="eyebrow">Prayer API</p>
                                    <h2>Setting Jadwal Shalat</h2>
                                </div>
                            </div>

                            <form method="post" class="admin-form">
                                <?= csrf_input(); ?>
                                <input type="hidden" name="action" value="save_prayer_settings">
                                <input type="hidden" name="tab" value="prayer">

                                <div class="field-grid">
                                    <div class="field-group">
                                        <label for="prayer_api_province">Provinsi API</label>
                                        <input class="admin-input" id="prayer_api_province" name="prayer_api_province" type="text" value="<?= h((string) $prayerForm['prayer_api_province']); ?>">
                                        <?php if (isset($errors['prayer']['prayer_api_province'])): ?><p class="field-error"><?= h($errors['prayer']['prayer_api_province']); ?></p><?php endif; ?>
                                    </div>
                                    <div class="field-group">
                                        <label for="prayer_api_city">Kab/Kota API</label>
                                        <input class="admin-input" id="prayer_api_city" name="prayer_api_city" type="text" value="<?= h((string) $prayerForm['prayer_api_city']); ?>">
                                        <?php if (isset($errors['prayer']['prayer_api_city'])): ?><p class="field-error"><?= h($errors['prayer']['prayer_api_city']); ?></p><?php endif; ?>
                                    </div>
                                    <?php foreach (['subuh' => 'Subuh', 'dzuhur' => 'Dzuhur', 'ashar' => 'Ashar', 'maghrib' => 'Maghrib', 'isya' => 'Isya'] as $key => $label): ?>
                                        <?php $field = 'prayer_offset_' . $key; ?>
                                        <div class="field-group">
                                            <label for="<?= h($field); ?>">Offset <?= h($label); ?> (menit)</label>
                                            <input class="admin-input" id="<?= h($field); ?>" name="<?= h($field); ?>" type="number" min="-120" max="120" value="<?= h((string) $prayerForm[$field]); ?>">
                                            <?php if (isset($errors['prayer'][$field])): ?><p class="field-error"><?= h($errors['prayer'][$field]); ?></p><?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="form-actions">
                                    <button class="button-link" type="submit">Simpan Jadwal Shalat</button>
                                </div>
                            </form>
                        </article>
                    </section>
                <?php endif; ?>

                <?php if ($activeTab === 'ticker'): ?>
                    <section class="content-grid content-grid--single">
                        <article class="card card--form">
                            <div class="card-heading">
                                <div>
                                    <p class="eyebrow">Homepage Marquee</p>
                                    <h2>Setting teks berjalan</h2>
                                </div>
                            </div>

                            <form method="post" class="admin-form">
                                <?= csrf_input(); ?>
                                <input type="hidden" name="action" value="save_ticker_settings">
                                <input type="hidden" name="tab" value="ticker">

                                <div class="field-group field-group--full">
                                    <label for="ticker_text">Teks Berjalan Homepage</label>
                                    <textarea class="admin-input" id="ticker_text" name="ticker_text" rows="8"><?= h($tickerForm['ticker_text']); ?></textarea>
                                    <p class="field-help">Tulis satu kalimat per baris. Setiap baris akan tampil sebagai item teks berjalan di homepage.</p>
                                    <?php if (isset($errors['ticker']['ticker_text'])): ?><p class="field-error"><?= h($errors['ticker']['ticker_text']); ?></p><?php endif; ?>
                                </div>

                                <div class="form-actions">
                                    <button class="button-link" type="submit">Simpan Teks Berjalan</button>
                                </div>
                            </form>
                        </article>
                    </section>
                <?php endif; ?>

                <?php if ($activeTab === 'categories'): ?>
                    <section class="content-grid">
                        <article class="card card--form">
                            <div class="card-heading">
                                <div>
                                    <p class="eyebrow"><?= (int) $categoryForm['id'] > 0 ? 'Edit Kategori' : 'Tambah Kategori'; ?></p>
                                    <h2><?= (int) $categoryForm['id'] > 0 ? 'Perbarui kategori master' : 'Tambah kategori master'; ?></h2>
                                </div>
                            </div>

                            <form method="post" class="admin-form">
                                <?= csrf_input(); ?>
                                <input type="hidden" name="action" value="save_category">
                                <input type="hidden" name="tab" value="categories">
                                <input type="hidden" name="id" value="<?= h($categoryForm['id']); ?>">

                                <div class="field-grid">
                                    <div class="field-group">
                                        <label for="group_key">Grup Kategori</label>
                                        <select class="admin-input" id="group_key" name="group_key">
                                            <?php foreach ($categoryGroups as $groupKey => $groupLabel): ?>
                                                <option value="<?= h($groupKey); ?>" <?= $categoryForm['group_key'] === $groupKey ? 'selected' : ''; ?>><?= h($groupLabel); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (isset($errors['categories']['group_key'])): ?><p class="field-error"><?= h($errors['categories']['group_key']); ?></p><?php endif; ?>
                                    </div>
                                    <div class="field-group">
                                        <label for="name">Nama Kategori</label>
                                        <input class="admin-input" id="name" name="name" type="text" value="<?= h($categoryForm['name']); ?>">
                                        <?php if (isset($errors['categories']['name'])): ?><p class="field-error"><?= h($errors['categories']['name']); ?></p><?php endif; ?>
                                    </div>
                                    <div class="field-group">
                                        <label for="sort_order">Urutan</label>
                                        <input class="admin-input" id="sort_order" name="sort_order" type="number" value="<?= h($categoryForm['sort_order']); ?>">
                                        <?php if (isset($errors['categories']['sort_order'])): ?><p class="field-error"><?= h($errors['categories']['sort_order']); ?></p><?php endif; ?>
                                    </div>
                                    <div class="field-group field-group--checkbox">
                                        <label class="checkbox-field">
                                            <input type="checkbox" name="is_active" value="1" <?= $categoryForm['is_active'] === '1' ? 'checked' : ''; ?>>
                                            <span>Aktifkan kategori</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="form-actions">
                                    <button class="button-link" type="submit"><?= (int) $categoryForm['id'] > 0 ? 'Simpan Kategori' : 'Tambah Kategori'; ?></button>
                                    <?php if ((int) $categoryForm['id'] > 0): ?>
                                        <a class="button-link button-link--secondary" href="<?= h(settings_tab_url('categories')); ?>">Batal Edit</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </article>

                        <article class="card card--list">
                            <div class="card-heading">
                                <div>
                                    <p class="eyebrow">Master Data</p>
                                    <h2>Daftar kategori</h2>
                                </div>
                            </div>

                            <div class="settings-stack">
                                <?php foreach ($categoryGroups as $groupKey => $groupLabel): ?>
                                    <section class="settings-subsection">
                                        <div class="settings-subsection__head">
                                            <h3><?= h($groupLabel); ?></h3>
                                            <span class="metric-chip"><?= h((string) count($categoriesByGroup[$groupKey] ?? [])); ?> data</span>
                                        </div>
                                        <div class="table-wrap">
                                            <table class="admin-table">
                                                <thead>
                                                    <tr>
                                                        <th>Nama</th>
                                                        <th>Urutan</th>
                                                        <th>Status</th>
                                                        <th>Aksi</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach (($categoriesByGroup[$groupKey] ?? []) as $item): ?>
                                                        <tr>
                                                            <td><strong><?= h((string) $item['name']); ?></strong></td>
                                                            <td><?= h((string) $item['sort_order']); ?></td>
                                                            <td><span class="<?= h((int) $item['is_active'] === 1 ? 'badge badge--active' : 'badge badge--draft'); ?>"><?= h((int) $item['is_active'] === 1 ? 'active' : 'inactive'); ?></span></td>
                                                            <td>
                                                                <div class="inline-actions">
                                                                    <a class="button-small" href="<?= h(settings_tab_url('categories')); ?>&category_edit=<?= h((string) $item['id']); ?>">Edit</a>
                                                                    <form method="post" onsubmit="return confirm('Hapus kategori ini?');">
                                                                        <?= csrf_input(); ?>
                                                                        <input type="hidden" name="action" value="delete_category">
                                                                        <input type="hidden" name="tab" value="categories">
                                                                        <input type="hidden" name="id" value="<?= h((string) $item['id']); ?>">
                                                                        <button class="button-small button-small--danger" type="submit">Hapus</button>
                                                                    </form>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </section>
                                <?php endforeach; ?>
                            </div>
                        </article>
                    </section>
                <?php endif; ?>

                <?php if ($activeTab === 'users'): ?>
                    <section class="content-grid">
                        <article class="card card--form">
                            <div class="card-heading">
                                <div>
                                    <p class="eyebrow"><?= (int) $userForm['id'] > 0 ? 'Edit User' : 'Tambah User'; ?></p>
                                    <h2><?= (int) $userForm['id'] > 0 ? 'Perbarui user admin' : 'Tambahkan user admin'; ?></h2>
                                </div>
                            </div>

                            <form method="post" class="admin-form">
                                <?= csrf_input(); ?>
                                <input type="hidden" name="action" value="save_user">
                                <input type="hidden" name="tab" value="users">
                                <input type="hidden" name="id" value="<?= h($userForm['id']); ?>">

                                <div class="field-grid">
                                    <div class="field-group field-group--full">
                                        <label for="full_name">Nama Lengkap</label>
                                        <input class="admin-input" id="full_name" name="full_name" type="text" value="<?= h($userForm['full_name']); ?>">
                                        <?php if (isset($errors['users']['full_name'])): ?><p class="field-error"><?= h($errors['users']['full_name']); ?></p><?php endif; ?>
                                    </div>
                                    <div class="field-group">
                                        <label for="email">Email</label>
                                        <input class="admin-input" id="email" name="email" type="email" value="<?= h($userForm['email']); ?>">
                                        <?php if (isset($errors['users']['email'])): ?><p class="field-error"><?= h($errors['users']['email']); ?></p><?php endif; ?>
                                    </div>
                                    <div class="field-group">
                                        <label for="role">Role</label>
                                        <select class="admin-input" id="role" name="role">
                                            <?php foreach (['super_admin' => 'Super Admin', 'admin' => 'Admin', 'editor' => 'Editor'] as $value => $label): ?>
                                                <option value="<?= h($value); ?>" <?= $userForm['role'] === $value ? 'selected' : ''; ?>><?= h($label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (isset($errors['users']['role'])): ?><p class="field-error"><?= h($errors['users']['role']); ?></p><?php endif; ?>
                                    </div>
                                    <div class="field-group field-group--full">
                                        <label for="password">Password <?= (int) $userForm['id'] > 0 ? '(opsional)' : ''; ?></label>
                                        <input class="admin-input" id="password" name="password" type="password" value="">
                                        <?php if (isset($errors['users']['password'])): ?><p class="field-error"><?= h($errors['users']['password']); ?></p><?php endif; ?>
                                    </div>
                                    <div class="field-group field-group--checkbox">
                                        <label class="checkbox-field">
                                            <input type="checkbox" name="is_active" value="1" <?= $userForm['is_active'] === '1' ? 'checked' : ''; ?>>
                                            <span>User aktif</span>
                                        </label>
                                        <?php if (isset($errors['users']['is_active'])): ?><p class="field-error"><?= h($errors['users']['is_active']); ?></p><?php endif; ?>
                                    </div>
                                </div>

                                <div class="form-actions">
                                    <button class="button-link" type="submit"><?= (int) $userForm['id'] > 0 ? 'Simpan User' : 'Tambah User'; ?></button>
                                    <?php if ((int) $userForm['id'] > 0): ?>
                                        <a class="button-link button-link--secondary" href="<?= h(settings_tab_url('users')); ?>">Batal Edit</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </article>

                        <article class="card card--list">
                            <div class="card-heading">
                                <div>
                                    <p class="eyebrow">User Management</p>
                                    <h2>Daftar user admin</h2>
                                </div>
                                <div class="metric-chip"><?= h((string) count($users)); ?> data</div>
                            </div>

                            <div class="table-wrap">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>Nama</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Login Terakhir</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td><strong><?= h((string) $user['full_name']); ?></strong></td>
                                                <td><?= h((string) $user['email']); ?></td>
                                                <td><?= h((string) $user['role']); ?></td>
                                                <td><span class="<?= h((int) $user['is_active'] === 1 ? 'badge badge--active' : 'badge badge--draft'); ?>"><?= h((int) $user['is_active'] === 1 ? 'active' : 'inactive'); ?></span></td>
                                                <td><?= h(format_human_date($user['last_login_at'] !== null ? (string) $user['last_login_at'] : null, true)); ?></td>
                                                <td>
                                                    <div class="inline-actions">
                                                        <a class="button-small" href="<?= h(settings_tab_url('users')); ?>&user_edit=<?= h((string) $user['id']); ?>">Edit</a>
                                                        <form method="post" onsubmit="return confirm('Hapus user ini?');">
                                                            <?= csrf_input(); ?>
                                                            <input type="hidden" name="action" value="delete_user">
                                                            <input type="hidden" name="tab" value="users">
                                                            <input type="hidden" name="id" value="<?= h((string) $user['id']); ?>">
                                                            <button class="button-small button-small--danger" type="submit" <?= (int) $user['id'] === (int) ($currentUser['id'] ?? 0) ? 'disabled' : ''; ?>>Hapus</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </article>
                    </section>
                <?php endif; ?>
            <?php endif; ?>
<?php
render_admin_page_end();
