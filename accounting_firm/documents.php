<?php
/**
 * Documents.
 *
 * One page, two audiences. Staff see every file and choose whose record
 * it belongs to; a client sees only their own. The ownership check is
 * done here rather than trusting a hidden field, because a hidden field
 * is just a value the browser sends and anyone can change it.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/includes.inc.php';
require_once __DIR__ . '/includes/layout.inc.php';

$user      = require_login();
$controler = new Controler();
$isAdmin   = $user['role'] === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard('documents.php');

    if (post_str('action') === 'upload') {
        // A client can only ever file something against their own record.
        $ownerId = $isAdmin ? post_int('owner_id') : (int) $user['id'];

        if ($ownerId <= 0) {
            flash('error', 'Choose which client this file belongs to.');
        } else {
            $result = $controler->storeUpload(
                $_FILES['document'] ?? [],
                [
                    'owner_id'  => $ownerId,
                    'category'  => post_str('category'),
                    'note'      => post_str('note'),
                    'direction' => $isAdmin ? 'from_firm' : 'from_client',
                ],
                $user
            );

            flash($result['ok'] ? 'success' : 'error',
                $result['ok'] ? 'File uploaded.' : $result['error']);
        }
    } elseif (post_str('action') === 'delete') {
        $doc = $controler->document(post_int('id'));

        // Clients may remove their own upload; only staff may remove
        // anything the firm issued.
        $mayDelete = $doc !== null
            && ($isAdmin || ((int) $doc['owner_id'] === (int) $user['id'] && $doc['direction'] === 'from_client'));

        if (!$mayDelete) {
            flash('error', 'You cannot delete that file.');
        } else {
            $result = $controler->removeDocument((int) $doc['id'], $user);
            flash($result['ok'] ? 'success' : 'error',
                $result['ok'] ? 'File deleted.' : $result['error']);
        }
    }

    redirect(query_with([]));
}

$page    = current_page();
$search  = get_str('q');
$ownerId = $isAdmin ? (get_int('owner') ?: null) : (int) $user['id'];

$result  = $controler->documents($page, $ownerId, $search);
$clients = $isAdmin ? $controler->clientOptions() : [];

$maxMb = (int) (MAX_UPLOAD_BYTES / 1048576);

portal_head('Documents', 'documents', [
    'subtitle' => $isAdmin
        ? 'Everything clients have sent in and everything the firm has returned'
        : 'Share paperwork with us and pick up what we have filed for you',
    'wide' => true,
]);
?>

<div class="grid-side">
    <section class="panel">
        <div class="panel__head">
            <div>
                <h2><?= $isAdmin ? 'All documents' : 'Your documents' ?></h2>
                <p><?= (int) $result['total'] ?> file<?= $result['total'] === 1 ? '' : 's' ?> on record</p>
            </div>
        </div>

        <div class="filters">
            <form method="get" action="documents.php" role="search">
                <?php if ($isAdmin && $ownerId): ?>
                    <input type="hidden" name="owner" value="<?= (int) $ownerId ?>">
                <?php endif; ?>
                <input type="search" name="q" value="<?= e($search) ?>"
                       placeholder="File name, category or note" aria-label="Search documents">
                <button type="submit" class="btn btn-secondary btn-sm">Search</button>
                <?php if ($search !== '' || ($isAdmin && $ownerId)): ?>
                    <a class="btn btn-ghost btn-sm" href="documents.php">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($result['rows'] === []): ?>
            <div class="panel__body">
                <?= empty_state(
                    'No files here yet',
                    $isAdmin
                        ? 'Uploads from clients and anything you file back to them will be listed here.'
                        : 'Upload your bank statements, receipts or last year\'s returns to get started.'
                ) ?>
            </div>
        <?php else: ?>
            <div class="panel__body panel__body--flush">
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>File</th>
                                <?php if ($isAdmin): ?><th>Client</th><?php endif; ?>
                                <th>Category</th>
                                <th>Direction</th>
                                <th class="num">Size</th>
                                <th>Uploaded</th>
                                <th class="num">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($result['rows'] as $doc): ?>
                            <tr>
                                <td>
                                    <span class="table__primary"><?= e($doc['original_name']) ?></span>
                                    <?php if ($doc['note']): ?>
                                        <span class="table__muted"><?= e($doc['note']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($isAdmin): ?>
                                    <td><?= e($doc['owner_name'] ?: $doc['owner_username'] ?: '—') ?></td>
                                <?php endif; ?>
                                <td><?= e($doc['category'] ?: file_glyph($doc['original_name'])) ?></td>
                                <td>
                                    <?= status_pill(
                                        $doc['direction'],
                                        $doc['direction'] === 'from_firm' ? 'From us' : 'From client'
                                    ) ?>
                                </td>
                                <td class="num table__muted"><?= e(human_size($doc['size_bytes'])) ?></td>
                                <td class="table__muted nowrap"><?= e(fmt_date($doc['uploaded_at'])) ?></td>
                                <td>
                                    <div class="table__actions">
                                        <a class="btn btn-secondary btn-sm"
                                           href="document_download.php?id=<?= (int) $doc['id'] ?>">Download</a>

                                        <?php
                                        $canDelete = $isAdmin
                                            || ((int) $doc['owner_id'] === (int) $user['id'] && $doc['direction'] === 'from_client');
                                        ?>
                                        <?php if ($canDelete): ?>
                                            <form method="post" action="documents.php"
                                                  data-confirm="Delete <?= e($doc['original_name']) ?>? This cannot be undone.">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int) $doc['id'] ?>">
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

            <?= render_pagination($result['total'], $page, 'documents.php') ?>
        <?php endif; ?>
    </section>

    <aside>
        <section class="panel">
            <div class="panel__head">
                <div>
                    <h2>Upload a file</h2>
                    <p>Up to <?= $maxMb ?> MB</p>
                </div>
            </div>
            <div class="panel__body">
                <form method="post" action="documents.php" enctype="multipart/form-data" class="stack" id="uploadForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="upload">

                    <?php if ($isAdmin): ?>
                        <div class="form-group">
                            <label for="owner_id">File against</label>
                            <select id="owner_id" name="owner_id" required>
                                <option value="">Choose a client…</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?= (int) $client['id'] ?>"
                                        <?= $ownerId === (int) $client['id'] ? 'selected' : '' ?>>
                                        <?= e($client['full_name'] ?: $client['username']) ?><?= $client['company'] ? ' — ' . e($client['company']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <!-- The input is visually hidden but still in the layout and
                         still reachable by keyboard through its label. It is
                         deliberately NOT marked `required`: Chrome refuses to
                         submit a form containing an invalid control it cannot
                         scroll into view, and throws "not focusable" instead of
                         showing a message. The server already rejects an empty
                         upload with a clear error, which is the check that
                         counts anyway. -->
                    <label class="drop" for="document" id="dropZone">
                        <strong id="dropName">Choose a file</strong>
                        <small><?= e(implode(', ', array_keys(ALLOWED_UPLOAD_TYPES))) ?></small>
                        <input type="file" id="document" name="document" class="visually-hidden"
                               accept=".<?= e(implode(',.', array_keys(ALLOWED_UPLOAD_TYPES))) ?>">
                    </label>

                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category">
                            <option value="">Not specified</option>
                            <?php foreach ([
                                'Bank statement', 'Receipts', 'Payroll', 'Tax return',
                                'VAT return', 'Financial statements', 'Contract', 'Other',
                            ] as $option): ?>
                                <option value="<?= e($option) ?>"><?= e($option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="note">Note <span class="muted small">(optional)</span></label>
                        <input type="text" id="note" name="note" maxlength="255"
                               placeholder="e.g. January to March, CBE account">
                    </div>

                    <button type="submit" class="btn btn-primary btn-full">Upload</button>
                </form>
            </div>
        </section>

        <section class="panel mt-1">
            <div class="panel__head"><div><h2>How files are stored</h2></div></div>
            <div class="panel__body">
                <p class="small muted mt-0">
                    Uploads are kept outside the public web folder under a randomised
                    name, and every download is checked against your account first.
                    Nobody can reach a file by guessing a URL.
                </p>
            </div>
        </section>
    </aside>
</div>

<?php portal_foot();
