<?php
declare(strict_types=1);

$role = $user['role'];
$settings = settings();
$all = cases_for_user($user);

$q = mb_strtolower(g('q'));
$status = g('status');
$type = g('type');
$dept = $role === 'guidance' ? g('department') : '';
$sort = g('sort', 'submitted');
$dir = g('dir', 'desc') === 'asc' ? 'asc' : 'desc';

$rows = array_values(array_filter($all, function ($c) use ($q, $status, $type, $dept) {
    if ($status !== '' && $c['status'] !== $status) return false;
    if ($type !== '' && $c['type'] !== $type) return false;
    if ($dept !== '' && $c['department'] !== $dept) return false;
    if ($q !== '') {
        $hay = mb_strtolower($c['ref'] . ' ' . $c['student_name'] . ' ' . $c['student_no']);
        if (!str_contains($hay, $q)) return false;
    }
    return true;
}));

$statusOrder = ['pending' => 0, 'review' => 1, 'resolved' => 2];
$rows = sort_rows($rows, $sort, $dir, [
    'ref'        => fn($c) => $c['id'],
    'student'    => fn($c) => $c['student_name'],
    'type'       => fn($c) => $c['type'],
    'department' => fn($c) => $c['department'],
    'submitted'  => fn($c) => $c['created_at'],
    'status'     => fn($c) => $statusOrder[$c['status']],
]);
[$pageRows, $meta] = paginate($rows);

$titles = ['student' => 'My Reports', 'guidance' => 'Violation Reports', 'dean' => 'Department Incidents'];
$subs = [
    'student'  => 'Reports you have submitted and their progress.',
    'guidance' => 'Review and resolve reported violations.',
    'dean'     => 'Incidents involving students in ' . ($user['department'] ?: 'your department') . '.',
];
$actions = $role === 'student' ? '<a class="btn btn-primary" href="' . e(url('report_new')) . '">' . icon('plus') . 'New report</a>' : '';
layout_start($titles[$role], $subs[$role], $actions);

$pills = ['' => 'All'];
foreach (STATUS_LABELS as $k => $label) {
    if ($role === 'dean' && $k === 'pending') continue;
    $pills[$k] = $label;
}
$countFor = fn(string $s) => $s === '' ? count($all) : count(array_filter($all, fn($c) => $c['status'] === $s));
$pillUrl = function (string $s): string {
    $params = $_GET;
    unset($params['p'], $params['status']);
    if ($s !== '') $params['status'] = $s;
    return 'index.php?' . http_build_query($params);
};
$hasFilters = $q !== '' || $type !== '' || $dept !== '' || $status !== '';
?>
<div class="pills" role="tablist" aria-label="Filter by status">
  <?php foreach ($pills as $k => $label): ?>
    <a href="<?= e($pillUrl($k)) ?>" class="pill <?= $status === $k ? 'is-active' : '' ?>" <?= $status === $k ? 'aria-current="true"' : '' ?>>
      <?= e($label) ?> <span class="pill-count"><?= $countFor($k) ?></span>
    </a>
  <?php endforeach; ?>
</div>

<section class="card">
  <form method="get" class="filters">
    <input type="hidden" name="page" value="cases">
    <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <div class="field grow">
      <label for="q" class="sr-only">Search</label>
      <div class="search"><?= icon('search') ?><input type="search" id="q" name="q" value="<?= e(g('q')) ?>" placeholder="Search by reference, student name or number"></div>
    </div>
    <div class="field">
      <label for="type" class="sr-only">Violation type</label>
      <select id="type" name="type">
        <option value="">All violation types</option>
        <?php foreach ($settings['violation_types'] as $t): ?><option value="<?= e($t) ?>" <?= $type === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
      </select>
    </div>
    <?php if ($role === 'guidance'): ?>
    <div class="field">
      <label for="department" class="sr-only">Department</label>
      <select id="department" name="department">
        <option value="">All departments</option>
        <?php foreach ($settings['departments'] as $d): ?><option value="<?= e($d) ?>" <?= $dept === $d ? 'selected' : '' ?>><?= e($d) ?></option><?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <button type="submit" class="btn btn-primary">Apply</button>
    <?php if ($hasFilters): ?><a class="btn btn-outline" href="<?= e(url('cases')) ?>">Clear</a><?php endif; ?>
  </form>

  <?php if ($rows): ?>
    <?= cases_table($pageRows, $role, true, $sort, $dir) ?>
    <?= pager($meta) ?>
  <?php elseif ($hasFilters): ?>
    <?= empty_state('No matching reports', 'Try a different search or clear the filters.', '<a class="btn btn-outline" href="' . e(url('cases')) . '">Clear filters</a>') ?>
  <?php elseif ($role === 'student'): ?>
    <?= empty_state('No reports yet', 'Reports you submit will be listed here.', '<a class="btn btn-primary" href="' . e(url('report_new')) . '">Submit a report</a>') ?>
  <?php elseif ($role === 'dean'): ?>
    <?= empty_state('No incidents yet', 'Incidents for your department appear here once Guidance starts reviewing them.') ?>
  <?php else: ?>
    <?= empty_state('No reports yet', 'Reports submitted by students will appear here.') ?>
  <?php endif; ?>
</section>
<?php layout_end();
