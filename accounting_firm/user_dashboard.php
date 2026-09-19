<?php
/**
 * Client dashboard.
 *
 * The point of this page is that a client never has to phone and ask
 * "did you get my message?" — every request they have sent shows where
 * it currently sits, and anything we have replied is right there.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/includes.inc.php';
require_once __DIR__ . '/includes/layout.inc.php';

$session   = require_login();
$controler = new Controler();

// Staff have their own console; sending them here would just confuse.
if ($session['role'] === 'admin') {
    redirect('admin.php');
}

$user = $controler->user((int) $session['id']);

if ($user === null) {
    // Account deleted mid-session. Nothing to show them.
    redirect('logout.php');
}

$requests = $controler->requestsFor($user, 1);
$invoices = $controler->invoiceSummary((int) $user['id']);
$recent   = $controler->invoices(1, (int) $user['id'], '', '');
$docCount = $controler->documentCount((int) $user['id']);

$open = 0;
foreach ($requests['rows'] as $row) {
    if (!in_array($row['status'], ['replied', 'closed'], true)) {
        $open++;
    }
}

$firstName = trim((string) ($user['full_name'] ?: $user['username']));
$firstName = explode(' ', $firstName)[0];

portal_head('Welcome back, ' . $firstName, 'dashboard', [
    'subtitle' => 'Where everything stands today',
    'actions'  => '<a class="btn btn-brass btn-sm" href="contact.html">New request</a>',
]);
?>

<section class="metrics">
    <article class="metric metric--brass">
        <span class="metric__label">Open requests</span>
        <span class="metric__value"><?= (int) $open ?></span>
        <span class="metric__note"><?= (int) $requests['total'] ?> submitted in total</span>
    </article>

    <article class="metric metric--<?= (float) $invoices['outstanding'] > 0 ? 'debit' : 'credit' ?>">
        <span class="metric__label">Amount due</span>
        <span class="metric__value metric__value--money"><?= e(money($invoices['outstanding'])) ?></span>
        <span class="metric__note">
            <?= (int) $invoices['overdue_count'] > 0
                ? '<strong>' . (int) $invoices['overdue_count'] . '</strong> past due'
                : 'Nothing overdue' ?>
        </span>
    </article>

    <article class="metric metric--credit">
        <span class="metric__label">Paid to date</span>
        <span class="metric__value metric__value--money"><?= e(money($invoices['billed_paid'])) ?></span>
        <span class="metric__note">Thank you</span>
    </article>

    <article class="metric">
        <span class="metric__label">Documents</span>
        <span class="metric__value"><?= (int) $docCount ?></span>
        <span class="metric__note">Shared with the firm</span>
    </article>
</section>

<div class="grid-side">
    <section class="panel">
        <div class="panel__head">
            <div><h2>Your requests</h2><p>Live status on everything you have sent us</p></div>
            <a class="btn btn-ghost btn-sm" href="my_requests.php">See all</a>
        </div>

        <div class="panel__body">
            <?php if ($requests['rows'] === []): ?>
                <?= empty_state(
                    'No requests yet',
                    'Send us a question through the contact form and you will be able to follow its progress here.',
                    '<a class="btn btn-primary" href="contact.html">Ask us something</a>'
                ) ?>
            <?php else: ?>
                <div class="stack">
                    <?php foreach (array_slice($requests['rows'], 0, 3) as $row): ?>
                        <?php
                        $stages = [
                            'Received'    => 0.15,
                            'Opened'      => 0.40,
                            'In progress' => 0.70,
                            'Answered'    => 1.00,
                        ];
                        $progress = request_progress($row['status']);
                        ?>
                        <article class="panel" style="box-shadow: var(--lift-1)">
                            <div class="panel__head">
                                <div>
                                    <h2 style="font-size:1rem">
                                        <?= e($row['service'] ?: 'General enquiry') ?>
                                    </h2>
                                    <p class="num"><?= e($row['reference'] ?: '—') ?> ·
                                        <?= e(fmt_date($row['submitted_at'])) ?></p>
                                </div>
                                <?= status_pill($row['status'], request_status_label($row['status'])) ?>
                            </div>

                            <div class="panel__body">
                                <div class="track">
                                    <?php $step = 1; foreach ($stages as $label => $threshold): ?>
                                        <div class="track__step<?= $progress >= $threshold ? ' is-done' : '' ?>">
                                            <span class="track__dot"><?= $progress >= $threshold ? '&check;' : $step ?></span>
                                            <span class="track__label"><?= e($label) ?></span>
                                        </div>
                                    <?php $step++; endforeach; ?>
                                </div>

                                <?php if (!empty($row['admin_reply'])): ?>
                                    <div class="bubble bubble--firm">
                                        <p class="bubble__meta">
                                            <strong>Our reply</strong>
                                            <span><?= e(fmt_date($row['replied_at'])) ?></span>
                                        </p>
                                        <?= e_multiline($row['admin_reply']) ?>
                                    </div>
                                <?php else: ?>
                                    <p class="small muted">
                                        <?= $row['status'] === 'new'
                                            ? 'We have it. Someone will pick this up shortly.'
                                            : 'We are working on this. You will get an email the moment we reply.' ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <aside class="stack">
        <section class="panel">
            <div class="panel__head">
                <div><h2>Recent invoices</h2></div>
                <a class="btn btn-ghost btn-sm" href="my_invoices.php">All</a>
            </div>
            <div class="panel__body">
                <?php
                $visible = array_filter($recent['rows'], static fn(array $row): bool => $row['status'] !== 'draft');
                ?>
                <?php if ($visible === []): ?>
                    <p class="small muted mt-0">No invoices have been issued to you yet.</p>
                <?php else: ?>
                    <div class="stack-sm">
                        <?php foreach (array_slice($visible, 0, 5) as $row): ?>
                            <a class="row row--between" href="invoice_view.php?id=<?= (int) $row['id'] ?>"
                               style="text-decoration:none">
                                <span>
                                    <strong class="num small"><?= e($row['invoice_no']) ?></strong><br>
                                    <span class="small muted">Due <?= e(fmt_date($row['due_date'])) ?></span>
                                </span>
                                <span class="right">
                                    <span class="num small"><?= e(money($row['total'], $row['currency'])) ?></span><br>
                                    <?= status_pill($row['status'], invoice_status_label($row['status'])) ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="panel">
            <div class="panel__head"><div><h2>Send us paperwork</h2></div></div>
            <div class="panel__body">
                <p class="small muted mt-0">
                    Bank statements, receipts, last year's return — upload them once and
                    they stay on your record.
                </p>
                <a class="btn btn-secondary btn-full mt-1" href="documents.php">Open documents</a>
            </div>
        </section>
    </aside>
</div>

<?php portal_foot();
