<?php
declare(strict_types=1);

function load_environment_file(?string $filePath = null): void
{
    static $loaded = false;

    if ($loaded) {
        return;
    }

    $loaded = true;
    $filePath = $filePath ?? dirname(__DIR__) . '/.env';

    if (!is_file($filePath) || !is_readable($filePath)) {
        return;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (str_starts_with($line, 'export ')) {
            $line = trim(substr($line, 7));
        }

        $separatorPosition = strpos($line, '=');
        if ($separatorPosition === false) {
            continue;
        }

        $name = trim(substr($line, 0, $separatorPosition));
        $value = trim(substr($line, $separatorPosition + 1));

        if ($name === '' || preg_match('/^[A-Z0-9_]+$/i', $name) !== 1) {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, '\'') && str_ends_with($value, '\''))
        ) {
            $value = substr($value, 1, -1);
        }

        if (env_value($name) !== null) {
            continue;
        }

        if (function_exists('putenv')) {
            putenv($name . '=' . $value);
        }

        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

function env_value(string $name, ?string $default = null): ?string
{
    $value = getenv($name);

    if ($value !== false) {
        return (string) $value;
    }

    if (array_key_exists($name, $_ENV)) {
        return (string) $_ENV[$name];
    }

    if (array_key_exists($name, $_SERVER)) {
        return (string) $_SERVER[$name];
    }

    return $default;
}
