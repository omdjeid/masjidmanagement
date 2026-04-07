<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/configuration.php';

if (is_logged_in()) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$errors = [];
$email = '';
$remember = false;
$adminCount = admin_user_count();
$generalDefaults = general_setting_defaults();
$siteName = (string) (configuration_get('site_name', $generalDefaults['site_name']) ?? $generalDefaults['site_name']);
$siteTagline = trim((string) (configuration_get('site_tagline', $generalDefaults['site_tagline']) ?? $generalDefaults['site_tagline']));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token('login.php');
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $remember = isset($_POST['remember']);

    if ($email === '') {
        $errors['email'] = 'Email wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Gunakan format email yang valid.';
    }

    if ($password === '') {
        $errors['password'] = 'Password wajib diisi.';
    }

    if ($errors === []) {
        $blockSeconds = auth_login_block_seconds($email);

        if ($blockSeconds > 0) {
            $minutes = (int) ceil($blockSeconds / 60);
            $errors['login'] = 'Terlalu banyak percobaan login. Coba lagi dalam ' . $minutes . ' menit.';
        }
    }

    if ($errors === []) {
        try {
            if (attempt_login($email, $password, $remember)) {
                clear_failed_login_attempts($email);
                header('Location: ' . app_url('dashboard.php'));
                exit;
            }

            register_failed_login_attempt($email);
            $errors['login'] = 'Email atau password tidak cocok.';
        } catch (Throwable $exception) {
            $errors['login'] = 'Login belum dapat diproses. Silakan coba beberapa saat lagi.';
        }
    }
}

function old(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Admin | <?= old($siteName); ?></title>
    <meta name="description" content="Halaman login admin <?= old($siteName); ?> dengan tema editorial Islami yang lembut.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= old(asset_url('css/login.css')); ?>">
</head>
<body>
    <main class="page-shell">
        <section class="auth-layout">
            <aside class="brand-panel">
                <div class="brand-panel__inner">
                    <div class="brand-block">
                        <?php if ($siteTagline !== ''): ?>
                            <p class="eyebrow"><?= old($siteTagline); ?></p>
                        <?php endif; ?>
                        <h1><?= old($siteName); ?></h1>
                        <p class="brand-copy">
                            Portal admin yang tenang, rapi, dan selaras dengan nuansa editorial Islami dari konsep utama website.
                        </p>
                    </div>

                    <div class="prayer-card">
                        <p class="prayer-card__label">Next Prayer In 15 Mins</p>
                        <div class="prayer-card__headline">Ashar</div>
                        <div class="prayer-card__time">15:30 WIB</div>
                        <div class="prayer-grid">
                            <div>
                                <span>Subuh</span>
                                <strong>04:32</strong>
                            </div>
                            <div>
                                <span>Dzuhur</span>
                                <strong>11:45</strong>
                            </div>
                            <div class="is-active">
                                <span>Ashar</span>
                                <strong>15:30</strong>
                            </div>
                            <div>
                                <span>Maghrib</span>
                                <strong>17:58</strong>
                            </div>
                            <div>
                                <span>Isya</span>
                                <strong>19:10</strong>
                            </div>
                        </div>
                    </div>

                    <div class="quote-card">
                        <p class="quote-card__label">Pengingat</p>
                        <p class="quote-card__text">
                            Sebaik-baik kalian adalah yang mempelajari Al-Qur'an dan mengajarkannya.
                        </p>
                    </div>
                </div>
            </aside>

            <section class="form-panel">
                <div class="form-panel__inner">
                    <div class="form-header">
                        <a class="brand-mini" href="<?= old(app_url('login.php')); ?>"><?= old($siteName); ?></a>
                    </div>

                    <div class="form-copy">
                        <p class="eyebrow">Admin Access</p>
                        <h2>Masuk ke ruang kendali</h2>
                    </div>

                    <?php if ($adminCount === 0): ?>
                        <div class="alert alert--error">
                            Belum ada akun admin. Buat akun pertama melalui
                            <a class="text-link" href="<?= old(app_url('setup-admin.php')); ?>">halaman setup admin</a>.
                        </div>
                    <?php endif; ?>

                    <?php if ($errors !== []): ?>
                        <div class="alert alert--error">
                            <?= old($errors['login'] ?? 'Mohon periksa kembali data login Anda.'); ?>
                        </div>
                    <?php endif; ?>

                    <form class="login-form" method="post" action="<?= old(app_url('login.php')); ?>" novalidate>
                        <?= csrf_input(); ?>
                        <div class="field">
                            <label for="email">Email Admin</label>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                inputmode="email"
                                autocomplete="email"
                                value="<?= old($email); ?>"
                                aria-describedby="<?= isset($errors['email']) ? 'email-error' : ''; ?>"
                            >
                            <?php if (isset($errors['email'])): ?>
                                <p class="field-error" id="email-error"><?= old($errors['email']); ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="field">
                            <div class="field-row">
                                <label for="password">Password</label>
                                <a href="#" class="text-link">Lupa password?</a>
                            </div>
                            <input
                                id="password"
                                name="password"
                                type="password"
                                autocomplete="current-password"
                                placeholder="Masukkan password"
                                aria-describedby="<?= isset($errors['password']) ? 'password-error' : ''; ?>"
                            >
                            <?php if (isset($errors['password'])): ?>
                                <p class="field-error" id="password-error"><?= old($errors['password']); ?></p>
                            <?php endif; ?>
                        </div>

                        <label class="checkbox-row" for="remember">
                            <input
                                id="remember"
                                name="remember"
                                type="checkbox"
                                <?= $remember ? 'checked' : ''; ?>
                            >
                            <span>Ingat saya di perangkat ini</span>
                        </label>

                        <button class="submit-button" type="submit">Masuk ke Dashboard</button>
                    </form>

                </div>
            </section>
        </section>
    </main>
</body>
</html>
