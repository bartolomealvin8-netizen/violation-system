<?php
declare(strict_types=1);

if ($user) {
    redirect('dashboard');
}

$errors = [];
$email = '';

if (is_post()) {
    csrf_verify();
    $email = strtolower(p('email'));
    $password = (string)($_POST['password'] ?? '');

    if (($_SESSION['lock_until'] ?? 0) > time()) {
        $errors['form'] = 'Too many failed attempts. Please wait a minute and try again.';
    } elseif ($email === '' || $password === '') {
        $errors['form'] = 'Enter your email and password.';
    } else {
        $found = null;
        foreach (db_read('users') as $u) {
            if (strtolower($u['email']) === $email) {
                $found = $u;
            }
        }
        if ($found && password_verify($password, $found['password_hash'])) {
            if (!$found['active']) {
                $errors['form'] = 'This account is deactivated. Please contact the administrator.';
            } else {
                unset($_SESSION['fails'], $_SESSION['lock_until']);
                login_user($found);
                redirect('dashboard');
            }
        } else {
            $_SESSION['fails'] = ($_SESSION['fails'] ?? 0) + 1;
            if ($_SESSION['fails'] >= 5) {
                $_SESSION['lock_until'] = time() + 60;
                $_SESSION['fails'] = 0;
            }
            $errors['form'] = 'Incorrect email or password.';
        }
    }
}

auth_start('Sign in', 'Sign in', 'Use the account created for you by the administrator.');
?>
<?php if (isset($errors['form'])): ?>
  <div class="alert alert-error" role="alert"><?= icon('alert') ?><span><?= e($errors['form']) ?></span></div>
<?php endif; ?>
<form method="post" class="stack" novalidate>
  <?= csrf_field() ?>
  <div class="field">
    <label for="email">Email</label>
    <input type="email" id="email" name="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>
  </div>
  <div class="field">
    <label for="password">Password</label>
    <input type="password" id="password" name="password" autocomplete="current-password" required>
  </div>
  <button type="submit" class="btn btn-primary btn-block">Sign in</button>
  <p class="muted small">Don’t have an account? Contact the system administrator.</p>
</form>
<?php auth_end();
