<?php
declare(strict_types=1);

if (is_post()) {
    csrf_verify();
    $_SESSION = [];
    session_regenerate_id(true);
    flash('success', 'You have been signed out.');
}
redirect('login');
