<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/includes.inc.php';
require_once __DIR__ . '/includes/layout.inc.php';

$session   = require_login();
$controler = new Controler();

if ($session['role'] === 'admin') {
    redirect('admin_messages.php');
}

$user = $controler->user((int) $session['id']);
if ($user === null) {
    redirect('logout.php');
}

$page   = current_page();
$result = $controler->requestsFor($user, $page);

portal_head('My requests', 'requests', [
    'subtitle' => 'Everything you have sent us, and where it got to',
    'actions'  => '<a class="btn btn-brass btn-sm" href="contact.html">New request</a>',
]);
?>

<?php if ($result['rows'] === []): ?>
    <section class="panel">
        <div class="panel__body">
            <?= empty_state(
                'You have not sent anything yet',
                'Requests you submit through the contact form show up here with a reference you can quote.',
                '<a class="btn btn-primary" href="contact.html">Send your first request</a>'
            ) ?>
        </div>
    </section>
<?php else: ?>
    <div class="stack">
        <?php foreach ($result['rows'] as $row): ?>
            <?php
            $stages = [
                'Received'    => 0.15,
                'Opened'      => 0.40,
                'In progress' => 0.70,
                'Answered'    => 1.00,
            ];
            $progress = request_progress($row['status']);
            ?>
            <article class="panel">
                <div class="panel__head">
                    <div>
                        <h2><?= e($row['service'] ?: 'General enquiry') ?></h2>
                        <p><span class="num"><?= e($row['reference'] ?: '—') ?></span>
                           · sent <?= e(fmt_datetime($row['submitted_at'])) ?></p>
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

                    <div class="thread mt-2">
                        <article class="bubble bubble--client">
                            <p class="bubble__meta">
                                <strong>You</strong>
                                <span><?= e(fmt_datetime($row['submitted_at'])) ?></span>
                            </p>
                            <?= e_multiline($row['message']) ?>
                        </article>

                        <?php if (!empty($row['admin_reply'])): ?>
                            <article class="bubble bubble--firm">
                                <p class="bubble__meta">
                                    <strong><?= e(APP_NAME) ?></strong>
                                    <span><?= e(fmt_datetime($row['replied_at'])) ?></span>
                                </p>
                                <?= e_multiline($row['admin_reply']) ?>
                            </article>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <?= render_pagination($result['total'], $page, 'my_requests.php') ?>
<?php endif; ?>

<?php portal_foot();
