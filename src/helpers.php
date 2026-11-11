<?php

declare(strict_types=1);

use App\AccessControl;
use App\App;
use App\Catalog;
use App\I18n;

/** Translate an interface string (the English text is the key). */
function __(string $text, array $params = []): string
{
    return I18n::translate($text, $params);
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Builds "page.php?a=1&b[]=2", dropping empty values. */
function url(string $path, array $params = []): string
{
    $params = array_filter($params, static fn ($v) => $v !== '' && $v !== null && $v !== [] && $v !== false);
    $query = http_build_query($params);
    return $path . ($query !== '' ? '?' . $query : '');
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit(__('This form has expired. Please go back, refresh the page and try again.'));
    }
}

function flash(?string $message = null, string $type = 'info'): ?array
{
    if ($message !== null) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
        return null;
    }
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function require_login(): array
{
    $user = App::user();
    if (!$user) {
        redirect(url('login.php', ['next' => $_SERVER['REQUEST_URI'] ?? '']));
    }
    return $user;
}

function require_manager(): array
{
    $user = require_login();
    if (!AccessControl::canManage($user)) {
        abort(403, __('This page is reserved for Records Office staff.'));
    }
    return $user;
}

function abort(int $status, string $message): never
{
    http_response_code($status);
    $title = $status === 403 ? __('Access denied') : __('Not found');
    render_header($title);
    echo '<section class="card narrow center"><p class="eyebrow">' . e(__('Error {code}', ['code' => $status])) . '</p><h1>'
        . e($title) . '</h1><p>' . e($message) . '</p><p><a class="btn" href="index.php">' . e(__('Back to search')) . '</a></p></section>';
    render_footer();
    exit;
}

/** "4 Nov 2026" / "4 nov. 2026" (with the time when the value has one). */
function format_date(?string $date): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    if (!$ts) {
        return e($date);
    }
    $withTime = strlen($date) > 10;
    if (I18n::lang() === 'fr') {
        $months = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
        return date('j', $ts) . ' ' . $months[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts) . ($withTime ? ' à ' . date('H:i', $ts) : '');
    }
    return date($withTime ? 'j M Y, H:i' : 'j M Y', $ts);
}

function format_number(float $value, int $decimals = 0): string
{
    return I18n::lang() === 'fr' ? number_format($value, $decimals, ',', ' ') : number_format($value, $decimals);
}

function access_badge(string $level): string
{
    return '<span class="access access-' . e($level) . '" title="' . e(Catalog::accessDescription($level)) . '">' . e(Catalog::accessLabel($level)) . '</span>';
}

function human_size(?int $bytes): string
{
    if (!$bytes) {
        return '—';
    }
    $units = I18n::lang() === 'fr' ? ['o', 'Ko', 'Mo', 'Go'] : ['B', 'KB', 'MB', 'GB'];
    $i = (int) floor(log($bytes, 1024));
    return format_number($bytes / (1024 ** $i), 1) . ' ' . $units[$i];
}

function language_switcher(): string
{
    $html = '<div class="lang-switch" role="group" aria-label="' . e(__('Language')) . '">';
    foreach (I18n::LANGUAGES as $code => $label) {
        $current = I18n::lang() === $code;
        $html .= '<a href="' . e(I18n::switchUrl($code)) . '" hreflang="' . $code . '" lang="' . $code . '" title="' . e($label) . '"'
            . ($current ? ' aria-current="true"' : '') . '>' . strtoupper($code) . '</a>';
    }
    return $html . '</div>';
}

function render_header(string $title, string $active = ''): void
{
    $user = App::user();
    $app = App::config('app_name');
    $nav = [
        'search' => ['index.php', __('Search'), true],
        'upload' => ['upload.php', __('Add document'), AccessControl::canManage($user)],
        'evaluation' => ['evaluation.php', __('Evaluation'), AccessControl::canManage($user)],
        'audit' => ['audit.php', __('Audit log'), AccessControl::isAdmin($user)],
        'feedback' => ['feedback.php', __('Give feedback'), true],
    ];
    ?><!doctype html>
<html lang="<?= e(I18n::lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e($app) ?></title>
<link rel="stylesheet" href="assets/style.css">
<script src="assets/app.js" defer></script>
</head>
<body>
<a class="skip" href="#main"><?= e(__('Skip to content')) ?></a>
<header class="topbar">
  <div class="wrap topbar-inner">
    <a class="brand" href="index.php">
      <span class="brand-mark" aria-hidden="true">LU</span>
      <span class="brand-text"><strong>Records Finder</strong><small><?= e(__(App::config('institution'))) ?> · <?= e(__('Records Office')) ?></small></span>
    </a>
    <?php if ($user): ?>
    <nav class="nav" aria-label="<?= e(__('Main menu')) ?>">
      <?php foreach ($nav as $key => [$href, $label, $show]): if (!$show) continue; ?>
        <a href="<?= e($href) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <?php endif; ?>
    <div class="userbox">
      <?= language_switcher() ?>
      <?php if ($user): ?>
      <span class="user-name"><?= e($user['full_name']) ?></span>
      <span class="role role-<?= e($user['role']) ?>"><?= e(Catalog::roleLabel($user['role'])) ?></span>
      <form method="post" action="logout.php"><?= csrf_field() ?><button class="linkbtn" type="submit"><?= e(__('Sign out')) ?></button></form>
      <?php endif; ?>
    </div>
  </div>
</header>
<main id="main" class="wrap main">
<?php if ($flash = flash()): ?>
  <div class="flash flash-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
<?php endif;
}

function render_footer(): void
{
    ?>
</main>
<footer class="footer"><div class="wrap">
  <?= e(__(App::config('institution'))) ?> · <?= e(__('Records Office')) ?> · <?= e(__('Information Retrieval System')) ?> ·
  <?= e(__('Need help?')) ?> <a href="mailto:<?= e(App::config('records_office_email')) ?>"><?= e(App::config('records_office_email')) ?></a>
</div></footer>
</body>
</html>
<?php
}
