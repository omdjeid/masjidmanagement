<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/site.php';

const DEFAULT_MAP_ZOOM = 17;

function location_map_link(string $url, string $address): string
{
    if (trim($url) !== '') {
        return $url;
    }

    if (trim($address) !== '') {
        return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($address);
    }

    return '';
}

function location_map_type(string $viewMode): string
{
    return $viewMode === 'roadmap' ? 'm' : 'k';
}

function location_embed_from_google_url(string $url, string $viewMode): string
{
    if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+),(\d+(?:\.\d+)?)z/i', $url, $matches) === 1) {
        $latitude = $matches[1];
        $longitude = $matches[2];
        $zoom = (int) round((float) $matches[3]);

        return 'https://www.google.com/maps?q='
            . rawurlencode($latitude . ',' . $longitude)
            . '&z=' . max(12, $zoom)
            . '&t=' . location_map_type($viewMode)
            . '&output=embed';
    }

    if (preg_match('/[?&]q=([^&]+)/i', $url, $matches) === 1) {
        $query = trim(urldecode($matches[1]));
        if ($query !== '') {
            return 'https://www.google.com/maps?q='
                . rawurlencode($query)
                . '&z=' . DEFAULT_MAP_ZOOM
                . '&t=' . location_map_type($viewMode)
                . '&output=embed';
        }
    }

    if (preg_match('/\/place\/([^\/]+)/i', $url, $matches) === 1) {
        $query = trim(str_replace('+', ' ', urldecode($matches[1])));
        if ($query !== '') {
            return 'https://www.google.com/maps?q='
                . rawurlencode($query)
                . '&z=' . DEFAULT_MAP_ZOOM
                . '&t=' . location_map_type($viewMode)
                . '&output=embed';
        }
    }

    return '';
}

function location_embed_url(string $url, string $address, string $viewMode): string
{
    $url = trim($url);
    $address = trim($address);

    if ($url !== '' && (str_contains($url, '/maps/embed') || str_contains($url, 'output=embed'))) {
        $separator = str_contains($url, '?') ? '&' : '?';
        return $url . $separator . 't=' . location_map_type($viewMode);
    }

    if ($url !== '') {
        $embedFromUrl = location_embed_from_google_url($url, $viewMode);
        if ($embedFromUrl !== '') {
            return $embedFromUrl;
        }
    }

    if ($address !== '') {
        return 'https://www.google.com/maps?q='
            . rawurlencode($address)
            . '&z=' . DEFAULT_MAP_ZOOM
            . '&t=' . location_map_type($viewMode)
            . '&output=embed';
    }

    return '';
}

$siteName = configured_site_name();
$siteAddress = configuration_get('site_address', general_setting_defaults()['site_address']) ?? '';
$googleMapsUrl = configuration_get('google_maps_url', general_setting_defaults()['google_maps_url']) ?? '';
$googleMapsView = configuration_get('google_maps_view', general_setting_defaults()['google_maps_view']) ?? 'satellite';
$mapLink = location_map_link($googleMapsUrl, $siteAddress);
$embedUrl = location_embed_url($googleMapsUrl, $siteAddress, $googleMapsView);

public_page_start('Peta Lokasi', 'lokasi');
?>
        <section class="page-intro">
            <div>
                <p class="eyebrow">Peta Lokasi</p>
                <h1>Lokasi<br><?= h($siteName); ?></h1>
                <p class="content-copy">Temukan lokasi masjid dan buka navigasi langsung ke Google Maps untuk memudahkan perjalanan Anda.</p>
            </div>
            <?php if ($mapLink !== ''): ?>
                <a class="button-secondary" href="<?= h($mapLink); ?>" target="_blank" rel="noopener noreferrer">Buka di Google Maps</a>
            <?php endif; ?>
        </section>

        <section class="location-layout">
            <article class="feature-card">
                <p class="eyebrow">Alamat</p>
                <h2><?= h($siteName); ?></h2>
                <p class="content-copy"><?= h($siteAddress !== '' ? $siteAddress : 'Alamat belum diatur dari panel setting.'); ?></p>
                <?php if ($mapLink !== ''): ?>
                    <div class="feature-meta">
                        <a class="button-primary" href="<?= h($mapLink); ?>" target="_blank" rel="noopener noreferrer">Arahkan ke Lokasi</a>
                    </div>
                <?php endif; ?>
            </article>

            <article class="location-map-card">
                <?php if ($embedUrl !== ''): ?>
                    <iframe
                        src="<?= h($embedUrl); ?>"
                        title="Peta lokasi <?= h($siteName); ?>"
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        allowfullscreen
                    ></iframe>
                <?php else: ?>
                    <div class="status-notice">
                        <p class="content-copy">URL Google Maps belum diatur. Silakan lengkapi dari tab General di halaman setting admin.</p>
                    </div>
                <?php endif; ?>
            </article>
        </section>
<?php
public_page_end();
