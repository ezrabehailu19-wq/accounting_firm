<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/includes.inc.php';
require_once __DIR__ . '/includes/layout.inc.php';

$admin     = require_admin();
$controler = new Controler();

$id = get_int('id');

// The notification email links by reference rather than id, because a
// reference is stable and readable; resolve it to an id here.
if ($id === 0 && get_str('ref') !== '') {
    $found = (new class extends Model {
        public function byRef(string $ref): ?array
        {
            return $this->selectOne('SELECT id FROM contact_messages WHERE reference = ?', [$ref]);
        }
    })->byRef(get_str('ref'));

    $id = $found ? (int) $found['id'] : 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard('admin_messages.php');

    $id     = post_int('id');
    $action = post_str('action');

    if ($action === 'reply') {
        $result = $controler->replyToRequest($id, post_str('reply'), $admin);
        flash($result['ok'] ? 'success' : 'error',
            $result['ok'] ? 'Reply sent and saved to the thread.' : $result['error']);
    } elseif ($action === 'status') {
        $result = $controler->updateRequestStatus($id, post_str('status'), $admin);
        flash($result['ok'] ? 'success' : 'error',
            $result['ok'] ? 'Status updated.' : $result['error']);
    } elseif ($action === 'delete') {
        $result = $controler->removeRequest($id, $admin);
        flash($result['ok'] ? 'success' : 'error',
            $result['ok'] ? 'Request deleted.' : $result['error']);
        redirect('admin_messages.php');
    }

    redirect('admin_message.php?id=' . $id);
}

$request = $id > 0 ? $controler->request($id) : null;

if ($request === null) {
    portal_head('Request not found', 'inbox');
    echo empty_state(
        'That request is not here',
        'It may have been deleted. The audit trail will show who removed it and when.',
        '<a class="btn btn-secondary" href="admin_messages.php">Back to the inbox</a>'
    );
    portal_foot();
    exit;
}

// Opening an unread request is what "read" means — no extra click.
$controler->markRequestRead($request);
if ($request['status'] === 'new') {
    $request['status'] = 'read';
}

$documents = $controler->documents(1, (int) ($request['user_id'] ?? 0));

portal_head(
    'Request ' . ($request['reference'] ?: '#' . $request['id']),
    'inbox',
    ['subtitle' => 'From ' . $request['fullname'] . ' · ' . fmt_datetime($request['submitted_at'])]
);
?>

<div class="grid-side">
    <div class="stack">
        <section class="panel">
            <div class="panel__head">
                <div>
                    <h2>Conversation</h2>
                    <p><?= status_pill($request['status'], request_status_label($request['status'])) ?></p>
                </div>
                <a class="btn btn-ghost btn-sm" href="admin_messages.php">Back to inbox</a>
            </div>

            <div class="panel__body">
                <div class="thread">
                    <article class="bubble bubble--client">
                        <p class="bubble__meta">
                            <strong><?= e($request['fullname']) ?></strong>
                            <span><?= e(fmt_datetime($request['submitted_at'])) ?></span>
                        </p>
                        <?= e_multiline($request['message']) ?>
                    </article>

                    <?php if (!empty($request['admin_reply'])): ?>
                        <article class="bubble bubble--firm">
                            <p class="bubble__meta">
                                <strong><?= e($request['replier_username'] ?: 'The firm') ?></strong>
                                <span><?= e(fmt_datetime($request['replied_at'])) ?></span>
                            </p>
                            <?= e_multiline($request['admin_reply']) ?>
                        </article>
                    <?php endif; ?>
                </div>
            </div>

            <div class="panel__foot">
                <form method="post" action="admin_message.php" class="stack">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reply">
                    <input type="hidden" name="id" value="<?= (int) $request['id'] ?>">

                    <div class="form-group">
                        <label for="reply">
                            <?= !empty($request['admin_reply']) ? 'Replace your reply' : 'Write a reply' ?>
                        </label>
                        <textarea id="reply" name="reply" rows="5" required
                                  placeholder="Answer the question, say what happens next, and give a timeline."><?= e($request['admin_reply'] ?? '') ?></textarea>
                        <span class="field-hint">
                            Sent to <?= e($request['email']) ?> and kept on this thread.
                        </span>
                    </div>

                    <div class="row row--end">
                        <button type="submit" class="btn btn-primary">Send reply</button>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <aside class="stack">
        <section class="panel">
            <div class="panel__head"><div><h2>Move this along</h2></div></div>
            <div class="panel__body">
                <form method="post" action="admin_message.php" class="stack">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="status">
                    <input type="hidden" name="id" value="<?= (int) $request['id'] ?>">

                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <?php foreach (['new', 'read', 'in_progress', 'replied', 'closed'] as $option): ?>
                                <option value="<?= e($option) ?>" <?= $request['status'] === $option ? 'selected' : '' ?>>
                                    <?= e(request_status_label($option)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-secondary btn-full">Update status</button>
                </form>
            </div>
        </section>

        <section class="panel">
            <div class="panel__head"><div><h2>Contact details</h2></div></div>
            <div class="panel__body">
                <dl class="dl">
                    <div class="dl__row"><dt>Reference</dt><dd class="num"><?= e($request['reference'] ?: '—') ?></dd></div>
                    <div class="dl__row"><dt>Name</dt><dd><?= e($request['fullname']) ?></dd></div>
                    <div class="dl__row">
                        <dt>Email</dt>
                        <dd><a href="mailto:<?= e($request['email']) ?>"><?= e($request['email']) ?></a></dd>
                    </div>
                    <div class="dl__row">
                        <dt>Phone</dt>
                        <dd><?= $request['phone'] ? '<a href="tel:' . e($request['phone']) . '">' . e($request['phone']) . '</a>' : '—' ?></dd>
                    </div>
                    <div class="dl__row"><dt>Service</dt><dd><?= e($request['service'] ?: '—') ?></dd></div>
                    <div class="dl__row">
                        <dt>Account</dt>
                        <dd>
                            <?php if (!empty($request['client_username'])): ?>
                                <a href="admin_users.php?q=<?= url_attr($request['client_username']) ?>">
                                    <?= e($request['client_username']) ?>
                                </a>
                            <?php else: ?>
                                <span class="muted">Not registered</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div class="dl__row"><dt>Received</dt><dd><?= e(fmt_datetime($request['submitted_at'])) ?></dd></div>
                </dl>
            </div>
        </section>

        <?php if (!empty($request['user_id']) && $documents['rows'] !== []): ?>
            <section class="panel">
                <div class="panel__head"><div><h2>Their documents</h2><p><?= (int) $documents['total'] ?> on file</p></div></div>
                <div class="panel__body stack-sm">
                    <?php foreach (array_slice($documents['rows'], 0, 5) as $doc): ?>
                        <div class="row row--between">
                            <span class="small"><?= e($doc['original_name']) ?></span>
                            <a class="btn btn-ghost btn-sm" href="document_download.php?id=<?= (int) $doc['id'] ?>">Download</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="panel">
            <div class="panel__head"><div><h2>Danger zone</h2></div></div>
            <div class="panel__body">
                <p class="small muted mt-0">
                    Deleting removes the message for good. The audit trail keeps a record
                    of who deleted it and when.
                </p>
                <form method="post" action="admin_message.php" class="mt-1"
                      data-confirm="Delete <?= e($request['reference'] ?: 'this request') ?> permanently?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $request['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-full">Delete this request</button>
                </form>
            </div>
        </section>
    </aside>
</div>

<?php portal_foot();
