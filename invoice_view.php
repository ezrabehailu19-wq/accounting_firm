<?php
/**
 * A single invoice, laid out as a document.
 *
 * Shared by staff and clients — the access check below is what keeps
 * one client from reading another's billing by changing the id in the
 * URL. Print styles turn this into a clean A4 page with no chrome, so
 * there is no separate PDF pipeline to maintain.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/includes.inc.php';
require_once __DIR__ . '/includes/layout.inc.php';

$user      = require_login();
$controler = new Controler();
$isAdmin   = $user['role'] === 'admin';

$invoice = $controler->invoice(get_int('id'));

if ($invoice === null) {
    portal_head('Invoice not found', 'invoices');
    echo empty_state('That invoice is not here', 'It may have been deleted.',
        '<a class="btn btn-secondary" href="' . ($isAdmin ? 'admin_invoices.php' : 'my_invoices.php') . '">Back to invoices</a>');
    portal_foot();
    exit;
}

if (!$isAdmin && (int) $invoice['client_id'] !== (int) $user['id']) {
    http_response_code(403);
    portal_head('Not your invoice', 'invoices');
    echo empty_state('You do not have access to that invoice', 'Only the client it was issued to can open it.',
        '<a class="btn btn-secondary" href="my_invoices.php">Your invoices</a>');
    portal_foot();
    exit;
}

// A draft has not been sent to anyone yet, so it should not be visible
// to the client it names.
if (!$isAdmin && $invoice['status'] === 'draft') {
    http_response_code(403);
    portal_head('Not available', 'invoices');
    echo empty_state('This invoice has not been issued yet', 'It will appear here once the firm sends it.',
        '<a class="btn btn-secondary" href="my_invoices.php">Your invoices</a>');
    portal_foot();
    exit;
}

$items = $controler->invoiceItems((int) $invoice['id']);

$actions = '<button type="button" class="btn btn-secondary btn-sm no-print" data-print>Print</button>';
if ($isAdmin && $invoice['status'] !== 'paid') {
    $actions .= ' <a class="btn btn-ghost btn-sm no-print" href="admin_invoice_form.php?id=' . (int) $invoice['id'] . '">Edit</a>';
}

portal_head(
    'Invoice ' . $invoice['invoice_no'],
    'invoices',
    [
        'subtitle' => invoice_status_label($invoice['status']) . ' · due ' . fmt_date($invoice['due_date']),
        'actions'  => $actions,
    ]
);
?>

<article class="doc">
    <header class="doc__head">
        <div>
            <h2 class="doc__no">Invoice <?= e($invoice['invoice_no']) ?></h2>
            <p class="small muted">
                Issued <?= e(fmt_date($invoice['issue_date'])) ?> ·
                Due <?= e(fmt_date($invoice['due_date'])) ?>
            </p>
            <div class="mt-1">
                <?php if ($invoice['status'] === 'paid'): ?>
                    <span class="doc__seal">PAID</span>
                <?php elseif ($invoice['status'] === 'void'): ?>
                    <span class="doc__seal doc__seal--void">VOID</span>
                <?php elseif ($invoice['status'] === 'overdue'): ?>
                    <span class="doc__seal doc__seal--overdue">OVERDUE</span>
                <?php else: ?>
                    <?= status_pill($invoice['status'], invoice_status_label($invoice['status'])) ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="right">
            <strong><?= e(APP_NAME) ?></strong>
            <p class="doc__firm">
                <?= e(APP_TAGLINE) ?><br>
                <?= e(FIRM_ADDRESS) ?><br>
                <?= e(FIRM_PHONE) ?><br>
                <?= e(FIRM_EMAIL) ?>
                <?php if (FIRM_TIN !== ''): ?><br>TIN <?= e(FIRM_TIN) ?><?php endif; ?>
            </p>
        </div>
    </header>

    <section class="doc__parties">
        <div class="doc__party">
            <h3>Billed to</h3>
            <p>
                <strong><?= e($invoice['client_name'] ?: $invoice['client_username']) ?></strong><br>
                <?php if ($invoice['client_company']): ?><?= e($invoice['client_company']) ?><br><?php endif; ?>
                <?= e($invoice['client_email']) ?>
                <?php if ($invoice['client_phone']): ?><br><?= e($invoice['client_phone']) ?><?php endif; ?>
            </p>
        </div>
        <div class="doc__party">
            <h3>Amount due</h3>
            <p>
                <strong class="num"><?= e(money($invoice['total'], $invoice['currency'])) ?></strong><br>
                <?= $invoice['status'] === 'paid'
                    ? 'Settled ' . e(fmt_date($invoice['paid_at']))
                    : 'Payable by ' . e(fmt_date($invoice['due_date'])) ?>
            </p>
        </div>
    </section>

    <table class="doc__table">
        <thead>
            <tr>
                <th>Description</th>
                <th class="num">Qty</th>
                <th class="num">Unit price</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?= e($item['description']) ?></td>
                <td class="num"><?= e(rtrim(rtrim(number_format((float) $item['quantity'], 2), '0'), '.')) ?></td>
                <td class="num"><?= e(number_format((float) $item['unit_price'], 2)) ?></td>
                <td class="num"><?= e(number_format((float) $item['line_total'], 2)) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <dl class="dl doc__totals">
        <div class="dl__row">
            <dt>Subtotal</dt>
            <dd class="num"><?= e(money($invoice['subtotal'], $invoice['currency'])) ?></dd>
        </div>
        <div class="dl__row">
            <dt>VAT <?= e(rtrim(rtrim(number_format((float) $invoice['tax_rate'], 2), '0'), '.')) ?>%</dt>
            <dd class="num"><?= e(money($invoice['tax_amount'], $invoice['currency'])) ?></dd>
        </div>
        <div class="dl__row dl__row--grand">
            <dt>Total</dt>
            <dd class="num"><?= e(money($invoice['total'], $invoice['currency'])) ?></dd>
        </div>
    </dl>

    <?php if ($invoice['notes']): ?>
        <div class="doc__notes"><?= e_multiline($invoice['notes']) ?></div>
    <?php endif; ?>
</article>

<?php portal_foot();
