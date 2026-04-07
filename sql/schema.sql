-- Jalankan script ini pada database yang sudah Anda buat dan pilih terlebih dahulu.
-- Contoh:
-- CREATE DATABASE nama_database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE nama_database;

CREATE TABLE IF NOT EXISTS admin_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'admin',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS login_attempts (
    attempt_key VARCHAR(80) PRIMARY KEY,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_attempt_at DATETIME NOT NULL,
    blocked_until DATETIME NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Buat akun admin pertama secara manual dengan password yang kuat.
-- Contoh:
-- INSERT INTO admin_users (full_name, email, password_hash, role, is_active)
-- VALUES (
--     'Administrator',
--     'admin@domainanda.com',
--     '$2y$10$ganti_dengan_hasil_password_hash_php',
--     'super_admin',
--     1
-- );

CREATE TABLE IF NOT EXISTS study_schedules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(190) NOT NULL,
    speaker VARCHAR(120) NOT NULL,
    category VARCHAR(80) NULL,
    session_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NULL,
    live_url VARCHAR(255) NULL,
    location VARCHAR(150) NOT NULL,
    summary TEXT NULL,
    status ENUM('draft', 'scheduled', 'completed', 'archived') NOT NULL DEFAULT 'scheduled',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

ALTER TABLE study_schedules
    ADD COLUMN IF NOT EXISTS live_url VARCHAR(255) NULL AFTER end_time;

CREATE TABLE IF NOT EXISTS articles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL UNIQUE,
    author VARCHAR(120) NOT NULL,
    category VARCHAR(80) NULL,
    excerpt TEXT NULL,
    body LONGTEXT NOT NULL,
    featured_image VARCHAR(255) NULL,
    featured_image_title VARCHAR(190) NULL,
    featured_image_alt VARCHAR(255) NULL,
    published_at DATETIME NULL,
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS videos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(190) NOT NULL,
    speaker VARCHAR(120) NOT NULL,
    category VARCHAR(80) NULL,
    youtube_url VARCHAR(255) NOT NULL,
    video_date DATE NULL,
    duration_minutes INT UNSIGNED NULL,
    summary TEXT NULL,
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

ALTER TABLE videos
    ADD COLUMN IF NOT EXISTS category VARCHAR(80) NULL AFTER speaker;

ALTER TABLE articles
    ADD COLUMN IF NOT EXISTS category VARCHAR(80) NULL AFTER author;

ALTER TABLE articles
    ADD COLUMN IF NOT EXISTS featured_image_title VARCHAR(190) NULL AFTER featured_image;

ALTER TABLE articles
    ADD COLUMN IF NOT EXISTS featured_image_alt VARCHAR(255) NULL AFTER featured_image_title;

CREATE TABLE IF NOT EXISTS infaq_campaigns (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(190) NOT NULL,
    description TEXT NULL,
    completion_mode ENUM('date', 'amount') NOT NULL DEFAULT 'date',
    target_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    collected_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    start_date DATE NULL,
    end_date DATE NULL,
    status ENUM('draft', 'active', 'completed', 'archived') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

ALTER TABLE infaq_campaigns
    ADD COLUMN IF NOT EXISTS completion_mode ENUM('date', 'amount') NOT NULL DEFAULT 'date' AFTER description;

CREATE TABLE IF NOT EXISTS site_settings (
    setting_key VARCHAR(120) PRIMARY KEY,
    setting_value LONGTEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS master_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_key VARCHAR(50) NOT NULL,
    name VARCHAR(120) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_group_name (group_key, name)
);

CREATE TABLE IF NOT EXISTS reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL UNIQUE,
    author VARCHAR(120) NOT NULL,
    category VARCHAR(80) NULL,
    period_label VARCHAR(120) NULL,
    excerpt TEXT NULL,
    body LONGTEXT NOT NULL,
    featured_image VARCHAR(255) NULL,
    attachment_url VARCHAR(255) NULL,
    gallery_urls LONGTEXT NULL,
    published_at DATETIME NULL,
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

ALTER TABLE reports
    ADD COLUMN IF NOT EXISTS gallery_urls LONGTEXT NULL AFTER attachment_url;

INSERT INTO master_categories (group_key, name, sort_order, is_active)
VALUES
    ('study_schedule', 'Aqidah', 1, 1),
    ('study_schedule', 'Akhlak', 2, 1),
    ('study_schedule', 'Fiqh', 3, 1),
    ('study_schedule', 'Hadits', 4, 1),
    ('study_schedule', 'Keluarga', 5, 1),
    ('study_schedule', 'Muamalah', 6, 1),
    ('study_schedule', 'Ramadhan', 7, 1),
    ('study_schedule', 'Sejarah', 8, 1),
    ('study_schedule', 'Tafsir', 9, 1),
    ('video', 'Aqidah', 1, 1),
    ('video', 'Akhlak', 2, 1),
    ('video', 'Fiqh', 3, 1),
    ('video', 'Keluarga', 4, 1),
    ('video', 'Kajian Umum', 5, 1),
    ('video', 'Muamalah', 6, 1),
    ('video', 'Ramadhan Special', 7, 1),
    ('video', 'Sejarah', 8, 1),
    ('video', 'Tafsir', 9, 1),
    ('article', 'Akhlak', 1, 1),
    ('article', 'Ibadah', 2, 1),
    ('article', 'Keluarga', 3, 1),
    ('article', 'Muamalah', 4, 1),
    ('article', 'Renungan', 5, 1),
    ('article', 'Sejarah', 6, 1),
    ('article', 'Spiritualitas', 7, 1),
    ('article', 'Tafsir', 8, 1),
    ('report', 'Laporan Kegiatan', 1, 1),
    ('report', 'Keuangan Bulanan', 2, 1),
    ('report', 'Program Sosial', 3, 1),
    ('report', 'Dokumen Publik', 4, 1)
ON DUPLICATE KEY UPDATE
    sort_order = VALUES(sort_order),
    is_active = VALUES(is_active);

INSERT INTO site_settings (setting_key, setting_value)
VALUES
    ('site_name', 'Website Masjid'),
    ('site_tagline', 'Pusat Ibadah, Dakwah, dan Pelayanan Umat'),
    ('site_address', 'Kota / Kabupaten Anda'),
    ('google_analytics_code', ''),
    ('google_maps_url', ''),
    ('google_maps_view', 'satellite'),
    ('whatsapp_channel_url', ''),
    ('meta_description', ''),
    ('meta_keywords', ''),
    ('og_type', 'website'),
    ('og_title', ''),
    ('og_description', ''),
    ('og_image', ''),
    ('twitter_card', 'summary_large_image'),
    ('twitter_title', ''),
    ('twitter_description', ''),
    ('twitter_image', ''),
    ('favicon_url', ''),
    ('prayer_api_province', ''),
    ('prayer_api_city', ''),
    ('prayer_offset_subuh', '0'),
    ('prayer_offset_dzuhur', '0'),
    ('prayer_offset_ashar', '0'),
    ('prayer_offset_maghrib', '0'),
    ('prayer_offset_isya', '0'),
    ('homepage_ticker_text', 'Sebaik-baik kalian adalah yang mempelajari Al-Qur''an dan mengajarkannya.\nSelamat datang di template website masjid yang siap disesuaikan dengan kebutuhan jamaah Anda.\nGunakan panel admin untuk memperbarui kajian, artikel, video, infaq, dan pengumuman masjid.')
ON DUPLICATE KEY UPDATE
    setting_value = VALUES(setting_value),
    updated_at = CURRENT_TIMESTAMP;
