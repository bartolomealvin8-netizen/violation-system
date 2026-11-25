<?php
declare(strict_types=1);

$editId = (int)g('id');
$editing = $editId ? user_by_id($editId) : null;
if ($editId && !$editing) {
    abort_page(404, 'User not found', 'This user does not exist.');
}
$settings = settings();
$errors = [];
$v = $editing
    ? ['name' => $editing['name'], 'email' => $editing['email'], 'role' => $editing['role'],
       'student_no' => $editing['student_no'] ?? '', 'department' => $editing['department'] ?? '']
    : ['name' => '', 'email' => '', 'role' => 'student', 'student_no' => '', 'department' => ''];
$isSelf = $editing && $editing['id'] === $user['id'];

if (is_post()) {
    csrf_verify();
    foreach ($v as $k => $_) {
        $v[$k] = p($k);
    }
    $v['email'] = strtolower($v['email']);
    $password = (string)($_POST['password'] ?? '');
    if ($isSelf) {
        $v['role'] = $editing['role']; // you cannot change your own role
    }

    if (mb_strlen($v['name']) < 2 || mb_strlen($v['name']) > 80) {
        $errors['name'] = 'Enter a full name (2–80 characters).';
    }
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if (!isset(ROLE_LABELS[$v['role']])) {
        $errors['role'] = 'Choose a role.';
    }
    $needsDept = in_array($v['role'], ['student', 'dean'], true);
    if ($needsDept && !in_array($v['department'], $settings['departments'], true)) {
        $errors['department'] = 'Choose a department.';
    }
    if ($v['role'] === 'student' && !preg_match('/^[A-Za-z0-9-]{3,20}$/', $v['student_no'])) {
        $errors['student_no'] = 'Enter a valid student number (letters, numbers and hyphens).';
    }
    if (!$editing && strlen($password) < 8) {
        $errors['password'] = 'Use at least 8 characters.';
    } elseif ($editing && $password !== '' && strlen($password) < 8) {
        $errors['password'] = 'Use at least 8 characters, or leave blank to keep the current password.';
    }
    foreach (db_read('users') as $u) {
        if ($u['id'] !== ($editing['id'] ?? 0) && strtolower($u['email']) === $v['email']) {
            $errors['email'] = 'Another user already has this email address.';
        }
        if ($v['role'] === 'student' && $v['student_no'] !== '' && $u['id'] !== ($editing['id'] ?? 0)
            && strtoupper($u['student_no'] ?? '') === strtoupper($v['student_no'])) {
            $errors['student_no'] = 'Another student already has this student number.';
        }
    }
    if ($editing && $editing['role'] === 'admin' && $v['role'] !== 'admin' && $editing['active'] && count_active_admins() <= 1) {
        $errors['role'] = 'At least one active administrator is required.';
    }

    if (!$errors) {
        $v['student_no'] = $v['role'] === 'student' ? strtoupper($v['student_no']) : '';
        $v['department'] = $needsDept ? $v['department'] : '';
        if ($editing) {
            db_mutate('users', function (array &$rows) use ($editing, $v, $password) {
                foreach ($rows as &$r) {
                    if ($r['id'] === $editing['id']) {
                        $r = array_merge($r, $v);
                        if ($password !== '') $r['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                    }
                }
            });
            flash('success', 'Changes saved for ' . $v['name'] . '.');
        } else {
            db_mutate('users', function (array &$rows) use ($v, $password) {
                $rows[] = array_merge($v, [
                    'id' => next_id($rows), 'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'active' => true, 'created_at' => now(), 'last_login' => null,
                ]);
            });
            flash('success', $v['name'] . ' was added as ' . ROLE_LABELS[$v['role']] . '.');
        }
        redirect('users');
    }
}

layout_start($editing ? 'Edit User' : 'Add User', $editing ? 'Update this person’s details or reset their password.' : 'Create an account for a student, Guidance staff, dean or administrator.',
    '<a class="btn btn-outline" href="' . e(url('users')) . '">Back to users</a>');
if ($errors) {
    echo '<div class="alert alert-error" role="alert">' . icon('alert') . '<span>Please fix the highlighted fields and try again.</span></div>';
}
?>
<form method="post" class="card form form-narrow" novalidate>
  <?= csrf_field() ?>
  <div class="field">
    <label for="name">Full name</label>
    <input type="text" id="name" name="name" value="<?= e($v['name']) ?>" required>
    <?= field_error($errors, 'name') ?>
  </div>
  <div class="field">
    <label for="email">Email</label>
    <input type="email" id="email" name="email" value="<?= e($v['email']) ?>" required>
    <?= field_error($errors, 'email') ?>
  </div>
  <div class="field">
    <label for="role">Role</label>
    <select id="role" name="role" <?= $isSelf ? 'disabled' : '' ?>>
      <?php foreach (ROLE_LABELS as $k => $label): ?><option value="<?= e($k) ?>" <?= $v['role'] === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
    </select>
    <?php if ($isSelf): ?><p class="hint">You cannot change your own role.</p><?php endif; ?>
    <?= field_error($errors, 'role') ?>
  </div>
  <div class="field" data-show-for="student">
    <label for="student_no">Student number</label>
    <input type="text" id="student_no" name="student_no" value="<?= e($v['student_no']) ?>">
    <?= field_error($errors, 'student_no') ?>
  </div>
  <div class="field" data-show-for="student,dean">
    <label for="department">Department</label>
    <select id="department" name="department">
      <option value="">Select department</option>
      <?php foreach ($settings['departments'] as $d): ?><option value="<?= e($d) ?>" <?= $v['department'] === $d ? 'selected' : '' ?>><?= e($d) ?></option><?php endforeach; ?>
    </select>
    <?= field_error($errors, 'department') ?>
  </div>
  <div class="field">
    <label for="password"><?= $editing ? 'New password' : 'Password' ?><?= $editing ? ' <span class="optional">(optional)</span>' : '' ?></label>
    <input type="password" id="password" name="password" autocomplete="new-password" <?= $editing ? '' : 'required' ?>>
    <p class="hint"><?= $editing ? 'Leave blank to keep the current password.' : 'At least 8 characters. Share it with the user securely.' ?></p>
    <?= field_error($errors, 'password') ?>
  </div>
  <div class="form-actions">
    <a class="btn btn-outline" href="<?= e(url('users')) ?>">Cancel</a>
    <button type="submit" class="btn btn-primary"><?= $editing ? 'Save changes' : 'Add user' ?></button>
  </div>
</form>
<?php layout_end();
