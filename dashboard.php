<?php
declare(strict_types=1);

$role = $user['role'];
$unread = $role === 'admin' ? 0 : unread_count($user['id']);

if ($role === 'admin') {
    $users = db_read('users');
    $active = array_filter($users, fn($u) => $u['active']);
    $count = fn(array $list, array $roles) => count(array_filter($list, fn($u) => in_array($u['role'], $roles, true)));
    $recent = $users;
    usort($recent, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
    $recent = array_slice($recent, 0, 5);
    layout_start('Dashboard', 'System overview for ' . $user['name'] . '.',
        '<a class="btn btn-primary" href="' . e(url('user_form')) . '">' . icon('plus') . 'Add user</a>');
    ?>
    <section class="stats">
      <?= stat_card('Active users', count($active), '', '', 'users', pct(count($active), count($users))) ?>
      <?= stat_card('Students', $count($active, ['student']), '', '', 'user', pct($count($active, ['student']), count($active)), 'c-lime') ?>
      <?= stat_card('Guidance and deans', $count($active, ['guidance', 'dean']), '', '', 'shield', pct($count($active, ['guidance', 'dean']), count($active)), 'c-teal') ?>
      <?= stat_card('Deactivated', count($users) - count($active), 'Cannot sign in', '', 'alert', pct(count($users) - count($active), count($users)), 'c-amber') ?>
    </section>
    <div class="split">
    <section class="card chart-card" style="order:2">
      <div class="card-head"><h2>Active users by role</h2></div>
      <?= donut_chart([
          ['Students', $count($active, ['student']), '#16a34a'],
          ['Guidance', $count($active, ['guidance']), '#4ade80'],
          ['Deans / Chairs', $count($active, ['dean']), '#0f9b8e'],
          ['Administrators', $count($active, ['admin']), '#c5e063'],
      ]) ?>
    </section>
    <section class="card" style="order:1">
      <div class="card-head"><h2>Recently added users</h2><a class="link" href="<?= e(url('users')) ?>">View all users</a></div>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Name</th><th>Role</th><th>Added</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $u): ?>
          <tr><td><strong><?= e($u['name']) ?></strong><br><small class="muted"><?= e($u['email']) ?></small></td>
              <td><?= e(ROLE_LABELS[$u['role']]) ?></td><td><?= e(fmt_date($u['created_at'])) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </section>
    </div>
    <?php
    layout_end();
    return;
}

$cases = cases_for_user($user);
$by = fn(string $s) => count(array_filter($cases, fn($c) => $c['status'] === $s));
$newest = fn(array $rows) => array_slice(array_reverse($rows), 0, 5);

if ($role === 'student') {
    layout_start('Dashboard', 'Welcome, ' . $user['name'] . '.',
        '<a class="btn btn-primary" href="' . e(url('report_new')) . '">' . icon('plus') . 'New report</a>');
    if ($unread) {
        echo '<div class="alert alert-info">' . icon('bell') . '<span>You have ' . $unread . ' unread notification' . ($unread > 1 ? 's' : '') . '.</span><a class="link" href="' . e(url('notifications')) . '">View</a></div>';
    }
    ?>
    <section class="stats">
      <?= stat_card('Reports submitted', count($cases), '', '', 'file') ?>
      <?= stat_card('Pending', $by('pending'), 'Waiting for Guidance', '', 'alert', pct($by('pending'), count($cases)), 'c-amber') ?>
      <?= stat_card('Under review', $by('review'), '', '', 'search', pct($by('review'), count($cases)), 'c-teal') ?>
      <?= stat_card('Resolved', $by('resolved'), '', '', 'check', pct($by('resolved'), count($cases))) ?>
    </section>
    <div class="split">
      <section class="card chart-card">
        <div class="card-head"><h2>Reports per month</h2><span class="chart-sub">Last 6 months</span></div>
        <?= month_bars($cases) ?>
      </section>
      <section class="card chart-card">
        <div class="card-head"><h2>Status overview</h2></div>
        <?= donut_chart([['Pending', $by('pending'), '#f2c14e'], ['Under review', $by('review'), '#0f9b8e'], ['Resolved', $by('resolved'), '#16a34a']]) ?>
      </section>
    </div>
    
    <section class="card">
      <div class="card-head"><h2>Recent reports</h2><?php if ($cases): ?><a class="link" href="<?= e(url('cases')) ?>">View all</a><?php endif; ?></div>
      <?= $cases ? cases_table($newest($cases), $role)
                 : empty_state('No reports yet', 'When you submit a violation report, you can follow its progress here.',
                     '<a class="btn btn-primary" href="' . e(url('report_new')) . '">Submit a report</a>') ?>
    </section>
    <?php
    layout_end();
    return;
}

if ($role === 'guidance') {
    $pending = array_values(array_filter($cases, fn($c) => $c['status'] === 'pending'));
    usort($pending, fn($a, $b) => strcmp($a['created_at'], $b['created_at']));
    layout_start('Dashboard', 'Welcome, ' . $user['name'] . '.');
    if ($pending) {
        echo '<div class="alert alert-warn">' . icon('alert') . '<span><strong>' . count($pending) . ' report' . (count($pending) > 1 ? 's are' : ' is')
            . ' waiting for review.</strong></span><a class="link" href="' . e(url('cases', ['status' => 'pending'])) . '">Review now</a></div>';
    }
    ?>
    <section class="stats">
      <?= stat_card('Pending', $by('pending'), 'Needs action', $by('pending') ? 'stat-alert' : '', 'alert', pct($by('pending'), count($cases)), 'c-amber') ?>
      <?= stat_card('Under review', $by('review'), '', '', 'search', pct($by('review'), count($cases)), 'c-teal') ?>
      <?= stat_card('Resolved', $by('resolved'), '', '', 'check', pct($by('resolved'), count($cases))) ?>
      <?= stat_card('All reports', count($cases), '', '', 'file', null, 'c-lime') ?>
    </section>
    <div class="split">
      <section class="card chart-card">
        <div class="card-head"><h2>Reports per month</h2><span class="chart-sub">Last 6 months</span></div>
        <?= month_bars($cases) ?>
      </section>
      <section class="card chart-card">
        <div class="card-head"><h2>Status overview</h2></div>
        <?= donut_chart([['Pending', $by('pending'), '#f2c14e'], ['Under review', $by('review'), '#0f9b8e'], ['Resolved', $by('resolved'), '#16a34a']]) ?>
      </section>
    </div>
    
    <section class="card">
      <div class="card-head"><h2>Waiting for review</h2><a class="link" href="<?= e(url('cases')) ?>">View all reports</a></div>
      <?= $pending ? cases_table(array_slice($pending, 0, 5), $role)
                   : empty_state('All caught up', 'There are no pending reports right now.') ?>
    </section>
    <?php
    layout_end();
    return;
}

// Dean / Program Chair
$needs = array_values(array_filter($cases, fn($c) => !in_array($user['id'], array_column($c['ack'] ?? [], 'user_id'), true)));
layout_start('Dashboard', 'Incidents for ' . ($user['department'] ?: 'your department') . '.');
if ($needs) {
    echo '<div class="alert alert-warn">' . icon('alert') . '<span><strong>' . count($needs) . ' incident' . (count($needs) > 1 ? 's need' : ' needs')
        . ' your acknowledgment.</strong></span><a class="link" href="' . e(url('cases')) . '">Open list</a></div>';
}
?>
<section class="stats">
  <?= stat_card('Department incidents', count($cases), '', '', 'file', null, 'c-lime') ?>
  <?= stat_card('Needs acknowledgment', count($needs), 'Waiting for you', $needs ? 'stat-alert' : '', 'alert', pct(count($needs), count($cases)), 'c-amber') ?>
  <?= stat_card('Under review', $by('review'), '', '', 'search', pct($by('review'), count($cases)), 'c-teal') ?>
  <?= stat_card('Resolved', $by('resolved'), '', '', 'check', pct($by('resolved'), count($cases))) ?>
</section>
<div class="split">
  <section class="card chart-card">
    <div class="card-head"><h2>Reports per month</h2><span class="chart-sub">Last 6 months</span></div>
    <?= month_bars($cases) ?>
  </section>
  <section class="card chart-card">
    <div class="card-head"><h2>Status overview</h2></div>
    <?= donut_chart([['Pending', $by('pending'), '#f2c14e'], ['Under review', $by('review'), '#0f9b8e'], ['Resolved', $by('resolved'), '#16a34a']]) ?>
  </section>
</div>

<section class="card">
  <div class="card-head"><h2>Needs your acknowledgment</h2><a class="link" href="<?= e(url('cases')) ?>">View all incidents</a></div>
  <?= $needs ? cases_table(array_slice(array_reverse($needs), 0, 5), $role)
             : empty_state('Nothing to acknowledge', 'Incidents appear here once Guidance starts reviewing them.') ?>
</section>
<?php
layout_end();
