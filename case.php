<?php
declare(strict_types=1);

$id = (int)g('id');
$case = find_case($user, $id);
if (!$case) {
    abort_page(404, 'Report not found', 'This report does not exist or you do not have access to it.');
}
$role = $user['role'];
$errors = [];
$form = ['status' => $case['status'], 'findings' => $case['findings'] ?? '', 'recommendation' => $case['recommendation'] ?? ''];
$link = url('case', ['id' => $id]);

if (is_post()) {
    csrf_verify();
    $action = p('action');

    if ($action === 'update' && $role === 'guidance') {
        $form = ['status' => p('status'), 'findings' => p('findings'), 'recommendation' => p('recommendation')];
        if (!isset(STATUS_LABELS[$form['status']])) {
            $errors['status'] = 'Choose a status.';
        }
        if (mb_strlen($form['findings']) > 2000) {
            $errors['findings'] = 'Keep the findings under 2,000 characters.';
        }
        if (mb_strlen($form['recommendation']) > 2000) {
            $errors['recommendation'] = 'Keep the recommendation under 2,000 characters.';
        }
        if ($form['status'] === 'resolved') {
            if ($form['findings'] === '') {
                $errors['findings'] = 'Enter your findings before resolving this report.';
            }
            if ($form['recommendation'] === '') {
                $errors['recommendation'] = 'Enter a recommendation before resolving this report.';
            }
        }
        if (!$errors) {
            $statusChanged = $form['status'] !== $case['status'];
            $textChanged = $form['findings'] !== ($case['findings'] ?? '') || $form['recommendation'] !== ($case['recommendation'] ?? '');
            if (!$statusChanged && !$textChanged) {
                flash('info', 'There were no changes to save.');
                redirect('case', ['id' => $id]);
            }
            $oldStatus = $case['status'];
            db_mutate('cases', function (array &$rows) use ($id, $form, $statusChanged, $textChanged, $oldStatus, $user) {
                foreach ($rows as &$r) {
                    if ($r['id'] !== $id) continue;
                    $r['status'] = $form['status'];
                    $r['findings'] = $form['findings'];
                    $r['recommendation'] = $form['recommendation'];
                    $r['updated_at'] = now();
                    $r['resolved_at'] = $form['status'] === 'resolved' ? now() : null;
                    if ($statusChanged) {
                        $r['ack'] = []; // deans acknowledge each status change
                        $r['history'][] = ['at' => now(), 'by' => $user['name'], 'public' => true,
                            'text' => 'Status changed from ' . STATUS_LABELS[$oldStatus] . ' to ' . STATUS_LABELS[$form['status']] . '.'];
                    }
                    if ($textChanged) {
                        $r['history'][] = ['at' => now(), 'by' => $user['name'], 'public' => false, 'text' => 'Findings or recommendation updated.'];
                    }
                }
            });
            if ($statusChanged) {
                $label = STATUS_LABELS[$form['status']];
                notify([$case['reporter_id']], 'Your report ' . $case['ref'] . ' is now ' . $label . '.', $link);
                if ($form['status'] !== 'pending') {
                    notify(array_column(active_users_by_role('dean', $case['department']), 'id'),
                        'Incident ' . $case['ref'] . ' (' . $case['department'] . ') is now ' . $label . '. Please review and acknowledge.', $link);
                }
            }
            flash('success', 'Case updated.');
            redirect('case', ['id' => $id]);
        }
    } elseif ($action === 'ack' && $role === 'dean') {
        db_mutate('cases', function (array &$rows) use ($id, $user) {
            foreach ($rows as &$r) {
                if ($r['id'] === $id && !in_array($user['id'], array_column($r['ack'] ?? [], 'user_id'), true)) {
                    $r['ack'][] = ['user_id' => $user['id'], 'name' => $user['name'], 'at' => now()];
                    $r['history'][] = ['at' => now(), 'by' => $user['name'], 'public' => false, 'text' => 'Acknowledged by ' . $user['name'] . '.'];
                }
            }
        });
        flash('success', 'Thank you. You have acknowledged this incident.');
        redirect('case', ['id' => $id]);
    }
}

$acked = in_array($user['id'], array_column($case['ack'] ?? [], 'user_id'), true);
$history = array_reverse($case['history'] ?? []);
if ($role === 'student') {
    $history = array_values(array_filter($history, fn($h) => $h['public'] ?? false));
}

layout_start($case['ref'], 'Violation report: ' . $case['type'], '<a class="btn btn-outline" href="' . e(url('cases')) . '">Back to list</a>');

if ($errors) {
    echo '<div class="alert alert-error" role="alert">' . icon('alert') . '<span>Please fix the highlighted fields and try again.</span></div>';
}
if ($role === 'dean' && !$acked) {
    echo '<div class="alert alert-warn">' . icon('alert') . '<span>This incident is waiting for your acknowledgment.</span></div>';
}

// Progress steps
$step = ['pending' => 1, 'review' => 2, 'resolved' => 3][$case['status']];
$steps = ['Submitted', 'Under review', 'Resolved'];
?>
<ol class="steps" aria-label="Report progress">
  <?php foreach ($steps as $i => $label): $n = $i + 1; ?>
    <li class="<?= $n < $step || ($n === 3 && $step === 3) ? 'is-done' : ($n === $step ? 'is-current' : '') ?>">
      <span class="step-dot"><?= $n < $step || ($n === 3 && $step === 3) ? icon('check') : $n ?></span><span><?= e($label) ?></span>
    </li>
  <?php endforeach; ?>
</ol>

<div class="split">
  <div class="stack-lg">
    <section class="card">
      <div class="card-head"><h2>Report details</h2><?= status_badge($case['status']) ?></div>
      <dl class="details">
        <div><dt>Student involved</dt><dd><?= e($case['student_name']) ?> (<?= e($case['student_no']) ?>)</dd></div>
        <div><dt>Department</dt><dd><?= e($case['department']) ?></dd></div>
        <div><dt>Violation type</dt><dd><?= e($case['type']) ?></dd></div>
        <div><dt>Date of incident</dt><dd><?= e(fmt_date($case['date'])) ?></dd></div>
        <div><dt>Location</dt><dd><?= e($case['location']) ?></dd></div>
        <div><dt>Submitted</dt><dd><?= e(fmt_datetime($case['created_at'])) ?><?= $role !== 'student' ? ' by ' . e($case['reporter_name']) : '' ?></dd></div>
      </dl>
      <h3>Description</h3>
      <p class="prose"><?= nl2br(e($case['description'])) ?></p>
      <h3>Attachments</h3>
      <?php if (!empty($case['attachments'])): ?>
        <ul class="files">
          <?php foreach ($case['attachments'] as $i => $a): ?>
            <li><?= icon('clip') ?><span><?= e($a['name']) ?> <small class="muted">(<?= e(fmt_size((int)$a['size'])) ?>)</small></span>
              <a class="link" href="<?= e(url('file', ['case' => $id, 'i' => $i])) ?>">Download</a></li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?><p class="muted">No files attached.</p><?php endif; ?>
    </section>

    <?php if ($role === 'guidance'): ?>
    <section class="card">
      <h2>Update case</h2>
      <form method="post" class="form" novalidate
            data-confirm-field="status" data-confirm-value="resolved"
            data-confirm="Mark this report as resolved? The student and the department head will be notified.">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update">
        <div class="field">
          <label for="status">Status</label>
          <select id="status" name="status">
            <?php foreach (STATUS_LABELS as $k => $label): ?>
              <option value="<?= e($k) ?>" <?= $form['status'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <?= field_error($errors, 'status') ?>
        </div>
        <div class="field">
          <label for="findings">Investigation findings</label>
          <textarea id="findings" name="findings" rows="4"><?= e($form['findings']) ?></textarea>
          <p class="hint">Required when the report is resolved.</p>
          <?= field_error($errors, 'findings') ?>
        </div>
        <div class="field">
          <label for="recommendation">Recommendation</label>
          <textarea id="recommendation" name="recommendation" rows="4"><?= e($form['recommendation']) ?></textarea>
          <?= field_error($errors, 'recommendation') ?>
        </div>
        <div class="form-actions"><button type="submit" class="btn btn-primary">Save changes</button></div>
      </form>
    </section>
    <?php elseif ($role === 'dean'): ?>
    <section class="card">
      <h2>Investigation result</h2>
      <h3>Findings</h3>
      <p class="prose"><?= $case['findings'] !== '' ? nl2br(e($case['findings'])) : '<span class="muted">Not provided yet.</span>' ?></p>
      <h3>Recommendation</h3>
      <p class="prose"><?= $case['recommendation'] !== '' ? nl2br(e($case['recommendation'])) : '<span class="muted">Not provided yet.</span>' ?></p>
    </section>
    <?php endif; ?>
  </div>

  <div class="stack-lg">
    <?php if ($role === 'dean'): ?>
    <section class="card">
      <h2>Acknowledgment</h2>
      <?php if ($acked): ?>
        <p class="muted">You acknowledged this incident.</p>
      <?php else: ?>
        <p class="muted">Confirm that you have seen this incident.</p>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="ack">
          <button type="submit" class="btn btn-primary btn-block"><?= icon('check') ?>Acknowledge</button></form>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($role === 'guidance' && !empty($case['ack'])): ?>
    <section class="card">
      <h2>Acknowledged by</h2>
      <ul class="plain">
        <?php foreach ($case['ack'] as $a): ?><li><strong><?= e($a['name']) ?></strong><br><small class="muted"><?= e(fmt_datetime($a['at'])) ?></small></li><?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>

    <section class="card">
      <h2>Activity</h2>
      <ul class="timeline">
        <?php foreach ($history as $h): ?>
          <li><span class="tl-text"><?= e($h['text']) ?></span><small class="muted"><?= e($h['by']) ?> · <?= e(fmt_datetime($h['at'])) ?></small></li>
        <?php endforeach; ?>
      </ul>
    </section>
  </div>
</div>
<?php layout_end();
