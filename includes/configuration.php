<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

function master_category_defaults(): array
{
    return [
        'study_schedule' => ['Aqidah', 'Akhlak', 'Fiqh', 'Hadits', 'Keluarga', 'Muamalah', 'Ramadhan', 'Sejarah', 'Tafsir'],
        'video' => ['Aqidah', 'Akhlak', 'Fiqh', 'Keluarga', 'Kajian Umum', 'Muamalah', 'Ramadhan Special', 'Sejarah', 'Tafsir'],
        'article' => ['Akhlak', 'Ibadah', 'Keluarga', 'Muamalah', 'Renungan', 'Sejarah', 'Spiritualitas', 'Tafsir'],
        'report' => ['Laporan Kegiatan', 'Keuangan Bulanan', 'Program Sosial', 'Dokumen Publik'],
    ];
}

function master_category_labels(): array
{
    return [
        'study_schedule' => 'Kategori Kajian',
        'video' => 'Kategori Video',
        'article' => 'Kategori Artikel',
        'report' => 'Kategori Laporan',
    ];
}

function general_setting_defaults(): array
{
    return [
        'site_name' => 'Website Masjid',
        'site_tagline' => 'Pusat Ibadah, Dakwah, dan Pelayanan Umat',
        'site_address' => 'Kota / Kabupaten Anda',
        'google_analytics_code' => '',
        'google_maps_url' => '',
        'google_maps_view' => 'satellite',
        'whatsapp_channel_url' => '',
    ];
}

function meta_setting_defaults(): array
{
    return [
        'meta_description' => '',
        'meta_keywords' => '',
        'og_type' => 'website',
        'og_title' => '',
        'og_description' => '',
        'og_image' => '',
        'twitter_card' => 'summary_large_image',
        'twitter_title' => '',
        'twitter_description' => '',
        'twitter_image' => '',
        'favicon_url' => '',
    ];
}

function prayer_setting_defaults(): array
{
    return [
        'prayer_api_province' => '',
        'prayer_api_city' => '',
        'prayer_offset_subuh' => '0',
        'prayer_offset_dzuhur' => '0',
        'prayer_offset_ashar' => '0',
        'prayer_offset_maghrib' => '0',
        'prayer_offset_isya' => '0',
    ];
}

function homepage_ticker_defaults(): array
{
    return [
        'Sebaik-baik kalian adalah yang mempelajari Al-Qur\'an dan mengajarkannya.',
        'Selamat datang di template website masjid yang siap disesuaikan dengan kebutuhan jamaah Anda.',
        'Gunakan panel admin untuk memperbarui kajian, artikel, video, infaq, dan pengumuman masjid.',
    ];
}

function configured_site_name(): string
{
    $defaults = general_setting_defaults();
    $siteName = trim((string) (configuration_get('site_name', $defaults['site_name']) ?? $defaults['site_name']));

    return $siteName !== '' ? $siteName : $defaults['site_name'];
}

function configured_site_tagline(): string
{
    $defaults = general_setting_defaults();

    return trim((string) (configuration_get('site_tagline', $defaults['site_tagline']) ?? $defaults['site_tagline']));
}

function configuration_load_all(): array
{
    if (isset($GLOBALS['__configuration_cache']) && is_array($GLOBALS['__configuration_cache'])) {
        return $GLOBALS['__configuration_cache'];
    }

    try {
        $rows = db()->query('SELECT setting_key, setting_value FROM site_settings')->fetchAll();
        $settings = [];

        foreach ($rows as $row) {
            $settings[(string) $row['setting_key']] = $row['setting_value'];
        }
    } catch (Throwable) {
        $settings = [];
    }

    $GLOBALS['__configuration_cache'] = $settings;

    return $settings;
}

function configuration_get(string $key, ?string $default = null): ?string
{
    $settings = configuration_load_all();

    return array_key_exists($key, $settings) ? (string) $settings[$key] : $default;
}

function configuration_set_many(array $values): void
{
    if ($values === []) {
        return;
    }

    $statement = db()->prepare(
        'INSERT INTO site_settings (setting_key, setting_value)
         VALUES (:setting_key, :setting_value)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP'
    );

    foreach ($values as $key => $value) {
        $statement->execute([
            'setting_key' => (string) $key,
            'setting_value' => $value !== null ? (string) $value : null,
        ]);
    }

    $settings = configuration_load_all();
    foreach ($values as $key => $value) {
        $settings[(string) $key] = $value !== null ? (string) $value : null;
    }
    $GLOBALS['__configuration_cache'] = $settings;
}

function configuration_set(string $key, ?string $value): void
{
    configuration_set_many([$key => $value]);
}

function configuration_lines(string $key, array $default = []): array
{
    $value = trim((string) configuration_get($key, ''));

    if ($value === '') {
        return $default;
    }

    $lines = preg_split('/\R+/', $value) ?: [];

    return array_values(array_filter(array_map(static fn (string $line): string => trim($line), $lines), static fn (string $line): bool => $line !== ''));
}

function fetch_master_categories(string $group): array
{
    $defaults = master_category_defaults();
    $fallback = $defaults[$group] ?? [];

    try {
        $statement = db()->prepare(
            'SELECT name
             FROM master_categories
             WHERE group_key = :group_key AND is_active = 1
             ORDER BY sort_order ASC, name ASC'
        );
        $statement->execute(['group_key' => $group]);
        $rows = $statement->fetchAll();

        if ($rows === []) {
            return $fallback;
        }

        return array_values(array_unique(array_map(static fn (array $row): string => (string) $row['name'], $rows)));
    } catch (Throwable) {
        return $fallback;
    }
}
