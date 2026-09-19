<?php
/**
 * Draft or edit an invoice.
 *
 * The line editor is progressive: rows are plain inputs that post as
 * arrays, so the form still works with JavaScript off. The script only
 * adds convenience — an extra row, a live total — on top of markup that
 * already functions. Totals are recalculated server-side regardless of
 * what the browser sends, because the numbers in a POST are only ever a
 * suggestion.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/includes.inc.php';
require_once __DIR__ . '/includes/layout.inc.php';

$admin     = require_admin();
$controler = new Controler();

$invoiceId = get_int('id') ?: null;
$invoice   = $invoiceId !== null ? $controler->invoice($invoiceId) : null;

if ($invoiceId !== null && $invoice === null) {
    portal_head('Invoice not found', 'invoices');
    echo empty_state('That invoice is gone', 'It may have been deleted.',
        '<a class="btn btn-secondary" href="admin_invoices.php">Back to invoices</a>');
    portal_foot();
    exit;
}

if ($invoice !== null && $invoice['status'] === 'paid') {
    flash('error', 'A paid invoice cannot be edited. Void it first if something is wrong.');
    redirect('invoice_view.php?id=' . (int) $invoice['id']);
}

$clients = $controler->clientOptions();
$error   = '';

// Form state: the posted values on a failed submit, the stored values
// when editing, sensible defaults when starting fresh.
$form = [
    'client_id'  => $invoice['client_id'] ?? 0,
    'issue_date' => $invoice['issue_date'] ?? date('Y-m-d'),
    'due_date'   => $invoice['due_date'] ?? date('Y-m-d', strtotime('+14 days')),
    'tax_rate'   => $invoice['tax_rate'] ?? VAT_RATE,
    'status'     => $invoice['status'] ?? 'draft',
    'notes'      => $invoice['notes'] ?? '',
];

$items = $invoiceId !== null ? $controler->invoiceItems($invoiceId) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard('admin_invoice_form.php');

    $form = [
        'client_id'  => post_int('client_id'),
        'issue_date' => post_str('issue_date'),
        'due_date'   => post_str('due_date'),
        'tax_rate'   => (float) post_str('tax_rate'),
        'status'     => post_str('status'),
        'notes'      => post_str('notes'),
    ];

    // Parallel arrays from the form, zipped back into rows.
    $descriptions = $_POST['description'] ?? [];
    $quantities   = $_POST['quantity'] ?? [];
    $prices       = $_POST['unit_price'] ?? [];
    $posted       = [];

    if (is_array($descriptions)) {
        foreach ($descriptions as $index => $description) {
            $posted[] = [
                'description' => (string) $description,
                'quantity'    => (float) ($quantities[$index] ?? 0),
                'unit_price'  => (float) ($prices[$index] ?? 0),
            ];
        }
    }

    $result = $controler->saveInvoice($form, $posted, $admin, $invoiceId);

    if ($result['ok']) {
        flash('success', $invoiceId === null ? 'Invoice created.' : 'Invoice updated.');
        redirect('invoice_view.php?id=' . (int) $result['id']);
    }

    $error = $result['error'];

    // Keep what they typed so a validation failure doesn't wipe the form.
    $items = array_map(static function (array $row): array {
        return [
            'description' => $row['description'],
            'quantity'    => $row['quantity'],
            'unit_price'  => $row['unit_price'],
        ];
    }, $posted);
}

if ($items === []) {
    $items = [['description' => '', 'quantity' => 1, 'unit_price' => 0]];
}

portal_head(
    $invoiceId === null ? 'New invoice' : 'Edit ' . $invoice['invoice_no'],
    'invoices',
    ['subtitle' => $invoiceId === null
        ? 'It stays a draft until you send it'
        : 'Changes are recorded in the audit trail']
);
?>

<?php if ($error !== ''): ?>
    <div class="notice notice--error"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" action="admin_invoice_form.php<?= $invoiceId !== null ? '?id=' . (int) $invoiceId : '' ?>"
      class="stack" id="invoiceForm">
    <?= csrf_field() ?>

    <div class="grid-side">
        <section class="panel">
            <div class="panel__head"><div><h2>Line items</h2><p>Blank rows are ignored</p></div></div>
            <div class="panel__body">
                <div class="table-wrap">
                    <table class="lines" id="lineItems">
                        <thead>
                            <tr>
                                <th style="width:52%">Description</th>
                                <th class="num" style="width:14%">Qty</th>
                                <th class="num" style="width:22%">Unit price</th>
                                <th style="width:12%"><span class="visually-hidden">Remove</span></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr class="lines__row">
                                <td>
                                    <input type="text" name="description[]" maxlength="255"
                                           value="<?= e($item['description']) ?>"
                                           placeholder="Monthly bookkeeping — March">
                                </td>
                                <td class="num">
                                    <input type="number" name="quantity[]" step="0.01" min="0"
                                           value="<?= e((string) $item['quantity']) ?>">
                                </td>
                                <td class="num">
                                    <input type="number" name="unit_price[]" step="0.01" min="0"
                                           value="<?= e((string) $item['unit_price']) ?>">
                                </td>
                                <td>
                                    <button type="button" class="lines__remove" aria-label="Remove this line">&times;</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="row mt-1">
                    <button type="button" class="btn btn-secondary btn-sm" id="addLine">Add a line</button>
                </div>
            </div>

            <div class="panel__foot">
                <dl class="dl doc__totals" id="liveTotals">
                    <div class="dl__row"><dt>Subtotal</dt><dd class="num" data-total="subtotal">—</dd></div>
                    <div class="dl__row">
                        <dt>VAT <span data-total="rate"><?= e((string) $form['tax_rate']) ?></span>%</dt>
                        <dd class="num" data-total="tax">—</dd>
                    </div>
                    <div class="dl__row dl__row--grand">
                        <dt>Total</dt><dd class="num" data-total="grand">—</dd>
                    </div>
                </dl>
                <p class="small muted">Recalculated on the server when you save.</p>
            </div>
        </section>

        <aside class="stack">
            <section class="panel">
                <div class="panel__head"><div><h2>Details</h2></div></div>
                <div class="panel__body">
                    <div class="form-group">
                        <label for="client_id">Client</label>
                        <select id="client_id" name="client_id" required>
                            <option value="">Choose a client…</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= (int) $client['id'] ?>"
                                    <?= (int) $form['client_id'] === (int) $client['id'] ? 'selected' : '' ?>>
                                    <?= e($client['full_name'] ?: $client['username']) ?><?= $client['company'] ? ' — ' . e($client['company']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="issue_date">Issue date</label>
                            <input type="date" id="issue_date" name="issue_date" required
                                   value="<?= e($form['issue_date']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="due_date">Due date</label>
                            <input type="date" id="due_date" name="due_date" required
                                   value="<?= e($form['due_date']) ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="tax_rate">VAT rate (%)</label>
                            <input type="number" id="tax_rate" name="tax_rate" step="0.01" min="0" max="100"
                                   value="<?= e((string) $form['tax_rate']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <?php
                                // 'overdue' is set by the system, not chosen here — but if
                                // this invoice is already overdue it has to stay in the list,
                                // otherwise saving an edit would quietly knock it back to
                                // draft and the client would stop being chased for payment.
                                $statusOptions = ['draft', 'sent', 'void'];
                                if (!in_array($form['status'], $statusOptions, true)) {
                                    $statusOptions[] = $form['status'];
                                }
                                foreach ($statusOptions as $option): ?>
                                    <option value="<?= e($option) ?>" <?= $form['status'] === $option ? 'selected' : '' ?>>
                                        <?= e(invoice_status_label($option)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="notes">Notes on the invoice</label>
                        <textarea id="notes" name="notes" rows="4"
                                  placeholder="Payment terms, bank details, thank you."><?= e($form['notes']) ?></textarea>
                    </div>
                </div>
                <div class="panel__foot row row--end">
                    <a class="btn btn-ghost" href="admin_invoices.php">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <?= $invoiceId === null ? 'Create invoice' : 'Save changes' ?>
                    </button>
                </div>
            </section>
        </aside>
    </div>
</form>

<?php portal_foot();
