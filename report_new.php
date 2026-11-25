<?php
declare(strict_types=1);

$settings = settings();
$errors = [];
$v = ['student_name' => '', 'student_no' => '', 'department' => '', 'type' => '', 'date' => '', 'location' => '', 'description' => ''];

if (is_post()) {
    csrf_verify();
    foreach ($v as $k => $_) {
        $v[$k] = p($k);
    }

    if (mb_strlen($v['student_name']) < 2 || mb_strlen($v['student_name']) > 80) {
        $errors['student_name'] = 'Enter the full name of the student involved.';
    }
    if (!preg_match('/^[A-Za-z0-9-]{3,20}$/', $v['student_no'])) {
        $errors['student_no'] = 'Enter a valid student number (letters, numbers and hyphens only).';
    }
    if (!in_array($v['department'], $settings['departments'], true)) {
        $errors['department'] = 'Choose a department.';
    }
    if (!in_array($v['type'], $settings['violation_types'], true)) {
        $errors['type'] = 'Choose the type of violation.';
    }
    $d = DateTime::createFromFormat('Y-m-d', $v['date']);
    if (!$d || $d->format('Y-m-d') !== $v['date']) {
        $errors['date'] = 'Choose the date the incident happened.';
    } elseif ($v['date'] > date('Y-m-d')) {
        $errors['date'] = 'The incident date cannot be in the future.';
    }
    if (mb_strlen($v['location']) < 2 || mb_strlen($v['location']) > 100) {
        $errors['location'] = 'Enter where the incident happened.';
    }
    if (mb_strlen($v['description']) < 20) {
        $errors['description'] = 'Describe what happened in at least 20 characters.';
    } elseif (mb_strlen($v['description']) > 2000) {
        $errors['description'] = 'Keep the description under 2,000 characters.';
    }
    $files = collect_uploads('attachments', $errors);

    if (!$errors) {
        $attachments = store_uploads($files);
        $case = db_mutate('cases', function (array &$rows) use ($v, $user, $attachments) {
            $id = next_id($rows);
            $case = [
                'id' => $id, 'ref' => sprintf('VR-%s-%04d', date('Y'), $id), 'reporter_id' => $user['id'],
                'reporter_name' => $user['name'], 'student_name' => $v['student_name'], 'student_no' => strtoupper($v['student_no']),
                'department' => $v['department'], 'type' => $v['type'], 'date' => $v['date'], 'location' => $v['location'],
                'description' => $v['description'], 'attachments' => $attachments, 'status' => 'pending',
                'findings' => '', 'recommendation' => '', 'resolved_at' => null, 'ack' => [],
                'history' => [['at' => now(), 'by' => $user['name'], 'text' => 'Report submitted.', 'public' => true]],
                'created_at' => now(), 'updated_at' => now(),
            ];
            $rows[] = $case;
            return $case;
        });
        notify([$user['id']], 'We received your report ' . $case['ref'] . '. Guidance will review it soon.', url('case', ['id' => $case['id']]));
        notify(array_column(active_users_by_role('guidance'), 'id'),
            'New report ' . $case['ref'] . ' (' . $case['type'] . ') is waiting for review.', url('case', ['id' => $case['id']]));
        flash('success', 'Report submitted. Your reference number is ' . $case['ref'] . '.');
        redirect('case', ['id' => $case['id']]);
    }
}

layout_start('New Violation Report', 'Tell us what happened. Guidance will review your report.');
if (!$settings['departments'] || !$settings['violation_types']) {
    echo '<div class="alert alert-warn">' . icon('alert') . '<span>Reports cannot be submitted yet because the administrator has not set up departments or violation types.</span></div>';
}
if ($errors) {
    echo '<div class="alert alert-error" role="alert">' . icon('alert') . '<span>Please fix the highlighted fields and try again.</span></div>';
}
?>
<form method="post" enctype="multipart/form-data" class="card form" novalidate>
  <?= csrf_field() ?>
  <h2>Student involved</h2>
  <div class="form-grid">
    <div class="field">
      <label for="student_name">Full name</label>
      <input type="text" id="student_name" name="student_name" value="<?= e($v['student_name']) ?>" required>
      <?= field_error($errors, 'student_name') ?>
    </div>
    <div class="field">
      <label for="student_no">Student number</label>
      <input type="text" id="student_no" name="student_no" value="<?= e($v['student_no']) ?>" required>
      <?= field_error($errors, 'student_no') ?>
    </div>
    <div class="field">
      <label for="department">Department</label>
      <select id="department" name="department" required>
        <option value="">Select department</option>
        <?php foreach ($settings['departments'] as $d): ?>
          <option value="<?= e($d) ?>" <?= $v['department'] === $d ? 'selected' : '' ?>><?= e($d) ?></option>
        <?php endforeach; ?>
      </select>
      <?= field_error($errors, 'department') ?>
    </div>
  </div>

  <h2>Incident details</h2>
  <div class="form-grid">
    <div class="field">
      <label for="type">Type of violation</label>
      <select id="type" name="type" required>
        <option value="">Select type</option>
        <?php foreach ($settings['violation_types'] as $t): ?>
          <option value="<?= e($t) ?>" <?= $v['type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
        <?php endforeach; ?>
      </select>
      <?= field_error($errors, 'type') ?>
    </div>
    <div class="field">
      <label for="date">Date of incident</label>
      <input type="date" id="date" name="date" max="<?= e(date('Y-m-d')) ?>" value="<?= e($v['date']) ?>" required>
      <?= field_error($errors, 'date') ?>
    </div>
    <div class="field">
      <label for="location">Location</label>
      <input type="text" id="location" name="location" value="<?= e($v['location']) ?>" placeholder="e.g. Room 301, canteen" required>
      <?= field_error($errors, 'location') ?>
    </div>
  </div>
  <div class="field">
    <label for="description">What happened?</label>
    <textarea id="description" name="description" rows="5" required><?= e($v['description']) ?></textarea>
    <p class="hint">Include who was involved and what you saw. At least 20 characters.</p>
    <?= field_error($errors, 'description') ?>
  </div>
  <div class="field">
    <label for="attachments">Attachments <span class="optional">(optional)</span></label>
    <input type="file" id="attachments" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
    <p class="hint">Up to 3 files, 5 MB each. JPG, PNG, PDF, DOC or DOCX.</p>
    <?= field_error($errors, 'attachments') ?>
  </div>
  <div class="form-actions">
    <a class="btn btn-outline" href="<?= e(url('dashboard')) ?>">Cancel</a>
    <button type="submit" class="btn btn-primary">Submit report</button>
  </div>
</form>
<?php layout_end();
