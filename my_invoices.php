<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/includes.inc.php';
require_once __DIR__ . '/includes/layout.inc.php';

$session   = require_login();
$controler = new Controler();

if ($session['role'] === 'admin') {
    redirect('admin_invoices.php');
}

$page    = current_page();
$status  = get_str('status');

// A draft has not been issued yet, so it is never a client's business.
if (!in_array($status, ['sent', 'paid', 'overdue'], true)) {
    $status = '';
}

$summary = $controler->invoiceSummary((int) $session['id']);
$result  = $controler->invoices($page, (int) $session['id'], $status);

portal_head('Invoices', 'invoices', ['subtitle' => 'What has been billed and what is outstanding']);
?>

<section class="metrics">
    <article class="metric metric--<?= (float) $summary['outstanding'] > 0 ? 'debit' : 'credit' ?>">
        <span class="metric__label">Outstanding</span>
        <span class="metric__value metric__value--money"><?= e(money($summary['outstanding'])) ?></span>
        <span class="metric__note">
            <?= (int) $summary['overdue_count'] > 0
                ? '<strong>' . (int) $summary['overdue_count'] . '</strong> past due'
                : 'Nothing overdue' ?>
        </span>
    </article>
    <article class="metric metric--credit">
        <span class="metric__label">Paid</span>
        <span class="metric__value metric__value--money"><?= e(money($summary['billed_paid'])) ?></span>
        <span class="metric__note">Settled in full</span>
    </article>
</section>

<section class="panel">
    <div class="filters">
        <div class="tabs">
            <a class="tab<?= $status === '' ? ' is-active' : '' ?>" href="my_invoices.php">All</a>
            <a class="tab<?= $status === 'sent' ? ' is-active' : '' ?>" href="my_invoices.php?status=sent">Awaiting payment</a>
            <a class="tab<?= $status === 'overdue' ? ' is-active' : '' ?>" href="my_invoices.php?status=overdue">Overdue</a>
            <a class="tab<?= $status === 'paid' ? ' is-active' : '' ?>" href="my_invoices.php?status=paid">Paid</a>
        </div>
    </div>

    <?php
    // Drafts are filtered out here rather than in SQL so that the status
    // tabs above stay simple; the volume per client is tiny.
    $rows = array_filter($result['rows'], static fn(array $row): bool => $row['status'] !== 'draft');
    ?>

    <?php if ($rows === []): ?>
        <div class="panel__body">
            <?= empty_state('No invoices to show', 'Anything the firm issues to you will be listed here.') ?>
        </div>
    <?php else: ?>
        <div class="panel__body panel__body--flush">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Number</th>
                            <th>Issued</th>
                            <th>Due</th>
                            <th class="num">Total</th>
                            <th>Status</th>
                            <th class="num"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr<?= $row['status'] === 'overdue' ? ' class="row--overdue"' : '' ?>>
                            <td class="num table__primary"><?= e($row['invoice_no']) ?></td>
                            <td class="table__muted nowrap"><?= e(fmt_date($row['issue_date'])) ?></td>
                            <td class="table__muted nowrap"><?= e(fmt_date($row['due_date'])) ?></td>
                            <td class="num"><?= e(money($row['total'], $row['currency'])) ?></td>
                            <td><?= status_pill($row['status'], invoice_status_label($row['status'])) ?></td>
                            <td class="table__actions">
                                <a class="btn btn-secondary btn-sm" href="invoice_view.php?id=<?= (int) $row['id'] ?>">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?= render_pagination($result['total'], $page, 'my_invoices.php') ?>
    <?php endif; ?>
</section>

<?php portal_foot();
