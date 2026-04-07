<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/configuration.php';

if (is_logged_in()) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$adminCount = admin_user_count();
if ($adminCount !== null && $adminCount > 0) {
    header('Location: ' . app_url('login.php'));
    exit;
}

$generalDefaults = general_setting_defaults();
$siteName = (string) (configuration_get('site_name', $generalDefaults['site_name']) ?? $generalDefaults['site_name']);
$siteTagline = trim((string) (configuration_get('site_tagline', $generalDefaults['site_tagline']) ?? $generalDefaults['site_tagline']));
$errors = [];
$form = [
    'full_name' => '',
    'email' => '',
];
$isDatabaseReady = $adminCount !== null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token('setup-admin.php');

    $form['full_name'] = trim((string) ($_POST['full_name'] ?? ''));
    $form['email'] = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

    if (!$isDatabaseReady) {
        $errors['setup'] = 'Database belum siap. Import `sql/schema.sql` terlebih dahulu lalu muat ulang halaman ini.';
    }

    if ($form['full_name'] === '') {
        $errors['full_name'] = 'Nama lengkap wajib diisi.';
    }

    if ($form['email'] === '') {
        $errors['email'] = 'Email admin wajib diisi.';
    } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Gunakan format email yang valid.';
    }

    if ($password === '') {
        $errors['password'] = 'Password wajib diisi.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password minimal 8 karakter.';
    }

    if ($passwordConfirmation === '') {
        $errors['password_confirmation'] = 'Konfirmasi password wajib diisi.';
    } elseif (!hash_equals($password, $passwordConfirmation)) {
        $errors['password_confirmation'] = 'Konfirmasi password belum sama.';
    }

    if ($errors === []) {
        try {
            if (!can_bootstrap_admin_user()) {
                header('Location: ' . app_url('login.php'));
                exit;
            }

            $statement = db()->prepare(
                'INSERT INTO admin_users (full_name, email, password_hash, role, is_active)
                 VALUES (:full_name, :email, :password_hash, :role, :is_active)'
            );
            $statement->execute([
                'full_name' => $form['full_name'],
                'email' => strtolower($form['email']),
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'super_admin',
                'is_active' => 1,
            ]);

            attempt_login($form['email'], $password);
            header('Location: ' . app_url('dashboard.php'));
            exit;
        } catch (Throwable) {
            $errors['setup'] = 'Akun admin pertama belum berhasil dibuat. Pastikan database aktif dan belum memiliki user admin.';
        }
    }
}

function setup_old(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setup Admin | <?= setup_old($siteName); ?></title>
    <meta name="description" content="Buat akun super admin pertama untuk template website masjid ini.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= setup_old(asset_url('css/login.css')); ?>">
</head>
<body>
    <main class="page-shell">
        <section class="auth-layout">
            <aside class="brand-panel">
                <div class="brand-panel__inner">
                    <div class="brand-block">
                        <?php if ($siteTagline !== ''): ?>
                            <p class="eyebrow"><?= setup_old($siteTagline); ?></p>
                        <?php endif; ?>
                        <h1><?= setup_old($siteName); ?></h1>
                        <p class="brand-copy">
                            Halaman ini hanya muncul ketika belum ada akun admin. Setelah akun pertama dibuat, akses akan diarahkan ke login biasa.
                        </p>
                    </div>

                    <div class="quote-card">
                        <p class="quote-card__label">Langkah Awal</p>
                        <p class="quote-card__text">
                            Import struktur database template, lalu buat satu akun super admin untuk mulai mengisi konten.
                        </p>
                    </div>
                </div>
            </aside>

            <section class="form-panel">
                <div class="form-panel__inner">
                    <div class="form-header">
                        <a class="brand-mini" href="<?= setup_old(app_url('setup-admin.php')); ?>"><?= setup_old($siteName); ?></a>
                    </div>

                    <div class="form-copy">
                        <p class="eyebrow">Initial Setup</p>
                        <h2>Buat super admin pertama</h2>
                    </div>

                    <?php if (!$isDatabaseReady): ?>
                        <div class="alert alert--error">
                            Database belum siap. Import `sql/schema.sql` terlebih dahulu lalu buka halaman ini lagi.
                        </div>
                    <?php elseif (isset($errors['setup'])): ?>
                        <div class="alert alert--error">
                            <?= setup_old($errors['setup']); ?>
                        </div>
                    <?php endif; ?>

                    <form class="login-form" method="post" action="<?= setup_old(app_url('setup-admin.php')); ?>" novalidate>
                        <?= csrf_input(); ?>

                        <div class="field">
                            <label for="full_name">Nama Lengkap</label>
                            <input
                                id="full_name"
                                name="full_name"
                                type="text"
                                autocomplete="name"
                                value="<?= setup_old($form['full_name']); ?>"
                                aria-describedby="<?= isset($errors['full_name']) ? 'full-name-error' : ''; ?>"
                            >
                            <?php if (isset($errors['full_name'])): ?>
                                <p class="field-error" id="full-name-error"><?= setup_old($errors['full_name']); ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="field">
                            <label for="email">Email Admin</label>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                inputmode="email"
                                autocomplete="email"
                                value="<?= setup_old($form['email']); ?>"
                                aria-describedby="<?= isset($errors['email']) ? 'email-error' : ''; ?>"
                            >
                            <?php if (isset($errors['email'])): ?>
                                <p class="field-error" id="email-error"><?= setup_old($errors['email']); ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="field">
                            <label for="password">Password</label>
                            <input
                                id="password"
                                name="password"
                                type="password"
                                autocomplete="new-password"
                                aria-describedby="<?= isset($errors['password']) ? 'password-error' : ''; ?>"
                            >
                            <?php if (isset($errors['password'])): ?>
                                <p class="field-error" id="password-error"><?= setup_old($errors['password']); ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="field">
                            <label for="password_confirmation">Konfirmasi Password</label>
                            <input
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                aria-describedby="<?= isset($errors['password_confirmation']) ? 'password-confirmation-error' : ''; ?>"
                            >
                            <?php if (isset($errors['password_confirmation'])): ?>
                                <p class="field-error" id="password-confirmation-error"><?= setup_old($errors['password_confirmation']); ?></p>
                            <?php endif; ?>
                        </div>

                        <button class="submit-button" type="submit">Simpan dan Masuk</button>
                    </form>

                    <p class="field-help" style="margin-top: 18px;">
                        Setelah akun pertama dibuat, halaman ini otomatis nonaktif dan pengguna akan diarahkan ke halaman login.
                    </p>
                </div>
            </section>
        </section>
    </main>
</body>
</html>
