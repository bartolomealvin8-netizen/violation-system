<?php
declare(strict_types=1);

/**
 * Process 5.0: generate reports from stored violation data.
 * Each CSV download or print is saved in incident_reports (data store D3).
 */
$role = $user['role'];
$settings = settings();
$errors = [];

$from = g('from');
$to = g('to');
$status = g('status');
$type = g('type');
$dept = $role === 'dean' ? ($user['department'] ?? '') : g('department');

$validDate = fn(string $d) => ($x = DateTime::createFromFormat('Y-m-d', $d)) && $x->format('Y-m-d') === $d;
if ($from !== '' && !$validDate($from)) $errors['from'] = 'Enter a valid start date.';
if ($to !== '' && !$validDate($to)) $errors['to'] = 'Enter a valid end date.';
if (!$errors && $from !== '' && $to !== '' && $from > $to) $errors['to'] = 'The end date must be the same as or after the start date.';

$source = $role === 'dean' ? cases_for_user($user) : db_read('cases');
$rows = [];
if (!$errors) {
    $rows = array_values(array_filter($source, function ($c) use ($from, $to, $status, $type, $dept) {
        $day = substr($c['created_at'], 0, 10);
        if ($from !== '' && $day < $from) return false;
        if ($to !== '' && $day > $to) return false;
        if ($status !== '' && $c['status'] !== $status) return false;
        if ($type !== '' && $c['type'] !== $type) return false;
        if ($dept !== '' && $c['department'] !== $dept) return false;
        return true;
    }));
    usort($rows, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
}

$total = count($rows);
$byStatus = array_fill_keys(array_keys(STATUS_LABELS), 0);
$byType = [];
$byDept = [];
foreach ($rows as $c) {
    $byStatus[$c['status']]++;
    $byType[$c['type']] = ($byType[$c['type']] ?? 0) + 1;
    $byDept[$c['department']] = ($byDept[$c['department']] ?? 0) + 1;
}
arsort($byType);
arsort($byDept);

$filterParts = [];
if ($from !== '' || $to !== '') $filterParts[] = ($from !== '' ? fmt_date($from) : 'Start') . ' – ' . ($to !== '' ? fmt_date($to) : 'Today');
if ($status !== '') $filterParts[] = STATUS_LABELS[$status] ?? $status;
if ($type !== '') $filterParts[] = $type;
if ($dept !== '') $filterParts[] = $dept;
$filterText = $filterParts ? implode(' · ', $filterParts) : 'All reports';

$logReport = function (string $format) use ($user, $total, $filterText, $from, $to, $status, $type, $dept) {
    db_mutate('incident_reports', function (array &$rows) use ($user, $total, $filterText, $format, $from, $to, $status, $type, $dept) {
        $rows[] = ['id' => next_id($rows), 'user_id' => $user['id'], 'user_name' => $user['name'], 'format' => $format,
                   'filters_text' => $filterText, 'query' => array_filter(compact('from', 'to', 'status', 'type') + ['department' => $dept]),
                   'count' => $total, 'created_at' => now()];
    });
};

// CSV download
if (g('export') === 'csv' && !$errors) {
    $logReport('CSV');
    $safe = fn($v) => preg_match('/^[=+\-@\t\r]/', (string)$v) ? "'" . $v : $v; // block spreadsheet formulas
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="violation-report-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    $head = ['Reference', 'Student', 'Student number', 'Department', 'Violation', 'Incident date', 'Location', 'Submitted', 'Status', 'Resolved on'];
    if ($role === 'guidance') { $head[] = 'Findings'; $head[] = 'Recommendation'; }
    fputcsv($out, $head);
    foreach ($rows as $c) {
        $line = [$c['ref'], $c['student_name'], $c['student_no'], $c['department'], $c['type'], $c['date'], $c['location'],
                 fmt_datetime($c['created_at']), STATUS_LABELS[$c['status']], $c['resolved_at'] ? fmt_datetime($c['resolved_at']) : ''];
        if ($role === 'guidance') { $line[] = $c['findings']; $line[] = $c['recommendation']; }
        fputcsv($out, array_map($safe, $line));
    }
    fclose($out);
    exit;
}

// Print / save as PDF view
if (g('print') === '1' && !$errors) {
    $logReport('Print / PDF');
    ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Violation Report</title><link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap">
<link rel="stylesheet" href="style.css"></head>
<body class="print-body" data-autoprint="1">
<header class="print-head">
  <h1>Student Violation Report</h1>
  <p><?= e(CAMPUS_NAME) ?></p>
  <p class="muted">Filters: <?= e($filterText) ?> · Generated <?= e(fmt_datetime(now())) ?> by <?= e($user['name']) ?></p>
</header>
<p><strong>Total:</strong> <?= $total ?> &nbsp; <?php foreach (STATUS_LABELS as $k => $l): ?><strong><?= e($l) ?>:</strong> <?= $byStatus[$k] ?> &nbsp; <?php endforeach; ?></p>
<?php if ($rows): ?>
<table class="table">
  <thead><tr><th>Reference</th><th>Student</th><th>Department</th><th>Violation</th><th>Submitted</th><th>Status</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $c): ?>
    <tr><td><?= e($c['ref']) ?></td><td><?= e($c['student_name']) ?> (<?= e($c['student_no']) ?>)</td><td><?= e($c['department']) ?></td>
        <td><?= e($c['type']) ?></td><td><?= e(fmt_date($c['created_at'])) ?></td><td><?= e(STATUS_LABELS[$c['status']]) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php else: ?><p class="muted">No reports match these filters.</p><?php endif; ?>
<p class="no-print"><a class="btn btn-outline" href="<?= e(url('reports', array_filter(['from' => $from, 'to' => $to, 'status' => $status, 'type' => $type, 'department' => $dept]))) ?>">Back to reports</a></p>
<script src="app.js"></script>
</body></html>
<?php
    exit;
}

[$pageRows, $meta] = paginate($rows);
$query = array_filter(['from' => $from, 'to' => $to, 'status' => $status, 'type' => $type, 'department' => $role === 'dean' ? '' : $dept]);
$actions = $total && !$errors
    ? '<a class="btn btn-outline" href="' . e(url('reports', $query + ['export' => 'csv'])) . '">' . icon('download') . 'Download CSV</a>'
      . '<a class="btn btn-primary" target="_blank" rel="noopener" href="' . e(url('reports', $query + ['print' => '1'])) . '">' . icon('printer') . 'Print / PDF</a>'
    : '';

$history = array_values(array_filter(db_read('incident_reports'), fn($h) => $h['user_id'] === $user['id']));
$history = array_slice(array_reverse($history), 0, 8);
$hasFilters = $from !== '' || $to !== '' || $status !== '' || $type !== '' || ($role !== 'dean' && $dept !== '');

layout_start('Reports', $role === 'dean' ? 'Incidents for ' . ($user['department'] ?: 'your department') . '.' : 'Choose filters, then view, download or print a report.', $actions);
?>
<section class="card">
  <form method="get" class="filters filters-wrap">
    <input type="hidden" name="page" value="reports">
    <div class="field">
      <label for="from">From</label>
      <input type="date" id="from" name="from" value="<?= e($from) ?>">
      <?= field_error($errors, 'from') ?>
    </div>
    <div class="field">
      <label for="to">To</label>
      <input type="date" id="to" name="to" value="<?= e($to) ?>">
      <?= field_error($errors, 'to') ?>
    </div>
    <div class="field">
      <label for="status">Status</label>
      <select id="status" name="status">
        <option value="">All statuses</option>
        <?php foreach (STATUS_LABELS as $k => $l): if ($role === 'dean' && $k === 'pending') continue; ?>
          <option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($l) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="type">Violation type</label>
      <select id="type" name="type">
        <option value="">All types</option>
        <?php foreach ($settings['violation_types'] as $t): ?><option value="<?= e($t) ?>" <?= $type === $t ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
      </select>
    </div>
    <?php if ($role !== 'dean'): ?>
    <div class="field">
      <label for="department">Department</label>
      <select id="department" name="department">
        <option value="">All departments</option>
        <?php foreach ($settings['departments'] as $d): ?><option value="<?= e($d) ?>" <?= $dept === $d ? 'selected' : '' ?>><?= e($d) ?></option><?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="filter-buttons">
      <button type="submit" class="btn btn-primary">Generate</button>
      <?php if ($hasFilters): ?><a class="btn btn-outline" href="<?= e(url('reports')) ?>">Clear</a><?php endif; ?>
    </div>
  </form>
</section>

<?php if (!$errors): ?>
<section class="stats">
  <?= stat_card('Total reports', $total, $filterText) ?>
  <?php foreach (STATUS_LABELS as $k => $l): if ($role === 'dean' && $k === 'pending') continue; ?>
    <?= stat_card($l, $byStatus[$k]) ?>
  <?php endforeach; ?>
</section>

<?php if ($total): ?>
<div class="split split-even">
  <section class="card">
    <h2>By violation type</h2>
    <ul class="bars">
      <?php foreach ($byType as $name => $n): ?>
        <li><span class="bar-label"><?= e($name) ?></span><span class="bar"><span style="width: <?= max(4, (int)round($n / $total * 100)) ?>%"></span></span><span class="bar-n"><?= $n ?></span></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php if ($role !== 'dean'): ?>
  <section class="card">
    <h2>By department</h2>
    <ul class="bars">
      <?php foreach ($byDept as $name => $n): ?>
        <li><span class="bar-label"><?= e($name) ?></span><span class="bar"><span style="width: <?= max(4, (int)round($n / $total * 100)) ?>%"></span></span><span class="bar-n"><?= $n ?></span></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>
</div>
<?php endif; ?>

<section class="card">
  <div class="card-head"><h2>Report results</h2></div>
  <?php if ($rows): ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Reference</th><th>Student involved</th><th>Department</th><th>Violation</th><th>Submitted</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($pageRows as $c): ?>
          <tr>
            <td><a class="link" href="<?= e(url('case', ['id' => $c['id']])) ?>"><?= e($c['ref']) ?></a></td>
            <td><strong><?= e($c['student_name']) ?></strong><br><small class="muted"><?= e($c['student_no']) ?></small></td>
            <td><?= e($c['department']) ?></td><td><?= e($c['type']) ?></td>
            <td><?= e(fmt_date($c['created_at'])) ?></td><td><?= status_badge($c['status']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= pager($meta) ?>
  <?php else: ?>
    <?= empty_state('No reports match these filters', 'Try a wider date range or clear the filters.', $hasFilters ? '<a class="btn btn-outline" href="' . e(url('reports')) . '">Clear filters</a>' : '') ?>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($history): ?>
<section class="card">
  <div class="card-head"><h2>Your recent downloads</h2></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Generated</th><th>Format</th><th>Filters</th><th>Records</th><th><span class="sr-only">Action</span></th></tr></thead>
    <tbody>
    <?php foreach ($history as $h): ?>
      <tr><td><?= e(fmt_datetime($h['created_at'])) ?></td><td><?= e($h['format']) ?></td><td><?= e($h['filters_text']) ?></td><td><?= (int)$h['count'] ?></td>
          <td class="t-right"><a class="btn btn-outline btn-sm" href="<?= e(url('reports', $h['query'] ?? [])) ?>">Open again</a></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endif; ?>
<?php layout_end();
