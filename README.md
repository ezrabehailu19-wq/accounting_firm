# Selamawit H/Mariam — Accounting &amp; Financial Consulting

A public website plus a client portal and practice console, in plain PHP
and MySQL. No framework, no build step, no Composer — copy the folder to
a server with PHP and it runs.

---

## What is in here

**Public site** — `index.html`, `services.html`, `about.html`, `faq.html`,
`contact.html`. Static pages; the contact form posts to `contact_submit.php`.

**Client portal** — sign in to track requests, exchange documents and view
invoices.

**Practice console** — staff side: the request inbox, client management,
invoicing, documents and an audit trail.

---

## Requirements

- PHP 7.4 or newer (8.1+ recommended), with `mysqli` and `fileinfo`
- MySQL 5.7+ or MariaDB 10.3+
- Apache with `mod_rewrite` and `AllowOverride All`, or nginx (see below)

---

## Installation

```bash
# 1. Put the files where your web server serves from
#    (XAMPP: C:\xampp\htdocs\accounting_firm, Linux: /var/www/accounting_firm)

# 2. Configure
cp .env.example .env
#    then edit .env — DB_USER and DB_PASS at minimum

# 3. Create the database
mysql -u root -p < schema.sql

# 4. Make the storage folder writable by the web server
mkdir -p storage/documents
chmod 750 storage storage/documents
chown -R www-data:www-data storage        # Linux; skip on Windows/XAMPP
```

Then open `setup_admin.php` in your browser, create your staff account,
and **delete that file**. It refuses to run a second time, but there is
no reason to leave it there.

Sign in at `login.php`.

### Already running the old version?

Do not import `schema.sql` — it would not destroy your data, but the
migration is the supported path:

```bash
mysqldump -u root -p accounting_firm > backup.sql     # always
mysql -u root -p accounting_firm < migrations/2026_01_portal_upgrade.sql
```

The migration is written to be safe to run twice. It adds the new columns
and tables, gives every existing message a tracking reference, and links
old anonymous messages to accounts where the email address matches.

---

## Email

Out of the box, `MAIL_LOG_ONLY=true` writes every outgoing message to
`storage/mail.log` instead of sending it. That is deliberate: PHP's
`mail()` silently does nothing on XAMPP and most shared hosting, which
makes "the password reset email never arrived" almost impossible to
debug. Test the flows against the log first.

For real delivery, set `MAIL_LOG_ONLY=false` and make sure an MTA is
configured. For anything serious, drop PHPMailer into `sendMail()` in
`classes/Controler.class.php` — it is the only place that sends mail, so
nothing else has to change.

---

## nginx

The `.htaccess` files are Apache-specific. The equivalent server block:

```nginx
server {
    root /var/www/accounting_firm;
    index index.html index.php;

    # Never serve configuration, data or application internals.
    location ~ ^/(classes|includes|migrations|storage)/ { deny all; }
    location ~ /\.            { deny all; }
    location ~ \.(env|sql|log|md|ini|bak)$ { deny all; }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

---

## How it is put together

```
config.php              Reads .env, defines every constant
schema.sql              Fresh install
migrations/             Upgrades for existing installs

classes/
  Db.class.php          Connection (cached) + prepared-statement helpers
  Model.class.php       Every SQL statement in the application
  Controler.class.php   Validation, policy, mail, audit
  Auth.class.php        Login, lockout, session establishment

includes/
  includes.inc.php      Bootstrap: autoload, session, security headers
  csrf.php              Token generation and verification
  helpers.inc.php       Escaping, formatting, guards, pagination
  layout.inc.php        Portal shell and auth shell

storage/documents/      Uploaded files — outside any served path
```

The split is strict on purpose: `Model` never decides anything, and
nothing outside `Model` writes SQL. When a bug turns out to be a bad
query, there is exactly one file to look in.

### Adding a page

```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/includes.inc.php';
require_once __DIR__ . '/includes/layout.inc.php';

$user      = require_admin();          // or require_login()
$controler = new Controler();

portal_head('Page title', 'nav-key', ['subtitle' => 'Optional']);
?>
<section class="panel">…</section>
<?php portal_foot();
```

---

## Security notes

Worth knowing if you are going to maintain this.

**Nothing concatenates user input into SQL.** Every query goes through
`Db::select()` or `Db::execute()` with bound parameters. There is no code
path that builds a query string from a variable.

**Uploads are allow-listed by extension *and* verified by content.** A
block-list ("anything except .php") always loses — `.phtml`, `.php5` and
`.phar` all execute on a default Apache. The real MIME type is read from
the file itself with `finfo`, because the browser-supplied `Content-Type`
is attacker-chosen.

**Uploaded files never live under a served path.** `storage/` sits
outside the pages and is denied by `.htaccess` as well.
`document_download.php` checks the session before streaming a byte, and
always sends `Content-Disposition: attachment` — serving a user upload
inline is a stored XSS with extra steps.

**Rate limiting is per username *and* per IP.** Locking on username alone
lets anyone lock a real customer out of their own account by failing five
times on purpose.

**Reset tokens are stored hashed.** A leaked database backup should not
hand out working password-reset links.

**Timing is levelled on login.** An unknown username still runs a
`password_verify()` against a dummy hash, so "no such user" does not
return faster than "wrong password".

**All time arithmetic happens in MySQL.** Lockout expiry and reset expiry
are written and compared with `NOW()`, so PHP's timezone and the
database's cannot disagree. They always agree on a developer laptop and
sometimes do not in production, which is the worst kind of bug.

### Before going live

- [ ] `.env` exists, is not world-readable, and is not in git
- [ ] `APP_ENV=production` and `APP_DEBUG=false`
- [ ] `setup_admin.php` deleted
- [ ] HTTPS on — the session cookie only gets its `Secure` flag over HTTPS
- [ ] `storage/` writable by the web server and by nobody else
- [ ] A backup schedule for the database *and* `storage/documents`

---

## Design

The interface language is called **Ledger Depth**, and it comes from the
objects this practice actually handles: ruled ledger sheets, stamped
seals, bound files, columns of figures that have to line up.

**Depth** is built from four stacked cues rather than one blurred drop
shadow — a lit top edge, a contact shadow, a diffuse ambient shadow, and
a darker base rule. That combination is what reads as a physical object
rather than a floating rectangle. The ramp is `--lift-0` through
`--lift-4`, with `--press` as its inverse for active controls. Real CSS
3D transforms are used where an object deserves to be handled: the hero
photograph tilts toward the pointer, and the sign-in page carries a stack
of ruled sheets in perspective.

**Colour** carries meaning rather than decoration. Ink navy for
structure, a single brass accent for "this one" — the colour of a seal on
a filed return — and the ledger's own semantics for money: green for
settled, oxblood for owed, amber for pending. No status is ever signalled
by colour alone; every pill carries a label.

**Type** is Fraunces for display and IBM Plex Sans for interface. IBM
Plex Mono appears only on money and reference codes, with tabular
figures, so columns of numbers align down the page — which in an
accounting system is a functional requirement, not a stylistic one.

Motion answers actions. Nothing animates on its own, and
`prefers-reduced-motion` disables all of it.

---

## Licence

Built for Selamawit H/Mariam Accounting &amp; Financial Consulting.
