<?php
declare(strict_types=1);

$errors = [];

if (is_post()) {
    csrf_verify();
    $current = (string)($_POST['current'] ?? '');
    $new = (string)($_POST['new'] ?? '');
    $confirm = (string)($_POST['confirm'] ?? '');

    if (!password_verify($current, $user['password_hash'])) {
        $errors['current'] = 'Your current password is incorrect.';
    }
    if (strlen($new) < 8) {
        $errors['new'] = 'Use at least 8 characters.';
    } elseif ($new === $current) {
        $errors['new'] = 'Choose a password different from your current one.';
    }
    if ($new !== $confirm) {
        $errors['confirm'] = 'The passwords do not match.';
    }
    if (!$errors) {
        $uid = $user['id'];
        db_mutate('users', function (array &$rows) use ($uid, $new) {
            foreach ($rows as &$r) {
                if ($r['id'] === $uid) $r['password_hash'] = password_hash($new, PASSWORD_DEFAULT);
            }
        });
        flash('success', 'Your password was changed.');
        redirect('profile');
    }
}

layout_start('My Profile', 'Your account details and password.');
?>
<div class="split split-even">
  <section class="card">
    <h2>Account</h2>
    <dl class="details single">
      <div><dt>Name</dt><dd><?= e($user['name']) ?></dd></div>
      <div><dt>Email</dt><dd><?= e($user['email']) ?></dd></div>
      <div><dt>Role</dt><dd><?= e(ROLE_LABELS[$user['role']]) ?></dd></div>
      <?php if (!empty($user['student_no'])): ?><div><dt>Student number</dt><dd><?= e($user['student_no']) ?></dd></div><?php endif; ?>
      <?php if (!empty($user['department'])): ?><div><dt>Department</dt><dd><?= e($user['department']) ?></dd></div><?php endif; ?>
    </dl>
    <p class="muted small">To change your name, email or role, contact the administrator.</p>
  </section>
  <section class="card">
    <h2>Change password</h2>
    <form method="post" class="form" novalidate>
      <?= csrf_field() ?>
      <div class="field">
        <label for="current">Current password</label>
        <input type="password" id="current" name="current" autocomplete="current-password" required>
        <?= field_error($errors, 'current') ?>
      </div>
      <div class="field">
        <label for="new">New password</label>
        <input type="password" id="new" name="new" autocomplete="new-password" required>
        <p class="hint">At least 8 characters.</p>
        <?= field_error($errors, 'new') ?>
      </div>
      <div class="field">
        <label for="confirm">Confirm new password</label>
        <input type="password" id="confirm" name="confirm" autocomplete="new-password" required>
        <?= field_error($errors, 'confirm') ?>
      </div>
      <div class="form-actions"><button type="submit" class="btn btn-primary">Change password</button></div>
    </form>
  </section>
</div>
<?php layout_end();
