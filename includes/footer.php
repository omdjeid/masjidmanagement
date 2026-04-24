<?php
declare(strict_types=1);

$layout = $GLOBALS['_public_layout_data'] ?? [];
$siteName = (string) ($layout['site_name'] ?? configured_site_name());
$siteTagline = trim((string) ($layout['site_tagline'] ?? ''));
$activeNav = (string) ($layout['active_nav'] ?? '');
$mobileItems = is_array($layout['mobile_items'] ?? null) ? $layout['mobile_items'] : [];
?>
    </main>
    <button class="public-mobile-overlay" type="button" aria-hidden="true" tabindex="-1"></button>
    <aside class="public-mobile-drawer" id="publicMobileDrawer" aria-label="Navigasi publik mobile">
        <div class="public-mobile-drawer__brand">
            <a class="public-brand" href="<?= h(app_url()); ?>"><?= h($siteName); ?></a>
            <?php if ($siteTagline !== ''): ?>
                <p><?= h($siteTagline); ?></p>
            <?php endif; ?>
        </div>
        <nav class="public-mobile-drawer__nav" aria-label="Navigasi publik mobile">
            <?php foreach ($mobileItems as $key => $item): ?>
                <a class="<?= $activeNav === $key ? 'is-active' : ''; ?>" href="<?= h((string) ($item['href'] ?? app_url())); ?>"><?= h((string) ($item['label'] ?? '')); ?></a>
            <?php endforeach; ?>
        </nav>
    </aside>
    <footer class="public-footer">
        <div class="public-footer__inner">
            <div>
                <div class="public-brand"><?= h($siteName); ?></div>
                <?php if ($siteTagline !== ''): ?>
                    <p><?= h($siteTagline); ?></p>
                <?php endif; ?>
            </div>
            <p class="public-footer__copy">&copy; 2026 <?= h($siteName); ?><?= $siteTagline !== '' ? '. ' . h($siteTagline) : ''; ?></p>
        </div>
    </footer>
    <script>
        (function () {
            var body = document.body;
            var drawer = document.getElementById('publicMobileDrawer');
            var toggle = document.querySelector('.public-mobile-toggle');
            var overlay = document.querySelector('.public-mobile-overlay');

            if (!body || !drawer || !toggle || !overlay) {
                return;
            }

            function setMenuState(isOpen) {
                body.classList.toggle('public-menu-open', isOpen);
                toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            }

            toggle.addEventListener('click', function () {
                setMenuState(!body.classList.contains('public-menu-open'));
            });

            overlay.addEventListener('click', function () {
                setMenuState(false);
            });

            drawer.querySelectorAll('a').forEach(function (link) {
                link.addEventListener('click', function () {
                    setMenuState(false);
                });
            });

            window.addEventListener('resize', function () {
                if (window.innerWidth > 760) {
                    setMenuState(false);
                }
            });
        }());
    </script>
</body>
</html>
