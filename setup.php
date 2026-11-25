<?php
declare(strict_types=1);

// First-run setup: creates the first administrator and the starter settings.
$errors = [];
$v = ['name' => '', 'email' => ''];

if (is_post()) {
    csrf_verify();
    $v['name'] = p('name');
    $v['email'] = strtolower(p('email'));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm'] ?? '');

    if (mb_strlen($v['name']) < 2 || mb_strlen($v['name']) > 80) {
        $errors['name'] = 'Enter your full name (2–80 characters).';
    }
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors['password'] = 'Use at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors['confirm'] = 'The passwords do not match.';
    }

    if (!$errors) {
        db_mutate('users', function (array &$rows) use ($v, $password) {
            if ($rows) {
                return;
            }
            $rows[] = [
                'id' => 1, 'name' => $v['name'], 'email' => $v['email'], 'role' => 'admin',
                'student_no' => '', 'department' => '', 'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'active' => true, 'created_at' => now(), 'last_login' => null,
            ];
        });
        db_mutate('settings', function (array &$s) {
            if ($s) {
                return;
            }
            // Starter lists. The administrator can edit these in System Settings.
            $s = [
                'departments' => [
                    'College of Arts and Sciences', 'College of Business and Accountancy', 'College of Computer Studies',
                    'College of Criminology', 'College of Education', 'College of Nursing',
                ],
                'violation_types' => [
                    'Academic dishonesty', 'Bullying or harassment', 'Dress code violation',
                    'Fighting or violence', 'Property damage', 'Smoking or vaping', 'Other',
                ],
            ];
        });
        flash('success', 'Administrator account created. Please sign in.');
        redirect('login');
    }
}

auth_start('Set up', 'Welcome', 'Create the administrator account to start using the system.');
?>
<form method="post" class="stack" novalidate>
  <?= csrf_field() ?>
  <div class="field">
    <label for="name">Full name</label>
    <input type="text" id="name" name="name" value="<?= e($v['name']) ?>" required autofocus>
    <?= field_error($errors, 'name') ?>
  </div>
  <div class="field">
    <label for="email">Email</label>
    <input type="email" id="email" name="email" value="<?= e($v['email']) ?>" required>
    <?= field_error($errors, 'email') ?>
  </div>
  <div class="field">
    <label for="password">Password</label>
    <input type="password" id="password" name="password" autocomplete="new-password" required>
    <p class="hint">At least 8 characters.</p>
    <?= field_error($errors, 'password') ?>
  </div>
  <div class="field">
    <label for="confirm">Confirm password</label>
    <input type="password" id="confirm" name="confirm" autocomplete="new-password" required>
    <?= field_error($errors, 'confirm') ?>
  </div>
  <button type="submit" class="btn btn-primary btn-block">Create administrator account</button>
</form>
<?php auth_end();
