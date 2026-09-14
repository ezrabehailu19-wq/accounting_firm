<?php
/**
 * Application configuration loader.
 *
 * Reads local environment variables from a .env file (never committed to
 * git) and exposes the ones the app needs as constants. This keeps real
 * credentials out of source control while still giving the rest of the
 * codebase a simple, predictable way to read them (DB_HOST, DB_USER, ...).
 *
 * Setup for a fresh checkout:
 *   1. Copy .env.example to .env
 *   2. Fill in your real local database credentials in .env
 *   3. Never commit .env (it's already in .gitignore)
 */

function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        throw new RuntimeException(
            "Missing .env file at {$path}. Copy .env.example to .env and fill in your database credentials."
        );
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        // Skip blank lines and comments
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        if (strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));

        // Strip surrounding quotes, e.g. DB_PASS="secret"
        $value = trim($value, "\"'");

        if (!array_key_exists($key, $_ENV)) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }
}

loadEnv(__DIR__ . '/.env');

define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_USER', $_ENV['DB_USER'] ?? '');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_NAME', $_ENV['DB_NAME'] ?? '');