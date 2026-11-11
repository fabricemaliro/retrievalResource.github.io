<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\App;
use App\Auth;

$next = (string) ($_GET['next'] ?? $_POST['next'] ?? '');
$safeNext = (str_starts_with($next, '/') && !str_starts_with($next, '//') && !str_contains($next, 'login.php')) ? $next : 'index.php';

if (App::user()) {
    redirect($safeNext);
}

$error = null;
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = (string) ($_POST['username'] ?? '');
    $result = Auth::attempt($username, (string) ($_POST['password'] ?? ''));
    if (is_array($result)) {
        redirect($safeNext);
    }
    $error = $result;
}

render_header(__('Sign in'));
?>
<section class="login">
  <div class="login-intro">
    <p class="eyebrow"><?= e(__('Records Office')) ?> · <?= e(__('Information Retrieval System')) ?></p>
    <h1><?= e(__('Find the right file in seconds, not hours.')) ?></h1>
    <p><?= e(__('Search thousands of student and administrative records by name, registration number, reference number, type, department or date. You only ever see the documents your role allows.')) ?></p>
  </div>

  <form class="card login-card" method="post" action="login.php" novalidate>
    <h2><?= e(__('Sign in')) ?></h2>
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e($next) ?>">
    <?php if ($error): ?><div class="flash flash-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <label for="username"><?= e(__('Username')) ?></label>
    <input id="username" name="username" autocomplete="username" required autofocus value="<?= e($username) ?>">
    <label for="password"><?= e(__('Password')) ?></label>
    <input id="password" name="password" type="password" autocomplete="current-password" required>
    <button class="btn btn-primary btn-block" type="submit"><?= e(__('Sign in')) ?></button>
  </form>

  <?php if (App::config('demo_mode')): ?>
  <div class="card demo-accounts">
    <h2><?= e(__('Demo accounts')) ?></h2>
    <p class="muted"><?= __('Password for every account: {password} — sign in with each role to see access control at work.', ['password' => '<code>Password123!</code>']) ?></p>
    <table class="table compact">
      <thead><tr><th><?= e(__('Username')) ?></th><th><?= e(__('Role')) ?></th><th><?= e(__('Can see')) ?></th></tr></thead>
      <tbody>
        <tr><td><code>manager</code></td><td><?= e(__('Records Manager')) ?></td><td><?= e(__('Everything, including restricted files')) ?></td></tr>
        <tr><td><code>officer</code></td><td><?= e(__('Records Officer')) ?></td><td><?= e(__('All except restricted')) ?></td></tr>
        <tr><td><code>fci.admin</code></td><td><?= e(__('Faculty of Computing staff')) ?></td><td><?= e(__('Public, internal + confidential files of the Faculty of Computing')) ?></td></tr>
        <tr><td><code>finance</code></td><td><?= e(__('Finance staff')) ?></td><td><?= e(__('Public, internal + confidential files of Finance')) ?></td></tr>
        <tr><td><code>student</code></td><td><?= e(__('Student (Brian Okello)')) ?></td><td><?= e(__('Public documents + his own records')) ?></td></tr>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>
<?php
render_footer();
