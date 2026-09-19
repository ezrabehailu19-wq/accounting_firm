<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/includes.inc.php';
require_once __DIR__ . '/includes/layout.inc.php';

$admin     = require_admin();
$controler = new Controler();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard('admin_invoices.php');

    $action = post_str('action');
    $id     = post_int('id');

    if ($action === 'status') {
        $result = $controler->markInvoice($id, post_str('status'), $admin);
        flash($result['ok'] ? 'success' : 'error',
            $result['ok'] ? 'Invoice updated.' : $result['error']);
    } elseif ($action === 'delete') {
        $result = $controler->removeInvoice($id, $admin);
        flash($result['ok'] ? 'success' : 'error',
            $result['ok'] ? 'Invoice deleted.' : $result['error']);
    }

    redirect(query_with([]));
}

$status   = get_str('status');
$search   = get_str('q');
$clientId = get_int('client') ?: null;
$page     = current_page();

if ($status !== '' && !in_array($status, ['draft', 'sent', 'paid', 'overdue', 'void'], true)) {
    $status = '';
}

$summary = $controler->invoiceSummary($clientId);   // also flips stale 'sent' to 'overdue'
$result  = $controler->invoices($page, $clientId, $status, $search);

portal_head('Invoices', 'invoices', [
    'subtitle' => 'Billing, and what is still owed',
    'wide'     => true,
    'actions'  => '<a class="btn btn-brass btn-sm" href="admin_invoice_form.php">New invoice</a>',
]);
?>

<section class="metrics">
    <article class="metric metric--debit">
        <span class="metric__label">Outstanding</span>
        <span class="metric__value metric__value--money"><?= e(money($summary['outstanding'])) ?></span>
        <span class="metric__note">Sent and overdue combined</span>
    </article>
    <article class="metric metric--pending">
        <span class="metric__label">Overdue</span>
        <span class="metric__value metric__value--money"><?= e(money($summary['overdue_amount'])) ?></span>
        <span class="metric__note"><strong><?= (int) $summary['overdue_count'] ?></strong> past their due date</span>
    </article>
    <article class="metric metric--credit">
        <span class="metric__label">Collected</span>
        <span class="metric__value metric__value--money"><?= e(money($summary['billed_paid'])) ?></span>
        <span class="metric__note">Across <?= (int) $summary['count_all'] ?> invoices</span>
    </article>
    <article class="metric">
        <span class="metric__label">Drafts</span>
        <span class="metric__value"><?= (int) $summary['draft_count'] ?></span>
        <span class="metric__note">Not yet sent to anyone</span>
    </article>
</section>

<section class="panel">
    <div class="filters">
        <div class="tabs">
            <?php
            $tabs = ['' => 'All', 'draft' => 'Draft', 'sent' => 'Sent', 'overdue' => 'Overdue', 'paid' => 'Paid', 'void' => 'Void'];
            foreach ($tabs as $key => $label):
                $params = array_filter([
                    'status' => $key,
                    'q'      => $search,
                    'client' => $clientId,
                ]);
                $href = 'admin_invoices.php' . ($params ? '?' . http_build_query($params) : '');
                ?>
                <a class="tab<?= $status === $key ? ' is-active' : '' ?>" href="<?= e($href) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>

        <form method="get" action="admin_invoices.php" role="search">
            <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
            <?php if ($clientId): ?><input type="hidden" name="client" value="<?= (int) $clientId ?>"><?php endif; ?>
            <input type="search" name="q" value="<?= e($search) ?>"
                   placeholder="Invoice number or client" aria-label="Search invoices">
            <button type="submit" class="btn btn-secondary btn-sm">Search</button>
            <?php if ($search !== '' || $clientId): ?>
                <a class="btn btn-ghost btn-sm" href="admin_invoices.php">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($result['rows'] === []): ?>
        <div class="panel__body">
            <?= empty_state(
                'No invoices to show',
                'Draft one and it will appear here. Drafts stay private until you mark them sent.',
                '<a class="btn btn-primary" href="admin_invoice_form.php">Draft an invoice</a>'
            ) ?>
        </div>
    <?php else: ?>
        <div class="panel__body panel__body--flush">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Number</th>
                            <th>Client</th>
                            <th>Issued</th>
                            <th>Due</th>
                            <th class="num">Total</th>
                            <th>Status</th>
                            <th class="num">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($result['rows'] as $row): ?>
                        <tr<?= $row['status'] === 'overdue' ? ' class="row--overdue"' : '' ?>>
                            <td class="num table__primary"><?= e($row['invoice_no']) ?></td>
                            <td>
                                <span class="table__primary"><?= e($row['client_name'] ?: $row['client_username']) ?></span>
                                <?php if ($row['client_company']): ?>
                                    <span class="table__muted"><?= e($row['client_company']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="table__muted nowrap"><?= e(fmt_date($row['issue_date'])) ?></td>
                            <td class="table__muted nowrap"><?= e(fmt_date($row['due_date'])) ?></td>
                            <td class="num"><?= e(money($row['total'], $row['currency'])) ?></td>
                            <td><?= status_pill($row['status'], invoice_status_label($row['status'])) ?></td>
                            <td>
                                <div class="table__actions">
                                    <a class="btn btn-ghost btn-sm" href="invoice_view.php?id=<?= (int) $row['id'] ?>">View</a>

                                    <?php if ($row['status'] !== 'paid'): ?>
                                        <a class="btn btn-ghost btn-sm" href="admin_invoice_form.php?id=<?= (int) $row['id'] ?>">Edit</a>
                                    <?php endif; ?>

                                    <?php if ($row['status'] === 'draft'): ?>
                                        <form method="post" action="admin_invoices.php">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="status">
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <input type="hidden" name="status" value="sent">
                                            <button type="submit" class="btn btn-secondary btn-sm">Send</button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if (in_array($row['status'], ['sent', 'overdue'], true)): ?>
                                        <form method="post" action="admin_invoices.php">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="status">
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <input type="hidden" name="status" value="paid">
                                            <button type="submit" class="btn btn-success btn-sm">Mark paid</button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($row['status'] === 'paid'): ?>
                                        <form method="post" action="admin_invoices.php"
                                              data-confirm="Void <?= e($row['invoice_no']) ?>? The number stays in the sequence.">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="status">
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <input type="hidden" name="status" value="void">
                                            <button type="submit" class="btn btn-ghost btn-sm">Void</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" action="admin_invoices.php"
                                              data-confirm="Delete <?= e($row['invoice_no']) ?>?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?= render_pagination($result['total'], $page, 'admin_invoices.php') ?>
    <?php endif; ?>
</section>

<?php portal_foot();
