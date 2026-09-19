<?php
/**
 * Page chrome for the signed-in portal.
 *
 * One layout, two navigations: staff see the practice management items,
 * clients see their own records. Building this once means a new page is
 * a handful of lines, and the navigation can never drift out of sync
 * between pages — which is exactly what had started to happen when every
 * admin page carried its own copy of the header markup.
 */

declare(strict_types=1);

/**
 * The navigation for a given role.
 *
 * @return array<int, array{href: string, label: string, key: string, glyph: string}>
 */
function portal_nav(string $role): array
{
    if ($role === 'admin') {
        return [
            ['key' => 'dashboard', 'href' => 'admin.php',           'label' => 'Overview',   'glyph' => 'M3 13h6V3H3v10Zm0 8h6v-6H3v6Zm8 0h10V11H11v10Zm0-18v6h10V3H11Z'],
            ['key' => 'inbox',     'href' => 'admin_messages.php',  'label' => 'Requests',   'glyph' => 'M3 5h18v14H3V5Zm0 0 9 7 9-7'],
            ['key' => 'invoices',  'href' => 'admin_invoices.php',  'label' => 'Invoices',   'glyph' => 'M6 2h12v20l-3-2-3 2-3-2-3 2V2Zm3 6h6M9 12h6M9 16h3'],
            ['key' => 'documents', 'href' => 'documents.php',       'label' => 'Documents',  'glyph' => 'M13 2H6v20h12V7l-5-5Zm0 0v5h5M9 13h6M9 17h6'],
            ['key' => 'clients',   'href' => 'admin_users.php',     'label' => 'Clients',    'glyph' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-8 9a8 8 0 0 1 16 0'],
            ['key' => 'audit',     'href' => 'admin_audit.php',     'label' => 'Audit trail','glyph' => 'M12 8v5l3 2M12 3a9 9 0 1 0 9 9'],
        ];
    }

    return [
        ['key' => 'dashboard', 'href' => 'user_dashboard.php', 'label' => 'Overview',  'glyph' => 'M3 13h6V3H3v10Zm0 8h6v-6H3v6Zm8 0h10V11H11v10Zm0-18v6h10V3H11Z'],
        ['key' => 'requests',  'href' => 'my_requests.php',    'label' => 'My requests','glyph' => 'M3 5h18v14H3V5Zm0 0 9 7 9-7'],
        ['key' => 'invoices',  'href' => 'my_invoices.php',    'label' => 'Invoices',  'glyph' => 'M6 2h12v20l-3-2-3 2-3-2-3 2V2Zm3 6h6M9 12h6M9 16h3'],
        ['key' => 'documents', 'href' => 'documents.php',      'label' => 'Documents', 'glyph' => 'M13 2H6v20h12V7l-5-5Zm0 0v5h5M9 13h6M9 17h6'],
        ['key' => 'profile',   'href' => 'profile.php',        'label' => 'Profile',   'glyph' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-8 9a8 8 0 0 1 16 0'],
    ];
}

/**
 * Open the page.
 *
 * @param string $title  Browser title and page heading
 * @param string $active Which nav item to mark current
 * @param array  $opts   'subtitle', 'actions' (raw HTML), 'wide' (bool)
 */
function portal_head(string $title, string $active = '', array $opts = []): void
{
    $user     = current_user() ?? ['username' => 'Guest', 'role' => 'user', 'full_name' => 'Guest'];
    $role     = $user['role'];
    $nav      = portal_nav($role);
    $subtitle = $opts['subtitle'] ?? '';
    $actions  = $opts['actions'] ?? '';
    $display  = $user['full_name'] !== '' ? $user['full_name'] : $user['username'];

    // Two letters is enough for an avatar and avoids shipping an image
    // upload flow for something nobody asked for.
    $initials = mb_strtoupper(mb_substr($display, 0, 1));
    $parts    = preg_split('/\s+/', trim($display)) ?: [];
    if (count($parts) > 1) {
        $initials .= mb_strtoupper(mb_substr(end($parts), 0, 1));
    }
    ?>
<!DOCTYPE html>
<html lang="en" class="portal">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · <?= e(APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap">
<link rel="stylesheet" href="style.css">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='7' fill='%230C1B2A'/><text x='16' y='22' font-family='Georgia' font-size='15' fill='%23C8A04A' text-anchor='middle'>S</text></svg>">
</head>
<body class="shell">

<a class="skip-link" href="#main">Skip to content</a>

<input type="checkbox" id="navToggle" class="nav-toggle-state" hidden>

<aside class="rail" aria-label="Main navigation">
    <a class="rail__brand" href="<?= $role === 'admin' ? 'admin.php' : 'user_dashboard.php' ?>">
        <span class="mark" aria-hidden="true">
            <span class="mark__face mark__face--top">S</span>
            <span class="mark__face mark__face--side"></span>
        </span>
        <span class="rail__brandtext">
            <strong><?= e(APP_NAME) ?></strong>
            <small><?= $role === 'admin' ? 'Practice console' : 'Client portal' ?></small>
        </span>
    </a>

    <nav class="rail__nav">
        <?php foreach ($nav as $item): ?>
            <a class="rail__link<?= $item['key'] === $active ? ' is-active' : '' ?>"
               href="<?= e($item['href']) ?>"
               <?= $item['key'] === $active ? 'aria-current="page"' : '' ?>>
                <svg class="rail__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="<?= e($item['glyph']) ?>"/>
                </svg>
                <span><?= e($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="rail__foot">
        <a class="rail__link rail__link--quiet" href="index.html">
            <svg class="rail__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 11 12 3l9 8M5 10v10h14V10"/>
            </svg>
            <span>Public site</span>
        </a>
        <a class="rail__link rail__link--quiet" href="logout.php">
            <svg class="rail__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M15 17l5-5-5-5M20 12H9M12 3H5v18h7"/>
            </svg>
            <span>Sign out</span>
        </a>
    </div>
</aside>

<label for="navToggle" class="nav-scrim" aria-hidden="true"></label>

<div class="canvas<?= !empty($opts['wide']) ? ' canvas--wide' : '' ?>">
    <header class="topbar">
        <label for="navToggle" class="topbar__burger" aria-label="Open navigation">
            <span></span><span></span><span></span>
        </label>

        <div class="topbar__titles">
            <h1><?= e($title) ?></h1>
            <?php if ($subtitle !== ''): ?><p><?= e($subtitle) ?></p><?php endif; ?>
        </div>

        <div class="topbar__right">
            <?= $actions ?>
            <a class="whoami" href="profile.php" title="Signed in as <?= e($user['username']) ?>">
                <span class="whoami__badge"><?= e($initials) ?></span>
                <span class="whoami__text">
                    <strong><?= e($display) ?></strong>
                    <small><?= $role === 'admin' ? 'Staff' : 'Client' ?></small>
                </span>
            </a>
        </div>
    </header>

    <main class="main" id="main">
        <?= render_flash() ?>
<?php
}

function portal_foot(): void
{
    ?>
    </main>

    <footer class="canvas__foot">
        <p>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. <?= e(APP_TAGLINE) ?>.</p>
    </footer>
</div>

<script src="script.js" defer></script>
</body>
</html>
<?php
}

/**
 * The sign-in / register / reset shell.
 *
 * Deliberately a different layout from the portal: a person on these
 * pages has no account context yet, so a sidebar of links they cannot
 * use would be noise. The left panel carries the firm's ledger motif and
 * the right panel holds nothing but the form.
 */
function auth_head(string $title, string $lede = ''): void
{
    ?>
<!DOCTYPE html>
<html lang="en" class="portal">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · <?= e(APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap">
<link rel="stylesheet" href="style.css">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='7' fill='%230C1B2A'/><text x='16' y='22' font-family='Georgia' font-size='15' fill='%23C8A04A' text-anchor='middle'>S</text></svg>">
</head>
<body class="gate">

<div class="gate__aside">
    <a class="gate__brand" href="index.html">
        <span class="mark" aria-hidden="true">
            <span class="mark__face mark__face--top">S</span>
            <span class="mark__face mark__face--side"></span>
        </span>
        <span>
            <strong><?= e(APP_NAME) ?></strong>
            <small><?= e(APP_TAGLINE) ?></small>
        </span>
    </a>

    <!-- The ledger stack: three ruled sheets in perspective. It is the
         one decorative element on these pages, and it is the object this
         business actually works with. -->
    <div class="ledger" aria-hidden="true">
        <div class="ledger__sheet ledger__sheet--3">
            <span class="ledger__rule"></span><span class="ledger__rule"></span>
            <span class="ledger__rule"></span><span class="ledger__rule"></span>
        </div>
        <div class="ledger__sheet ledger__sheet--2">
            <span class="ledger__rule"></span><span class="ledger__rule"></span>
            <span class="ledger__rule"></span><span class="ledger__rule"></span>
        </div>
        <div class="ledger__sheet ledger__sheet--1">
            <span class="ledger__seal">VAT</span>
            <span class="ledger__rule"></span><span class="ledger__rule"></span>
            <span class="ledger__rule"></span><span class="ledger__rule"></span>
            <span class="ledger__rule"></span>
        </div>
    </div>

    <blockquote class="gate__note">
        <p>Every figure traceable to a document, every document traceable to a client.</p>
        <cite>How this practice works</cite>
    </blockquote>
</div>

<div class="gate__panel">
    <div class="gate__card">
        <h1 class="gate__title"><?= e($title) ?></h1>
        <?php if ($lede !== ''): ?><p class="gate__lede"><?= e($lede) ?></p><?php endif; ?>
        <?= render_flash() ?>
<?php
}

function auth_foot(): void
{
    ?>
    </div>
    <p class="gate__back"><a href="index.html">Back to the website</a></p>
</div>

<script src="script.js" defer></script>
</body>
</html>
<?php
}

/**
 * Empty-state block. An empty screen should tell you what to do next,
 * not just report that there is nothing here.
 */
function empty_state(string $heading, string $body, string $actionHtml = ''): string
{
    return '<div class="empty">'
        . '<h3>' . e($heading) . '</h3>'
        . '<p>' . e($body) . '</p>'
        . ($actionHtml !== '' ? '<div class="empty__action">' . $actionHtml . '</div>' : '')
        . '</div>';
}
