<?php
declare(strict_types=1);

require __DIR__ . '/core.php';
require __DIR__ . '/layout.php';

$page = preg_replace('/[^a-z_]/', '', (string)($_GET['page'] ?? 'dashboard'));

// page => allowed roles (null = any signed-in user, 'public' = no sign-in needed)
$routes = [
    'login'         => 'public',
    'setup'         => 'public',
    'logout'        => 'public',
    'dashboard'     => null,
    'report_new'    => ['student'],
    'cases'         => ['student', 'guidance', 'dean'],
    'case'          => ['student', 'guidance', 'dean'],
    'file'          => ['student', 'guidance', 'dean'],
    'notifications' => ['student', 'guidance', 'dean'],
    'users'         => ['admin'],
    'user_form'     => ['admin'],
    'settings'      => ['admin'],
    'reports'       => ['guidance', 'dean', 'admin'],
    'profile'       => null,
];

$user = current_user();
$hasUsers = count(db_read('users')) > 0;

if (!$hasUsers && $page !== 'setup') {
    redirect('setup');
}
if ($hasUsers && $page === 'setup') {
    redirect($user ? 'dashboard' : 'login');
}
if (!array_key_exists($page, $routes)) {
    if (!$user) {
        redirect('login');
    }
    abort_page(404, 'Page not found', 'The page you are looking for does not exist.');
}

$access = $routes[$page];
if ($access !== 'public') {
    if (!$user) {
        redirect('login');
    }
    if (is_array($access) && !in_array($user['role'], $access, true)) {
        abort_page(403, 'No access', 'Your role does not have access to this page.');
    }
}

require __DIR__ . '/' . $page . '.php';
