<?php
declare(strict_types=1);

// Sends an attachment only to users who are allowed to view the report.
$case = find_case($user, (int)g('case'));
$i = (int)g('i', '-1');
if (!$case || !isset($case['attachments'][$i])) {
    abort_page(404, 'File not found', 'This file does not exist or you do not have access to it.');
}
$att = $case['attachments'][$i];
$path = UPLOAD_DIR . '/' . basename($att['stored']);
if (!is_file($path)) {
    abort_page(404, 'File not found', 'This file is no longer available.');
}
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $att['name']) . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
