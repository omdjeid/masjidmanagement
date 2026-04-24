<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

ensure_session_started();
send_security_headers();

function base_path(): string
{
    static $basePath = null;

    if ($basePath !== null) {
        return $basePath;
    }

    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $directory = str_replace('\\', '/', dirname($scriptName));
    $directory = rtrim($directory, '/.');

    $basePath = ($directory === '' || $directory === '\\') ? '' : $directory;

    return $basePath;
}

function app_route_aliases(): array
{
    return [
        'index.php' => '',
        'jadwal.php' => 'jadwal',
        'artikel-list.php' => 'artikel',
        'artikel-detail.php' => 'artikel',
        'kajian-video.php' => 'video',
        'video-detail.php' => 'video',
        'laporan.php' => 'laporan',
        'laporan-detail.php' => 'laporan',
        'lokasi.php' => 'lokasi',
        'infaq-page.php' => 'infaq',
        'qurban-page.php' => 'qurban',
        'login.php' => 'dashboard/login',
        'logout.php' => 'dashboard/logout',
        'setup-admin.php' => 'dashboard/setup-admin',
        'dashboard.php' => 'dashboard',
        'kajian.php' => 'dashboard/kajian',
        'artikel.php' => 'dashboard/artikel',
        'gallery.php' => 'dashboard/gallery',
        'video.php' => 'dashboard/video',
        'infaq.php' => 'dashboard/infaq',
        'qurban.php' => 'dashboard/qurban',
        'laporan-admin.php' => 'dashboard/laporan',
        'settings.php' => 'dashboard/settings',
    ];
}

function build_routed_path(string $path, array &$query): string
{
    $aliases = app_route_aliases();
    $path = ltrim($path, '/');

    if ($path === '' || $path === 'index.php') {
        return '';
    }

    $routedPath = $aliases[$path] ?? preg_replace('/\.php$/i', '', $path) ?? $path;

    if ($path === 'artikel-detail.php' && isset($query['slug']) && trim((string) $query['slug']) !== '') {
        $routedPath .= '/' . rawurlencode(trim((string) $query['slug']));
        unset($query['slug']);
    }

    if ($path === 'laporan-detail.php' && isset($query['slug']) && trim((string) $query['slug']) !== '') {
        $routedPath .= '/' . rawurlencode(trim((string) $query['slug']));
        unset($query['slug']);
    }

    if ($path === 'video-detail.php' && isset($query['id']) && trim((string) $query['id']) !== '') {
        $routedPath .= '/' . rawurlencode(trim((string) $query['id']));
        unset($query['id']);
    }

    return trim($routedPath, '/');
}

function app_url(string $path = ''): string
{
    $basePath = base_path();
    $path = trim($path);

    if ($path === '' || $path === '/') {
        return $basePath !== '' ? $basePath . '/' : '/';
    }

    if (preg_match('/^https?:\/\//i', $path) === 1) {
        return $path;
    }

    $parts = parse_url($path);
    if ($parts === false) {
        $parts = ['path' => $path];
    }

    $routePath = (string) ($parts['path'] ?? $path);
    $query = [];
    parse_str((string) ($parts['query'] ?? ''), $query);
    $fragment = isset($parts['fragment']) ? (string) $parts['fragment'] : '';

    $routedPath = build_routed_path($routePath, $query);
    $url = $basePath !== '' ? $basePath . '/' : '/';

    if ($routedPath !== '') {
        $url = ($basePath !== '' ? $basePath : '') . '/' . $routedPath;
    }

    if ($query !== []) {
        $url .= '?' . http_build_query($query);
    }

    if ($fragment !== '') {
        $url .= '#' . rawurlencode($fragment);
    }

    return $url;
}

function configured_app_origin(): string
{
    $appUrl = trim((string) getenv('APP_URL'));

    if ($appUrl === '') {
        return '';
    }

    $parts = parse_url($appUrl);
    if (!is_array($parts)) {
        return '';
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));
    $port = isset($parts['port']) ? (int) $parts['port'] : null;

    if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
        return '';
    }

    $origin = $scheme . '://' . $host;

    if ($port !== null && $port > 0 && !($scheme === 'http' && $port === 80) && !($scheme === 'https' && $port === 443)) {
        $origin .= ':' . $port;
    }

    return $origin;
}

function sanitize_request_host(string $value): string
{
    $value = trim(explode(',', $value)[0] ?? '');

    if ($value === '') {
        return '';
    }

    $candidate = str_contains($value, '://') ? $value : 'http://' . $value;
    $parts = parse_url($candidate);
    if (!is_array($parts)) {
        return '';
    }

    $host = strtolower((string) ($parts['host'] ?? ''));
    $port = isset($parts['port']) ? (int) $parts['port'] : null;

    if (
        $host === ''
        || preg_match('/^(?:localhost|[a-z0-9.-]+|\[[a-f0-9:]+\])$/i', $host) !== 1
    ) {
        return '';
    }

    if ($port !== null && ($port < 1 || $port > 65535)) {
        $port = null;
    }

    return $port !== null ? $host . ':' . $port : $host;
}

function app_origin(): string
{
    $configuredOrigin = configured_app_origin();
    if ($configuredOrigin !== '') {
        return $configuredOrigin;
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = sanitize_request_host((string) ($_SERVER['SERVER_NAME'] ?? ''));

    if ($host === '') {
        $host = sanitize_request_host((string) ($_SERVER['HTTP_HOST'] ?? ''));
    }

    if ($host === '') {
        $host = 'localhost';
    }

    $serverPort = isset($_SERVER['SERVER_PORT']) ? (int) $_SERVER['SERVER_PORT'] : null;
    if (
        $serverPort !== null
        && $serverPort > 0
        && !str_contains($host, ':')
        && !($scheme === 'http' && $serverPort === 80)
        && !($scheme === 'https' && $serverPort === 443)
    ) {
        $host .= ':' . $serverPort;
    }

    return $scheme . '://' . $host;
}

function absolute_app_url(string $path = ''): string
{
    return app_origin() . app_url($path);
}

function asset_url(string $path): string
{
    return app_url('assets/' . ltrim($path, '/'));
}

function ensure_session_started(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => base_path() !== '' ? base_path() . '/' : '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function absolute_request_url(?string $fallbackPath = null): string
{
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $path = $requestUri !== '' && str_starts_with($requestUri, '/')
        ? $requestUri
        : app_url($fallbackPath ?? '');

    return app_origin() . $path;
}

function csrf_token(): string
{
    if (!isset($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token']) || $_SESSION['_csrf_token'] === '') {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function has_valid_csrf_token(?string $token): bool
{
    return is_string($token) && $token !== '' && hash_equals(csrf_token(), $token);
}

function require_csrf_token(string $fallbackPath = 'dashboard.php'): void
{
    if (has_valid_csrf_token($_POST['_csrf'] ?? null)) {
        return;
    }

    $_SESSION['flash'] = [
        'type' => 'error',
        'message' => 'Sesi formulir tidak valid. Silakan coba lagi.',
    ];

    header('Location: ' . app_url($fallbackPath));
    exit;
}

function attempt_login(string $email, string $password, bool $remember = false): bool
{
    $statement = db()->prepare(
        'SELECT id, full_name, email, password_hash, role, is_active
         FROM admin_users
         WHERE email = :email
         LIMIT 1'
    );
    $statement->execute(['email' => $email]);

    $user = $statement->fetch();

    if ($user === false || (int) $user['is_active'] !== 1) {
        return false;
    }

    if (!password_verify($password, (string) $user['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'full_name' => (string) $user['full_name'],
        'email' => (string) $user['email'],
        'role' => (string) $user['role'],
    ];

    if ($remember) {
        $lifetime = 60 * 60 * 24 * 30;
        setcookie(session_name(), session_id(), [
            'expires' => time() + $lifetime,
            'path' => base_path() !== '' ? base_path() . '/' : '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    db()->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = :id')
        ->execute(['id' => (int) $user['id']]);

    return true;
}

function admin_user_count(): ?int
{
    try {
        $result = db()->query('SELECT COUNT(*) AS total FROM admin_users')->fetch();

        return $result !== false ? (int) ($result['total'] ?? 0) : 0;
    } catch (Throwable) {
        return null;
    }
}

function can_bootstrap_admin_user(): bool
{
    return admin_user_count() === 0;
}

function current_user_role(): string
{
    return is_logged_in() ? (string) ($_SESSION['user']['role'] ?? '') : '';
}

function user_has_role(string|array $roles): bool
{
    $roles = is_array($roles) ? $roles : [$roles];

    return is_logged_in() && in_array(current_user_role(), $roles, true);
}

function is_logged_in(): bool
{
    return isset($_SESSION['user']) && is_array($_SESSION['user']);
}

function current_user(): array
{
    return is_logged_in() ? $_SESSION['user'] : [];
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ' . app_url('login.php'));
        exit;
    }
}

function require_role(string|array $roles, string $fallbackPath = 'dashboard.php'): void
{
    require_login();

    if (user_has_role($roles)) {
        return;
    }

    $_SESSION['flash'] = [
        'type' => 'error',
        'message' => 'Anda tidak memiliki izin untuk membuka halaman ini.',
    ];

    header('Location: ' . app_url($fallbackPath));
    exit;
}

function auth_client_ip(): string
{
    $candidates = [
        (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''),
        trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''))[0] ?? ''),
        (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
    ];

    foreach ($candidates as $candidate) {
        if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
            return $candidate;
        }
    }

    return 'unknown';
}

function login_throttle_keys(string $email): array
{
    $normalizedEmail = strtolower(trim($email));
    $clientIp = auth_client_ip();

    return [
        'ip:' . hash('sha256', $clientIp),
        'combo:' . hash('sha256', $clientIp . '|' . $normalizedEmail),
    ];
}

function auth_fallback_throttle_state(): array
{
    $state = $_SESSION['_login_throttle'] ?? [];

    return is_array($state) ? $state : [];
}

function auth_login_block_seconds(string $email): int
{
    $keys = login_throttle_keys($email);
    $now = new DateTimeImmutable('now');
    $remaining = 0;

    try {
        $statement = db()->prepare(
            'SELECT blocked_until
             FROM login_attempts
             WHERE attempt_key = :attempt_key
             LIMIT 1'
        );

        foreach ($keys as $key) {
            $statement->execute(['attempt_key' => $key]);
            $row = $statement->fetch();
            if ($row === false || empty($row['blocked_until'])) {
                continue;
            }

            $blockedUntil = date_create((string) $row['blocked_until']);
            if ($blockedUntil instanceof DateTimeInterface && $blockedUntil > $now) {
                $remaining = max($remaining, $blockedUntil->getTimestamp() - $now->getTimestamp());
            }
        }

        return $remaining;
    } catch (Throwable) {
        $state = auth_fallback_throttle_state();

        foreach ($keys as $key) {
            $row = $state[$key] ?? null;
            if (!is_array($row) || empty($row['blocked_until'])) {
                continue;
            }

            $blockedUntil = date_create((string) $row['blocked_until']);
            if ($blockedUntil instanceof DateTimeInterface && $blockedUntil > $now) {
                $remaining = max($remaining, $blockedUntil->getTimestamp() - $now->getTimestamp());
            }
        }

        return $remaining;
    }
}

function register_failed_login_attempt(string $email): void
{
    $keys = login_throttle_keys($email);
    $now = new DateTimeImmutable('now');
    $windowStart = $now->modify('-15 minutes');

    try {
        $select = db()->prepare(
            'SELECT attempt_count, last_attempt_at, blocked_until
             FROM login_attempts
             WHERE attempt_key = :attempt_key
             LIMIT 1'
        );
        $update = db()->prepare(
            'UPDATE login_attempts
             SET attempt_count = :attempt_count,
                 last_attempt_at = :last_attempt_at,
                 blocked_until = :blocked_until,
                 updated_at = CURRENT_TIMESTAMP
             WHERE attempt_key = :attempt_key'
        );
        $insert = db()->prepare(
            'INSERT INTO login_attempts (attempt_key, attempt_count, last_attempt_at, blocked_until)
             VALUES (:attempt_key, :attempt_count, :last_attempt_at, :blocked_until)'
        );

        foreach ($keys as $key) {
            $select->execute(['attempt_key' => $key]);
            $row = $select->fetch();
            $attemptCount = 1;

            if ($row !== false) {
                $lastAttempt = date_create((string) ($row['last_attempt_at'] ?? ''));
                if ($lastAttempt instanceof DateTimeInterface && $lastAttempt >= $windowStart) {
                    $attemptCount = (int) ($row['attempt_count'] ?? 0) + 1;
                }
            }

            $blockedUntil = null;
            if ($attemptCount >= 5) {
                $blockSeconds = min(1800, 300 * ($attemptCount - 4));
                $blockedUntil = $now->modify('+' . $blockSeconds . ' seconds')->format('Y-m-d H:i:s');
            }

            $payload = [
                'attempt_key' => $key,
                'attempt_count' => $attemptCount,
                'last_attempt_at' => $now->format('Y-m-d H:i:s'),
                'blocked_until' => $blockedUntil,
            ];

            if ($row !== false) {
                $update->execute($payload);
            } else {
                $insert->execute($payload);
            }
        }

        return;
    } catch (Throwable) {
        $state = auth_fallback_throttle_state();

        foreach ($keys as $key) {
            $row = is_array($state[$key] ?? null) ? $state[$key] : [];
            $attemptCount = 1;

            if (!empty($row['last_attempt_at'])) {
                $lastAttempt = date_create((string) $row['last_attempt_at']);
                if ($lastAttempt instanceof DateTimeInterface && $lastAttempt >= $windowStart) {
                    $attemptCount = (int) ($row['attempt_count'] ?? 0) + 1;
                }
            }

            $blockedUntil = null;
            if ($attemptCount >= 5) {
                $blockSeconds = min(1800, 300 * ($attemptCount - 4));
                $blockedUntil = $now->modify('+' . $blockSeconds . ' seconds')->format('Y-m-d H:i:s');
            }

            $state[$key] = [
                'attempt_count' => $attemptCount,
                'last_attempt_at' => $now->format('Y-m-d H:i:s'),
                'blocked_until' => $blockedUntil,
            ];
        }

        $_SESSION['_login_throttle'] = $state;
    }
}

function clear_failed_login_attempts(string $email): void
{
    $keys = login_throttle_keys($email);

    try {
        $statement = db()->prepare('DELETE FROM login_attempts WHERE attempt_key = :attempt_key');
        foreach ($keys as $key) {
            $statement->execute(['attempt_key' => $key]);
        }
    } catch (Throwable) {
        $state = auth_fallback_throttle_state();
        foreach ($keys as $key) {
            unset($state[$key]);
        }
        $_SESSION['_login_throttle'] = $state;
    }
}

function logout_user(): void
{
    ensure_session_started();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }

    session_destroy();
}
