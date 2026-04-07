-- Jalankan script ini pada database aktif yang sudah Anda pilih.

DELETE FROM study_schedules
WHERE title = 'Fiqh Muamalah Kontemporer'
  AND session_date = '2026-03-28';

DELETE FROM articles
WHERE slug = 'merenungkan-kedamaian-dalam-setiap-sujud';

DELETE FROM videos
WHERE youtube_url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

DELETE FROM infaq_campaigns
WHERE title = 'Renovasi Serambi Utara';

DELETE FROM reports
WHERE slug = 'laporan-kegiatan-masjid-ausath-maret-2026';
