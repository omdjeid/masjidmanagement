<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/site.php';

function article_reading_minutes(string $text): int
{
    $plainText = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');

    if ($plainText === '') {
        return 1;
    }

    preg_match_all('/[\p{L}\p{N}]+(?:[\'â€™-][\p{L}\p{N}]+)*/u', $plainText, $matches);
    $wordCount = count($matches[0]);

    return max(1, (int) ceil($wordCount / 180));
}

function infer_article_topic(array $article): string
{
    $storedCategory = trim((string) ($article['category'] ?? ''));
    if ($storedCategory !== '') {
        return $storedCategory;
    }

    $haystack = strtolower(trim((string) (($article['title'] ?? '') . ' ' . ($article['excerpt'] ?? '') . ' ' . ($article['body'] ?? ''))));

    return match (true) {
        str_contains($haystack, 'qur') || str_contains($haystack, 'tafsir') => "Al-Qur'an",
        str_contains($haystack, 'shalat') || str_contains($haystack, 'sujud') || str_contains($haystack, 'ibadah') => 'Ibadah',
        str_contains($haystack, 'renung') || str_contains($haystack, 'ciptaan') || str_contains($haystack, 'langit') => 'Renungan',
        str_contains($haystack, 'adab') || str_contains($haystack, 'akhlak') => 'Akhlak',
        default => 'Spiritualitas',
    };
}

function current_page_url(): string
{
    return absolute_request_url('artikel-detail.php');
}

function article_body_html(string $body): string
{
    $body = trim($body);

    if ($body === '') {
        return '';
    }

    if (preg_match('/<\s*(p|h2|h3|ul|ol|li|blockquote|strong|em|a|br|img)\b/i', $body) === 1) {
        return sanitize_rich_text_html($body);
    }

    $paragraphs = preg_split("/\R{2,}/", $body) ?: [];
    $html = '';

    foreach ($paragraphs as $paragraph) {
        $text = trim($paragraph);
        if ($text === '') {
            continue;
        }

        $html .= '<p>' . nl2br(h($text)) . '</p>';
    }

    return $html;
}

$slug = trim((string) ($_GET['slug'] ?? ''));
$notice = null;
$article = null;
$relatedArticles = [];

try {
    if ($slug !== '') {
        $statement = db()->prepare("SELECT * FROM articles WHERE slug = :slug AND status = 'published' LIMIT 1");
        $statement->execute(['slug' => $slug]);
        $result = $statement->fetch();
        if ($result !== false) {
            $article = $result;
        }
    } else {
        $result = db()->query("SELECT * FROM articles WHERE status = 'published' ORDER BY COALESCE(published_at, created_at) DESC LIMIT 1")->fetch();
        if ($result !== false) {
            $article = $result;
        }
    }

    if ($article !== null) {
        $related = db()->prepare(
            "SELECT * FROM articles
             WHERE status = 'published' AND slug <> :slug
             ORDER BY COALESCE(published_at, created_at) DESC
             LIMIT 3"
        );
        $related->execute(['slug' => (string) $article['slug']]);
        $relatedArticles = $related->fetchAll();
    }
} catch (Throwable) {
    $notice = 'Data artikel belum dapat dimuat saat ini.';
}

$readingMinutes = article_reading_minutes((string) ($article['body'] ?? ''));
$articleHeroImage = media_url((string) ($article['featured_image'] ?? ''));
$articleTopic = $article !== null ? infer_article_topic($article) : 'Artikel';
$siteName = configured_site_name();
$whatsAppShareUrl = 'https://wa.me/?text=' . rawurlencode((string) ($article['title'] ?? ('Artikel ' . $siteName)) . ' - ' . current_page_url());
$articleBodyHtml = article_body_html((string) ($article['body'] ?? ''));
$articleMetaDescription = $article !== null
    ? meta_plain_text((string) (($article['excerpt'] ?? '') !== '' ? $article['excerpt'] : ($article['body'] ?? '')))
    : '';

public_page_start(
    (string) ($article['title'] ?? 'Artikel'),
    'artikel',
    [
        'title' => (string) ($article['title'] ?? 'Artikel'),
        'description' => $articleMetaDescription,
        'image' => (string) ($article['featured_image'] ?? ''),
        'image_alt' => $article !== null ? featured_image_alt_text($article, 'Featured image artikel') : '',
        'type' => 'article',
        'twitter_card' => $articleHeroImage !== '' ? 'summary_large_image' : 'summary',
    ]
);
?>
        <?php if ($notice !== null): ?>
            <div class="status-notice"><p class="content-copy"><?= h($notice); ?></p></div>
        <?php endif; ?>

        <?php if ($article !== null): ?>
            <section class="article-hero<?= $articleHeroImage !== '' ? ' article-hero--has-image' : ''; ?>"<?= $articleHeroImage !== '' ? ' style="background-image: linear-gradient(0deg, rgba(0, 53, 39, 0.78), rgba(0, 53, 39, 0.2)), url(\'' . h($articleHeroImage) . '\');"' : ''; ?>>
                <div>
                    <p class="eyebrow"><?= h($articleTopic); ?></p>
                    <h1><?= h((string) $article['title']); ?></h1>
                    <div class="article-meta">
                        <span>Oleh <?= h((string) $article['author']); ?></span>
                        <span><?= h(format_human_date($article['published_at'] !== null ? (string) $article['published_at'] : null, true)); ?></span>
                        <span><?= h((string) $readingMinutes); ?> Menit Membaca</span>
                    </div>
                </div>
            </section>

            <section class="article-layout">
                <aside class="article-side">
                    <article class="meta-card">
                        <p class="eyebrow">Topik Terkait</p>
                        <ul class="topic-list">
                            <?php foreach (array_slice($relatedArticles, 0, 3) as $item): ?>
                                <li>
                                    <a href="<?= h(app_url('artikel-detail.php?slug=' . urlencode((string) $item['slug']))); ?>">
                                        <span class="topic-list__arrow">&rarr;</span>
                                        <span><?= h((string) $item['title']); ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </article>
                </aside>

                <article class="article-body">
                    <?= $articleBodyHtml; ?>

                    <div class="share-section">
                        <p class="eyebrow">Bagikan Kebaikan Ini</p>
                        <a class="whatsapp-share" href="<?= h($whatsAppShareUrl); ?>" target="_blank" rel="noopener noreferrer">
                            <span>Bagikan ke WhatsApp</span>
                        </a>
                    </div>
                </article>

                <div class="article-spacer" aria-hidden="true"></div>
            </section>

            <?php if ($relatedArticles !== []): ?>
                <section class="list-card">
                    <div class="section-head">
                        <div>
                            <p class="eyebrow">Editorial Lanjutan</p>
                            <h2 class="section-title">Artikel Terkait Lainnya</h2>
                        </div>
                        <a class="section-link" href="<?= h(app_url('artikel-list.php')); ?>">Lihat Semua</a>
                    </div>
                    <div class="related-grid">
                        <?php foreach ($relatedArticles as $item): ?>
                            <a class="related-card" href="<?= h(app_url('artikel-detail.php?slug=' . urlencode((string) $item['slug']))); ?>">
                                <div class="related-thumb">
                                    <?php $relatedImage = media_url((string) ($item['featured_image'] ?? '')); ?>
                                    <?php if ($relatedImage !== ''): ?>
                                        <img src="<?= h($relatedImage); ?>" alt="<?= h(featured_image_alt_text($item, (string) $item['title'])); ?>" loading="lazy">
                                    <?php endif; ?>
                                    <span class="related-thumb__badge"><?= h(infer_article_topic($item)); ?></span>
                                </div>
                                <div class="related-card__body">
                                    <h3><?= h((string) $item['title']); ?></h3>
                                    <p><?= h((string) $item['author']); ?> - <?= h(format_human_date((string) $item['published_at'])); ?> - <?= h((string) article_reading_minutes((string) ($item['body'] ?? ($item['excerpt'] ?? $item['title'])))); ?> Menit</p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        <?php else: ?>
            <div class="status-notice">
                <p class="content-copy">Artikel yang Anda cari belum tersedia.</p>
            </div>
        <?php endif; ?>
<?php
public_page_end();
