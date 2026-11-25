<?php
declare(strict_types=1);

$uid = $user['id'];

// Open one notification: mark it read, then go to its page.
if (g('open') !== '') {
    $openId = (int)g('open');
    $target = null;
    db_mutate('notifications', function (array &$rows) use ($openId, $uid, &$target) {
        foreach ($rows as &$r) {
            if ($r['id'] === $openId && $r['user_id'] === $uid) {
                $r['read'] = true;
                $target = $r['link'];
            }
        }
    });
    header('Location: ' . ($target && str_starts_with($target, 'index.php?') ? $target : url('notifications')));
    exit;
}

if (is_post()) {
    csrf_verify();
    if (p('action') === 'read_all') {
        db_mutate('notifications', function (array &$rows) use ($uid) {
            foreach ($rows as &$r) {
                if ($r['user_id'] === $uid) $r['read'] = true;
            }
        });
        flash('success', 'All notifications marked as read.');
    }
    redirect('notifications');
}

$mine = array_values(array_filter(db_read('notifications'), fn($n) => $n['user_id'] === $uid));
usort($mine, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
$unread = count(array_filter($mine, fn($n) => !$n['read']));
[$rows, $meta] = paginate($mine, 15);

$actions = $unread ? '<form method="post">' . csrf_field() . '<input type="hidden" name="action" value="read_all"><button type="submit" class="btn btn-outline">Mark all as read</button></form>' : '';
layout_start('Notifications', $unread ? $unread . ' unread' : 'You are all caught up.', $actions);
?>
<section class="card">
  <?php if ($rows): ?>
    <ul class="notes">
      <?php foreach ($rows as $n): ?>
        <li class="<?= $n['read'] ? '' : 'is-unread' ?>">
          <a href="<?= e(url('notifications', ['open' => $n['id']])) ?>">
            <span class="note-dot" aria-hidden="true"></span>
            <span class="note-body"><span><?= e($n['message']) ?></span><small class="muted"><?= e(fmt_datetime($n['created_at'])) ?><?= $n['read'] ? '' : ' · New' ?></small></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
    <?= pager($meta) ?>
  <?php else: ?>
    <?= empty_state('No notifications', 'Updates about your reports will appear here.') ?>
  <?php endif; ?>
</section>
<?php layout_end();
