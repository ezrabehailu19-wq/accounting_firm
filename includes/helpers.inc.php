<?php
/**
 * Small helpers shared by every page: escaping, formatting, access
 * guards, flash messages and pagination.
 */

declare(strict_types=1);

// =========================================================
//  Output escaping
// =========================================================

/**
 * Escape for HTML. Named `e` deliberately — it is used on every single
 * echoed value, and a short name is what makes that habit stick.
 *
 * ENT_QUOTES covers both quote styles, so this is also safe inside an
 * HTML attribute. Never use it for a URL or inside a <script> block;
 * those need url_attr() and json_encode() respectively.
 */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Escape a value being placed in a query string. */
function url_attr($value): string
{
    return e(rawurlencode((string) $value));
}

/** Plain text to HTML with line breaks preserved. */
function e_multiline($value): string
{
    return nl2br(e($value));
}

// =========================================================
//  Formatting
// =========================================================

function money($amount, string $currency = ''): string
{
    $currency = $currency !== '' ? $currency : CURRENCY;

    return $currency . ' ' . number_format((float) $amount, 2);
}

function fmt_date(?string $value, string $format = 'd M Y'): string
{
    if ($value === null || $value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return '—';
    }

    $ts = strtotime($value);

    return $ts === false ? '—' : date($format, $ts);
}

function fmt_datetime(?string $value): string
{
    return fmt_date($value, 'd M Y, H:i');
}

/** "3 hours ago" — easier to scan in an activity feed than a timestamp. */
function time_ago(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    $ts = strtotime($value);
    if ($ts === false) {
        return '—';
    }

    $diff = time() - $ts;

    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return intdiv($diff, 60) . 'm ago';
    if ($diff < 86400)  return intdiv($diff, 3600) . 'h ago';
    if ($diff < 604800) return intdiv($diff, 86400) . 'd ago';

    return date('d M Y', $ts);
}

function human_size($bytes): string
{
    $bytes = (float) $bytes;
    $units = ['B', 'KB', 'MB', 'GB'];
    $i     = 0;

    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }

    return round($bytes, $i === 0 ? 0 : 1) . ' ' . $units[$i];
}

/** A short glyph for a file type, used in the documents list. */
function file_glyph(string $filename): string
{
    $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

    $map = [
        'pdf'  => 'PDF',
        'doc'  => 'DOC', 'docx' => 'DOC',
        'xls'  => 'XLS', 'xlsx' => 'XLS', 'csv' => 'CSV',
        'png'  => 'IMG', 'jpg'  => 'IMG', 'jpeg' => 'IMG', 'webp' => 'IMG',
        'zip'  => 'ZIP',
        'txt'  => 'TXT',
    ];

    return $map[$ext] ?? 'FILE';
}

// =========================================================
//  Status labels
// =========================================================

function request_status_label(string $status): string
{
    $labels = [
        'new'         => 'New',
        'read'        => 'Read',
        'in_progress' => 'In progress',
        'replied'     => 'Replied',
        'closed'      => 'Closed',
    ];

    return $labels[$status] ?? ucfirst($status);
}

function invoice_status_label(string $status): string
{
    $labels = [
        'draft'   => 'Draft',
        'sent'    => 'Sent',
        'paid'    => 'Paid',
        'overdue' => 'Overdue',
        'void'    => 'Void',
    ];

    return $labels[$status] ?? ucfirst($status);
}

/** Renders a status pill. The modifier class drives the colour in CSS. */
function status_pill(string $status, string $label): string
{
    return '<span class="pill pill--' . e($status) . '">' . e($label) . '</span>';
}

/**
 * Where a request sits in its lifecycle, 0–1, for the client-facing
 * progress track. Closed and replied both read as finished to a client.
 */
function request_progress(string $status): float
{
    $steps = [
        'new'         => 0.15,
        'read'        => 0.40,
        'in_progress' => 0.70,
        'replied'     => 1.00,
        'closed'      => 1.00,
    ];

    return $steps[$status] ?? 0.0;
}

// =========================================================
//  Access control
// =========================================================

function is_logged_in(): bool
{
    return !empty($_SESSION['loggedin']) && !empty($_SESSION['user_id']);
}

function is_admin(): bool
{
    return is_logged_in() && ($_SESSION['role'] ?? '') === 'admin';
}

/** The signed-in user as an array, or null. */
function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }

    return [
        'id'        => (int) $_SESSION['user_id'],
        'username'  => (string) ($_SESSION['username'] ?? ''),
        'role'      => (string) ($_SESSION['role'] ?? 'user'),
        'full_name' => (string) ($_SESSION['full_name'] ?? ''),
    ];
}

/**
 * Require a signed-in session, or bounce to the login page.
 *
 * The browser's fingerprint is re-checked here: a session cookie lifted
 * from one machine and replayed on another will not match, and gets
 * dropped rather than honoured.
 */
function require_login(): array
{
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? 'user_dashboard.php';
        redirect('login.php');
    }

    if (isset($_SESSION['fingerprint']) && $_SESSION['fingerprint'] !== Auth::fingerprint()) {
        $_SESSION = [];
        session_destroy();
        session_start();
        $_SESSION['flash'] = ['type' => 'error', 'text' => 'Your session could not be verified. Please sign in again.'];
        redirect('login.php');
    }

    return current_user();
}

function require_admin(): array
{
    $user = require_login();

    if ($user['role'] !== 'admin') {
        http_response_code(403);
        $_SESSION['flash'] = ['type' => 'error', 'text' => 'That area is for staff accounts only.'];
        redirect('user_dashboard.php');
    }

    return $user;
}

function redirect(string $to): void
{
    if (!headers_sent()) {
        header('Location: ' . $to);
    }

    exit;
}

// =========================================================
//  Flash messages
// =========================================================

function flash(string $type, string $text): void
{
    $_SESSION['flash'] = ['type' => $type, 'text' => $text];
}

/** Read and clear. Returns null when there is nothing waiting. */
function take_flash(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function render_flash(): string
{
    $flash = take_flash();

    if ($flash === null) {
        return '';
    }

    $type = in_array($flash['type'], ['success', 'error', 'info'], true) ? $flash['type'] : 'info';

    return '<div class="notice notice--' . $type . '" role="status">' . e($flash['text']) . '</div>';
}

// =========================================================
//  Request input
// =========================================================

function get_int(string $key, int $default = 0): int
{
    return isset($_GET[$key]) && is_scalar($_GET[$key]) ? (int) $_GET[$key] : $default;
}

function get_str(string $key, string $default = ''): string
{
    return isset($_GET[$key]) && is_scalar($_GET[$key]) ? trim((string) $_GET[$key]) : $default;
}

function post_str(string $key, string $default = ''): string
{
    return isset($_POST[$key]) && is_scalar($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
}

function post_int(string $key, int $default = 0): int
{
    return isset($_POST[$key]) && is_scalar($_POST[$key]) ? (int) $_POST[$key] : $default;
}

function current_page(): int
{
    return max(1, get_int('page', 1));
}

// =========================================================
//  Pagination
// =========================================================

/**
 * Render page links, preserving the filters already in the query string
 * so paging never silently drops the search the user typed.
 */
function render_pagination(int $total, int $page, string $baseUrl = '', int $perPage = 0): string
{
    $perPage = $perPage > 0 ? $perPage : PER_PAGE;
    $pages   = (int) ceil($total / max(1, $perPage));

    if ($pages <= 1) {
        return '';
    }

    $query = $_GET;
    unset($query['page']);
    $baseUrl = $baseUrl !== '' ? $baseUrl : basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));

    $link = static function (int $target) use ($query, $baseUrl): string {
        $query['page'] = $target;

        return e($baseUrl . '?' . http_build_query($query));
    };

    $from = ($page - 1) * $perPage + 1;
    $to   = min($total, $page * $perPage);

    $html  = '<nav class="pager" aria-label="Pagination">';
    $html .= '<p class="pager__count">' . $from . '–' . $to . ' of ' . $total . '</p>';
    $html .= '<div class="pager__links">';

    $html .= $page > 1
        ? '<a class="pager__btn" href="' . $link($page - 1) . '" rel="prev">Previous</a>'
        : '<span class="pager__btn is-disabled">Previous</span>';

    // A window of pages around the current one, with ellipses, so a
    // 300-page list doesn't render 300 links.
    $window = 2;
    $shown  = [];

    for ($i = 1; $i <= $pages; $i++) {
        if ($i === 1 || $i === $pages || abs($i - $page) <= $window) {
            $shown[] = $i;
        }
    }

    $previous = 0;
    foreach ($shown as $number) {
        if ($previous && $number - $previous > 1) {
            $html .= '<span class="pager__gap">…</span>';
        }

        $html .= $number === $page
            ? '<span class="pager__btn is-current" aria-current="page">' . $number . '</span>'
            : '<a class="pager__btn" href="' . $link($number) . '">' . $number . '</a>';

        $previous = $number;
    }

    $html .= $page < $pages
        ? '<a class="pager__btn" href="' . $link($page + 1) . '" rel="next">Next</a>'
        : '<span class="pager__btn is-disabled">Next</span>';

    $html .= '</div></nav>';

    return $html;
}

/**
 * Rebuild the current URL with some query parameters changed.
 *
 * Returns a RAW url, not an HTML-escaped one, because its main job is
 * feeding redirect() — and an escaped ampersand in a Location header
 * sends the browser to a different address than the one intended.
 * Escape it with e() at the point of use if it goes into markup.
 */
function query_with(array $changes): string
{
    $query = array_merge($_GET, $changes);

    foreach ($query as $key => $value) {
        if ($value === '' || $value === null) {
            unset($query[$key]);
        }
    }

    return basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''))
        . ($query ? '?' . http_build_query($query) : '');
}
