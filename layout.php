<?php
declare(strict_types=1);

/** Shared page layout and small UI components. */

function icon(string $name, string $class = ''): string
{
    return '<svg class="icon ' . $class . '" aria-hidden="true"><use href="#i-' . $name . '"/></svg>';
}

function sprite(): string
{
    $s = [
        'home'     => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'plus'     => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>',
        'file'     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
        'bell'     => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'users'    => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'sliders'  => '<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>',
        'chart'    => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
        'user'     => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'logout'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'search'   => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'menu'     => '<line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>',
        'check'    => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
        'alert'    => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
        'printer'  => '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
        'clip'     => '<path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>',
        'shield'   => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'x'        => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'info'     => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
    ];
    $out = '<svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs>';
    foreach ($s as $id => $paths) {
        $out .= '<symbol id="i-' . $id . '" viewBox="0 0 24 24">' . $paths . '</symbol>';
    }
    return $out . '</defs></svg>';
}

function nav_for(string $role): array
{
    $menu = [['dashboard', 'Dashboard', 'home']];
    switch ($role) {
        case 'student':
            $menu[] = ['report_new', 'New Report', 'plus'];
            $menu[] = ['cases', 'My Reports', 'file'];
            break;
        case 'guidance':
            $menu[] = ['cases', 'Violation Reports', 'file'];
            $menu[] = ['reports', 'Reports', 'chart'];
            break;
        case 'dean':
            $menu[] = ['cases', 'Department Incidents', 'file'];
            $menu[] = ['reports', 'Reports', 'chart'];
            break;
        case 'admin':
            $menu[] = ['users', 'Users', 'users'];
            $menu[] = ['settings', 'System Settings', 'sliders'];
            $menu[] = ['reports', 'Reports', 'chart'];
            break;
    }
    $account = [];
    if ($role !== 'admin') {
        $account[] = ['notifications', 'Notifications', 'bell'];
    }
    $account[] = ['profile', 'My Profile', 'user'];
    return ['Menu' => $menu, 'Account' => $account];
}

function layout_start(string $title, string $subtitle = '', string $actions = ''): void
{
    global $page, $user;
    $active = ['case' => 'cases', 'user_form' => 'users'][$page] ?? $page;
    $unread = unread_count($user['id']);
    $initial = strtoupper(mb_substr($user['name'], 0, 1));
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e(APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="style.css">
</head>
<body>
<?= sprite() ?>
<div class="shell">
  <aside class="sidebar" id="sidebar" aria-label="Main navigation">
    <div class="brand">
      <span class="brand-mark"><img src="olfu%20logo.png" alt="OLFU logo"></span>
      <span><strong><?= e(APP_NAME) ?></strong><small>OLFU Antipolo</small></span>
    </div>
    <?php foreach (nav_for($user['role']) as $group => $items): ?>
      <p class="nav-group"><?= e($group) ?></p>
      <nav class="nav">
        <?php foreach ($items as [$pg, $label, $ic]): ?>
          <a href="<?= e(url($pg)) ?>" class="<?= $active === $pg ? 'is-active' : '' ?>"<?= $active === $pg ? ' aria-current="page"' : '' ?>>
            <?= icon($ic) ?><span><?= e($label) ?></span>
            <?php if ($pg === 'notifications' && $unread > 0): ?><span class="count"><?= $unread ?></span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </nav>
    <?php endforeach; ?>
    <form method="post" action="<?= e(url('logout')) ?>" class="sidebar-foot">
      <?= csrf_field() ?>
      <button type="submit" class="nav-logout"><?= icon('logout') ?><span>Sign out</span></button>
    </form>
  </aside>
  <div class="scrim" id="scrim"></div>
  <div class="main">
    <header class="topbar">
      <button type="button" class="icon-btn menu-btn" id="menu-btn" aria-label="Open menu" aria-controls="sidebar"><?= icon('menu') ?></button>
      <span class="topbar-title"><?= e(CAMPUS_NAME) ?></span>
      <div class="topbar-user">
        <?php if ($user['role'] !== 'admin'): ?>
          <a class="icon-btn bell" href="<?= e(url('notifications')) ?>" aria-label="Notifications<?= $unread ? ', ' . $unread . ' unread' : '' ?>">
            <?= icon('bell') ?><?php if ($unread > 0): ?><span class="dot"><?= $unread > 9 ? '9+' : $unread ?></span><?php endif; ?>
          </a>
        <?php endif; ?>
        <a class="user-chip" href="<?= e(url('profile')) ?>">
          <span class="avatar"><?= e($initial) ?></span>
          <span class="user-meta"><strong><?= e($user['name']) ?></strong><small><?= e(ROLE_LABELS[$user['role']]) ?></small></span>
        </a>
      </div>
    </header>
    <main class="content" id="content">
      <?php foreach (take_flashes() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>">
          <?= icon($f['type'] === 'success' ? 'check' : ($f['type'] === 'error' ? 'alert' : 'info')) ?>
          <span><?= e($f['message']) ?></span>
          <button type="button" class="alert-close" aria-label="Dismiss message"><?= icon('x') ?></button>
        </div>
      <?php endforeach; ?>
      <div class="page-head">
        <div>
          <h1><?= e($title) ?></h1>
          <?php if ($subtitle): ?><p class="muted"><?= e($subtitle) ?></p><?php endif; ?>
        </div>
        <?php if ($actions): ?><div class="page-actions"><?= $actions ?></div><?php endif; ?>
      </div>
<?php
}

function layout_end(): void
{
    ?>
    </main>
  </div>
</div>
<dialog id="confirm-dialog" class="dialog">
  <form method="dialog">
    <h2 id="confirm-title">Please confirm</h2>
    <p id="confirm-text" class="muted"></p>
    <div class="dialog-actions">
      <button type="button" class="btn btn-outline" id="confirm-cancel">Cancel</button>
      <button type="button" class="btn btn-primary" id="confirm-ok">Confirm</button>
    </div>
  </form>
</dialog>
<script src="app.js"></script>
</body>
</html>
<?php
}

function auth_start(string $title, string $heading, string $sub = ''): void
{
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e(APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="style.css">
</head>
<body class="auth-body">
<?= sprite() ?>
<main class="auth-card">
  <div class="auth-brand">
    <span class="brand-mark"><img src="olfu%20logo.png" alt="OLFU logo"></span>
    <div><strong><?= e(APP_NAME) ?></strong><small><?= e(CAMPUS_NAME) ?></small></div>
  </div>
  <h1><?= e($heading) ?></h1>
  <?php if ($sub): ?><p class="muted"><?= e($sub) ?></p><?php endif; ?>
  <?php foreach (take_flashes() as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?>" role="alert"><?= icon($f['type'] === 'success' ? 'check' : ($f['type'] === 'error' ? 'alert' : 'info')) ?><span><?= e($f['message']) ?></span></div>
  <?php endforeach; ?>
<?php
}

function auth_end(): void
{
    ?>
</main>
<script src="app.js"></script>
</body>
</html>
<?php
}

function abort_page(int $code, string $title, string $message): void
{
    global $user;
    http_response_code($code);
    $back = current_user() ? '<a class="btn btn-primary" href="' . e(url('dashboard')) . '">Back to dashboard</a>'
                           : '<a class="btn btn-primary" href="' . e(url('login')) . '">Go to sign in</a>';
    if (current_user()) {
        $user = current_user();
        layout_start($title);
        echo empty_state($title, $message, $back);
        layout_end();
    } else {
        auth_start($title, $title, $message);
        echo '<p>' . $back . '</p>';
        auth_end();
    }
    exit;
}

function empty_state(string $title, string $text, string $actionHtml = ''): string
{
    return '<div class="empty">' . icon('file', 'empty-icon') . '<h3>' . e($title) . '</h3><p class="muted">' . e($text) . '</p>'
        . ($actionHtml ? '<div>' . $actionHtml . '</div>' : '') . '</div>';
}

function stat_card(string $label, $value, string $hint = '', string $tone = '', string $ic = '', ?float $pct = null, string $chip = ''): string
{
    $ico  = $ic ? '<span class="stat-ico ' . e($chip) . '">' . icon($ic) . '</span>' : '';
    $ring = '';
    if ($pct !== null) {
        $pct  = max(0.0, min(100.0, $pct));
        $ring = '<span class="ring" style="--p:' . round($pct, 1) . '" aria-hidden="true"><b>' . round($pct, 1) . '%</b></span>';
    }
    return '<div class="stat ' . $tone . '">' . $ico . '<span class="stat-body"><span class="stat-label">' . e($label) . '</span><span class="stat-value">' . e($value)
        . '</span>' . ($hint ? '<span class="stat-hint">' . e($hint) . '</span>' : '') . '</span>' . $ring . '</div>';
}

/** Share of $part in $whole as a percentage (0 when empty). */
function pct(int $part, int $whole): float
{
    return $whole > 0 ? $part * 100 / $whole : 0.0;
}

/** Donut chart with legend. $items = [[label, value, '#hex'], ...] */
function donut_chart(array $items): string
{
    $total = array_sum(array_column($items, 1));
    $stops = [];
    $at = 0.0;
    foreach ($items as [$label, $n, $color]) {
        if ($total <= 0 || $n <= 0) {
            continue;
        }
        $to = $at + $n * 100 / $total;
        $stops[] = $color . ' ' . round($at, 2) . '% ' . round($to, 2) . '%';
        $at = $to;
    }
    $bg = $stops ? 'conic-gradient(' . implode(',', $stops) . ')' : '#e4f0e7';
    $out = '<div class="donut-wrap"><div class="donut" style="background:' . $bg . '" role="img" aria-label="Chart: '
        . e(implode(', ', array_map(fn($i) => $i[0] . ' ' . $i[1], $items))) . '"></div><ul class="legend">';
    foreach ($items as [$label, $n, $color]) {
        $out .= '<li><i style="background:' . $color . '"></i><span>' . e($label) . '</span><b>' . (int)$n . '</b></li>';
    }
    return $out . '</ul></div>';
}

/** Bars of cases submitted in each of the last $months months. */
function month_bars(array $cases, int $months = 6): string
{
    $buckets = [];
    for ($i = $months - 1; $i >= 0; $i--) {
        $t = strtotime('first day of -' . $i . ' month');
        $buckets[date('Y-m', $t)] = [date('M', $t), 0];
    }
    foreach ($cases as $c) {
        $k = date('Y-m', strtotime($c['created_at']) ?: 0);
        if (isset($buckets[$k])) {
            $buckets[$k][1]++;
        }
    }
    $max = max(1, max(array_column($buckets, 1)));
    $out = '<div class="months" role="img" aria-label="Reports per month">';
    foreach ($buckets as [$label, $n]) {
        $out .= '<div class="month"><span class="month-n">' . $n . '</span><div class="month-track"><div class="month-fill" style="height:'
            . round($n * 100 / $max) . '%"></div></div><span class="month-l">' . e($label) . '</span></div>';
    }
    return $out . '</div>';
}

function status_badge(string $status): string
{
    return '<span class="badge badge-' . e($status) . '">' . e(STATUS_LABELS[$status] ?? $status) . '</span>';
}

function field_error(array $errors, string $key): string
{
    return isset($errors[$key]) ? '<p class="field-error" id="err-' . e($key) . '">' . e($errors[$key]) . '</p>' : '';
}

/** Table of cases used on the dashboard and the list page. */
function cases_table(array $rows, string $role, bool $sortable = false, string $sort = '', string $dir = ''): string
{
    $th = function (string $label, string $key) use ($sortable, $sort, $dir): string {
        return $sortable ? th_sort($label, $key, $sort, $dir) : '<th>' . e($label) . '</th>';
    };
    ob_start();
    ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr>
          <?= $th('Reference', 'ref') ?>
          <?= $th('Student involved', 'student') ?>
          <?= $th('Violation', 'type') ?>
          <?php if ($role === 'guidance'): ?><?= $th('Department', 'department') ?><?php endif; ?>
          <?= $th('Submitted', 'submitted') ?>
          <?= $th('Status', 'status') ?>
          <?php if ($role === 'dean'): ?><th>Acknowledged</th><?php endif; ?>
          <th><span class="sr-only">Action</span></th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $c): ?>
          <tr>
            <td><a class="link" href="<?= e(url('case', ['id' => $c['id']])) ?>"><?= e($c['ref']) ?></a></td>
            <td><strong><?= e($c['student_name']) ?></strong><br><small class="muted"><?= e($c['student_no']) ?></small></td>
            <td><?= e($c['type']) ?></td>
            <?php if ($role === 'guidance'): ?><td><?= e($c['department']) ?></td><?php endif; ?>
            <td><?= e(fmt_date($c['created_at'])) ?></td>
            <td><?= status_badge($c['status']) ?></td>
            <?php if ($role === 'dean'): ?>
              <td><?php
                $me = $GLOBALS['user']['id'];
                $acked = in_array($me, array_column($c['ack'] ?? [], 'user_id'), true);
                echo $acked ? '<span class="badge badge-resolved">Yes</span>' : '<span class="badge badge-pending">Needed</span>';
              ?></td>
            <?php endif; ?>
            <td class="t-right"><a class="btn btn-outline btn-sm" href="<?= e(url('case', ['id' => $c['id']])) ?>"><?= $role === 'guidance' && $c['status'] !== 'resolved' ? 'Review' : 'Open' ?></a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
    return (string)ob_get_clean();
}
