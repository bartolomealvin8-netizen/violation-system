<?php
declare(strict_types=1);

/**
 * Core helpers: constants, storage (JSON files), auth, CSRF, flash, sorting, paging.
 * Storage is isolated in db_read() / db_mutate() so a real database can replace it later.
 */

define('ROOT', __DIR__);
define('DATA_DIR', ROOT . '/data');
define('UPLOAD_DIR', ROOT . '/uploads');
// Data files are .php files that start with this guard, so a browser can never read them.
define('DATA_GUARD', "<?php http_response_code(404); exit; ?>\n");

const APP_NAME = 'Violation Monitoring';
const CAMPUS_NAME = 'Our Lady of Fatima University Antipolo Campus';
const STATUS_LABELS = ['pending' => 'Pending', 'review' => 'Under Review', 'resolved' => 'Resolved'];
const ROLE_LABELS = [
    'student'  => 'Student',
    'guidance' => 'Guidance',
    'dean'     => 'Dean / Program Chair',
    'admin'    => 'Administrator',
];
const MAX_FILE_BYTES = 5242880; // 5 MB
const MAX_FILES = 3;
const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
const PER_PAGE = 10;
const IDLE_SECONDS = 1800;

date_default_timezone_set('Asia/Manila');
foreach ([DATA_DIR, UPLOAD_DIR] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

session_name('olfu_vms');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
session_start();

/* ---------- Small helpers ---------- */

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function g(string $key, string $default = ''): string
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function p(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function url(string $page, array $params = []): string
{
    $params = array_filter($params, fn($v) => $v !== null && $v !== '');
    return 'index.php?' . http_build_query(['page' => $page] + $params);
}

function redirect(string $page, array $params = []): void
{
    header('Location: ' . url($page, $params));
    exit;
}

function now(): string
{
    return date('c');
}

function fmt_date(?string $iso): string
{
    return $iso ? date('M j, Y', strtotime($iso)) : '—';
}

function fmt_datetime(?string $iso): string
{
    return $iso ? date('M j, Y g:i A', strtotime($iso)) : '—';
}

function fmt_size(int $bytes): string
{
    return $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB' : max(1, (int)round($bytes / 1024)) . ' KB';
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- Storage ---------- */

function db_read(string $name): array
{
    $path = DATA_DIR . "/$name.php";
    if (!is_file($path)) {
        return [];
    }
    $raw = (string)file_get_contents($path);
    if (str_starts_with($raw, DATA_GUARD)) {
        $raw = substr($raw, strlen(DATA_GUARD));
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** Read, change and save one data file under an exclusive lock. $fn receives the data by reference. */
function db_mutate(string $name, callable $fn)
{
    $lock = fopen(DATA_DIR . '/.lock', 'c');
    flock($lock, LOCK_EX);
    try {
        $data = db_read($name);
        $result = $fn($data);
        $path = DATA_DIR . "/$name.php";
        $json = DATA_GUARD . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents($path . '.tmp', $json);
        rename($path . '.tmp', $path);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
    return $result;
}

function next_id(array $rows): int
{
    $max = 0;
    foreach ($rows as $r) {
        $max = max($max, (int)($r['id'] ?? 0));
    }
    return $max + 1;
}

function settings(): array
{
    $s = db_read('settings');
    return [
        'departments'     => $s['departments'] ?? [],
        'violation_types' => $s['violation_types'] ?? [],
    ];
}

/* ---------- Auth ---------- */

function current_user(): ?array
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    $cache = null;
    if (empty($_SESSION['uid'])) {
        return null;
    }
    if (time() - ($_SESSION['last_seen'] ?? time()) > IDLE_SECONDS) {
        session_unset();
        flash('info', 'You were signed out after 30 minutes of inactivity. Please sign in again.');
        return null;
    }
    foreach (db_read('users') as $u) {
        if ($u['id'] === $_SESSION['uid'] && $u['active']) {
            $_SESSION['last_seen'] = time();
            return $cache = $u;
        }
    }
    session_unset();
    return null;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = $user['id'];
    $_SESSION['last_seen'] = time();
    db_mutate('users', function (array &$rows) use ($user) {
        foreach ($rows as &$r) {
            if ($r['id'] === $user['id']) {
                $r['last_login'] = now();
            }
        }
    });
}

function user_by_id(int $id): ?array
{
    foreach (db_read('users') as $u) {
        if ($u['id'] === $id) {
            return $u;
        }
    }
    return null;
}

function active_users_by_role(string $role, ?string $department = null): array
{
    return array_values(array_filter(db_read('users'), function ($u) use ($role, $department) {
        return $u['active'] && $u['role'] === $role && ($department === null || ($u['department'] ?? '') === $department);
    }));
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $sent = (string)($_POST['csrf'] ?? '');
    if (!hash_equals(csrf_token(), $sent)) {
        abort_page(419, 'Session expired', 'Your form session has expired. Go back, refresh the page and try again.');
    }
}

/* ---------- Cases and notifications ---------- */

function cases_for_user(array $u): array
{
    $all = db_read('cases');
    switch ($u['role']) {
        case 'student':
            return array_values(array_filter($all, fn($c) => $c['reporter_id'] === $u['id']));
        case 'dean':
            // Deans see their department's incidents once Guidance has started reviewing them.
            return array_values(array_filter($all, fn($c) => $c['department'] === ($u['department'] ?? '') && $c['status'] !== 'pending'));
        case 'guidance':
            return $all;
    }
    return [];
}

function find_case(array $u, int $id): ?array
{
    foreach (cases_for_user($u) as $c) {
        if ($c['id'] === $id) {
            return $c;
        }
    }
    return null;
}

function case_ref(array $c): string
{
    return $c['ref'];
}

function notify(array $userIds, string $message, ?string $link = null): void
{
    if (!$userIds) {
        return;
    }
    db_mutate('notifications', function (array &$rows) use ($userIds, $message, $link) {
        $id = next_id($rows);
        foreach (array_unique($userIds) as $uid) {
            $rows[] = ['id' => $id++, 'user_id' => $uid, 'message' => $message, 'link' => $link, 'read' => false, 'created_at' => now()];
        }
    });
}

function unread_count(int $userId): int
{
    $n = 0;
    foreach (db_read('notifications') as $row) {
        if ($row['user_id'] === $userId && !$row['read']) {
            $n++;
        }
    }
    return $n;
}

function count_active_admins(): int
{
    return count(active_users_by_role('admin'));
}

/* ---------- Tables: sorting and paging ---------- */

function sort_rows(array $rows, string $sort, string $dir, array $keys): array
{
    if (!isset($keys[$sort])) {
        return $rows;
    }
    $f = $keys[$sort];
    usort($rows, function ($a, $b) use ($f, $dir) {
        $x = $f($a);
        $y = $f($b);
        $c = is_string($x) ? strcasecmp($x, (string)$y) : ($x <=> $y);
        return $dir === 'desc' ? -$c : $c;
    });
    return $rows;
}

function th_sort(string $label, string $key, string $curSort, string $curDir): string
{
    $params = $_GET;
    unset($params['p']);
    $active = $curSort === $key;
    $params['sort'] = $key;
    $params['dir'] = ($active && $curDir === 'asc') ? 'desc' : 'asc';
    $arrow = $active ? ($curDir === 'asc' ? '▲' : '▼') : '↕';
    $aria = $active ? ($curDir === 'asc' ? 'ascending' : 'descending') : 'none';
    return '<th aria-sort="' . $aria . '"><a class="sort' . ($active ? ' is-active' : '') . '" href="index.php?'
        . e(http_build_query($params)) . '">' . e($label) . ' <span aria-hidden="true">' . $arrow . '</span></a></th>';
}

function paginate(array $rows, int $per = PER_PAGE): array
{
    $total = count($rows);
    $pages = max(1, (int)ceil($total / $per));
    $page = min(max(1, (int)($_GET['p'] ?? 1)), $pages);
    return [
        array_slice($rows, ($page - 1) * $per, $per),
        ['page' => $page, 'pages' => $pages, 'total' => $total,
         'from' => $total ? ($page - 1) * $per + 1 : 0, 'to' => min($total, $page * $per)],
    ];
}

function pager(array $m): string
{
    if ($m['total'] === 0) {
        return '';
    }
    $link = function (int $page, string $label, bool $enabled): string {
        if (!$enabled) {
            return '<span class="btn btn-outline btn-sm is-disabled" aria-disabled="true">' . $label . '</span>';
        }
        $params = $_GET;
        $params['p'] = $page;
        return '<a class="btn btn-outline btn-sm" href="index.php?' . e(http_build_query($params)) . '">' . $label . '</a>';
    };
    return '<nav class="pager" aria-label="Pagination"><span class="muted">Showing ' . $m['from'] . '–' . $m['to'] . ' of ' . $m['total']
        . '</span><span class="pager-buttons">' . $link($m['page'] - 1, 'Previous', $m['page'] > 1)
        . '<span class="muted">Page ' . $m['page'] . ' of ' . $m['pages'] . '</span>'
        . $link($m['page'] + 1, 'Next', $m['page'] < $m['pages']) . '</span></nav>';
}

/* ---------- Uploads ---------- */

/** Validates uploaded files for $field and returns a list of accepted files. Adds to $errors on problems. */
function collect_uploads(string $field, array &$errors): array
{
    $out = [];
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]['name'])) {
        return $out;
    }
    $f = $_FILES[$field];
    for ($i = 0, $n = count($f['name']); $i < $n; $i++) {
        if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $name = (string)$f['name'][$i];
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($f['error'][$i] !== UPLOAD_ERR_OK) {
            $errors[$field] = '“' . $name . '” could not be uploaded. Try a smaller file.';
        } elseif (!in_array($ext, ALLOWED_EXT, true)) {
            $errors[$field] = '“' . $name . '” is not allowed. Use JPG, PNG, PDF, DOC or DOCX.';
        } elseif ($f['size'][$i] > MAX_FILE_BYTES) {
            $errors[$field] = '“' . $name . '” is larger than 5 MB.';
        } elseif (!is_uploaded_file($f['tmp_name'][$i])) {
            $errors[$field] = '“' . $name . '” could not be uploaded.';
        } else {
            $out[] = ['tmp' => $f['tmp_name'][$i], 'name' => $name, 'ext' => $ext, 'size' => (int)$f['size'][$i]];
        }
    }
    if (count($out) > MAX_FILES) {
        $errors[$field] = 'Attach at most ' . MAX_FILES . ' files.';
    }
    return $out;
}

/** Moves accepted uploads to private storage and returns the attachment records. */
function store_uploads(array $files): array
{
    $saved = [];
    foreach ($files as $f) {
        $stored = bin2hex(random_bytes(16)) . '.dat';
        if (move_uploaded_file($f['tmp'], UPLOAD_DIR . '/' . $stored)) {
            $clean = preg_replace('/[^A-Za-z0-9._ -]/', '_', $f['name']);
            $saved[] = ['name' => $clean, 'stored' => $stored, 'size' => $f['size']];
        }
    }
    return $saved;
}
