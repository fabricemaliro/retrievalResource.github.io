<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\AccessControl;
use App\App;
use App\Catalog;
use App\Doc;
use App\I18n;
use App\Logger;

$user = require_login();
$stmt = App::db()->prepare('SELECT d.*, dp.name AS department_name FROM documents d JOIN departments dp ON dp.id = d.department_id WHERE d.id = ?');
$stmt->execute([(int) ($_GET['id'] ?? 0)]);
$doc = $stmt->fetch();

if (!$doc || !AccessControl::canView($user, $doc)) {
    if ($doc) {
        Logger::audit($user, 'access_denied', (int) $doc['id'], ['download' => true]);
    }
    abort(404, __('This document does not exist or you are not authorised to download it.'));
}

Logger::audit($user, 'download', (int) $doc['id'], ['lang' => I18n::lang()]);
$safeName = static fn (string $name): string => preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?: 'document';

header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

// Uploaded file: the original is served as it is (its language is the language it was written in).
$path = $doc['file_path'] ? App::config('upload_dir') . '/' . basename($doc['file_path']) : null;
if ($path && is_file($path)) {
    header('Content-Type: ' . ($doc['file_mime'] ?: 'application/octet-stream'));
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: attachment; filename="' . $safeName($doc['file_name']) . '"');
    readfile($path);
    exit;
}

// Text record: exported in the interface language, with its metadata header.
$text = implode("\n", [
    __(App::config('institution')) . ' — ' . __($doc['department_name']),
    str_repeat('=', 60),
    __('Reference') . ' : ' . $doc['reference_no'],
    __('Title') . ' : ' . Doc::t($doc, 'title'),
    __('Type') . ' : ' . Catalog::typeLabel($doc['document_type']),
    __('Date') . ' : ' . format_date($doc['date_created']),
    __('Access level') . ' : ' . Catalog::accessLabel($doc['access_level']),
    __('Downloaded by {name} on {date}', ['name' => $user['full_name'], 'date' => format_date(date('Y-m-d H:i:s'))]),
    str_repeat('=', 60),
    '',
    Doc::t($doc, 'content'),
    '',
]);
if (I18n::lang() === 'en') {
    $text = str_replace(' : ', ': ', $text);
}
$suffix = Doc::available($doc) ? '-' . I18n::lang() : '';
header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $safeName(str_replace('/', '-', $doc['reference_no'])) . $suffix . '.txt"');
echo $text;
