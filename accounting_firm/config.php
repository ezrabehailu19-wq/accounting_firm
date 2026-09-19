<?php
/**
 * Application configuration.
 *
 * Reads local settings from a .env file (never committed) and exposes
 * them as constants. Keeping credentials here rather than inside
 * Db.class.php means the code can be shared or pushed to git without
 * also handing over the database password.
 *
 * Fresh checkout:
 *   1. cp .env.example .env
 *   2. fill in your real values
 *   3. import schema.sql
 *   4. open setup_admin.php once, then delete it
 */

declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__);
}

/**
 * Minimal .env parser. Deliberately not a dependency — this app has no
 * composer install step, and the format we need is four lines of KEY=value.
 *
 * Supports: comments (#), blank lines, quoted values, and values that
 * themselves contain '=' (a password like "a=b" parses correctly because
 * we split on the first '=' only).
 */
function load_env(string $path): array
{
    if (!is_readable($path)) {
        return [];
    }

    $vars  = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        // Strip one matching pair of surrounding quotes, if present.
        $len = strlen($value);
        if ($len >= 2) {
            $first = $value[0];
            $last  = $value[$len - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        $vars[$key] = $value;
    }

    return $vars;
}

$env = load_env(APP_ROOT . '/.env');

/**
 * Read a setting: .env first, then the real environment (so the app can
 * also be configured through Docker/Apache SetEnv), then a default.
 */
function env_get(string $key, string $default = ''): string
{
    global $env;

    if (array_key_exists($key, $env)) {
        return $env[$key];
    }

    $fromSystem = getenv($key);

    return ($fromSystem === false || $fromSystem === '') ? $default : $fromSystem;
}

function env_bool(string $key, bool $default = false): bool
{
    $raw = strtolower(trim(env_get($key, $default ? 'true' : 'false')));

    return in_array($raw, ['1', 'true', 'yes', 'on'], true);
}

// ---------- Database ----------
define('DB_HOST', env_get('DB_HOST', 'localhost'));
define('DB_USER', env_get('DB_USER', 'root'));
define('DB_PASS', env_get('DB_PASS', ''));
define('DB_NAME', env_get('DB_NAME', 'accounting_firm'));
define('DB_PORT', (int) env_get('DB_PORT', '3306'));

// ---------- Application ----------
define('APP_ENV',   env_get('APP_ENV', 'production'));   // 'development' shows real errors
define('APP_DEBUG', env_bool('APP_DEBUG', false));
define('APP_NAME',  env_get('APP_NAME', 'Selamawit H/Mariam'));
define('APP_TAGLINE', env_get('APP_TAGLINE', 'Accounting & Financial Consulting'));

// ---------- Business details (printed on invoices) ----------
define('FIRM_ADDRESS', env_get('FIRM_ADDRESS', 'Ureal Area, 6th Floor, Addis Ababa, Ethiopia'));
define('FIRM_PHONE',   env_get('FIRM_PHONE', '+251 933 5831'));
define('FIRM_EMAIL',   env_get('FIRM_EMAIL', 'info@example.com'));
define('FIRM_TIN',     env_get('FIRM_TIN', ''));          // Ethiopian taxpayer ID
define('CURRENCY',     env_get('CURRENCY', 'ETB'));
define('VAT_RATE',     (float) env_get('VAT_RATE', '15'));

// ---------- Notifications ----------
define('ADMIN_NOTIFY_EMAIL', env_get('ADMIN_NOTIFY_EMAIL', ''));
define('MAIL_FROM',          env_get('MAIL_FROM', 'no-reply@localhost'));
// When true, outgoing mail is written to storage/mail.log instead of being
// sent. Lets you test the reset and notification flows before SMTP exists.
define('MAIL_LOG_ONLY',      env_bool('MAIL_LOG_ONLY', true));

// ---------- File uploads ----------
// Storage lives OUTSIDE the pages directory tree and is additionally
// blocked by storage/.htaccess. Uploaded files are never served directly;
// document_download.php streams them after checking the session.
define('STORAGE_PATH',   APP_ROOT . '/storage');
define('DOCUMENTS_PATH', STORAGE_PATH . '/documents');
define('MAX_UPLOAD_BYTES', (int) env_get('MAX_UPLOAD_MB', '10') * 1024 * 1024);

/**
 * Upload allow-list, keyed by extension => permitted MIME types.
 *
 * This is an allow-list rather than a block-list on purpose. A block-list
 * ("anything except .php") always loses: .phtml, .php5, .phar and a dozen
 * other extensions execute on a default Apache, and that is how a file
 * upload form turns into a remote shell.
 */
define('ALLOWED_UPLOAD_TYPES', [
    'pdf'  => ['application/pdf'],
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png'  => ['image/png'],
    'webp' => ['image/webp'],
    'doc'  => ['application/msword'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    'xls'  => ['application/vnd.ms-excel'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    'csv'  => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'],
    'txt'  => ['text/plain'],
    'zip'  => ['application/zip', 'application/x-zip-compressed'],
]);

// ---------- Security tuning ----------
define('LOGIN_MAX_ATTEMPTS',   (int) env_get('LOGIN_MAX_ATTEMPTS', '5'));
define('LOGIN_LOCKOUT_MINUTES', (int) env_get('LOGIN_LOCKOUT_MINUTES', '15'));
define('RESET_TOKEN_MINUTES',  (int) env_get('RESET_TOKEN_MINUTES', '30'));
define('SESSION_IDLE_MINUTES', (int) env_get('SESSION_IDLE_MINUTES', '60'));
define('PASSWORD_MIN_LENGTH',  8);
define('PER_PAGE',             (int) env_get('PER_PAGE', '15'));

// ---------- Error display ----------
// In production the visitor sees a neutral page and the detail goes to the
// PHP error log. Leaking a stack trace tells an attacker your file paths,
// database name and often the query that failed.
if (APP_ENV === 'development' || APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

date_default_timezone_set(env_get('APP_TIMEZONE', 'Africa/Addis_Ababa'));
