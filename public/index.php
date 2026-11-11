<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\AccessControl;
use App\App;
use App\Catalog;

$user = require_login();
$db = App::db();

// Document types the user can actually reach, with counts
[$acl, $args] = AccessControl::where($user, 'd');
$stmt = $db->prepare("SELECT d.document_type, COUNT(*) AS n FROM documents d WHERE $acl AND d.status = 'active' GROUP BY d.document_type ORDER BY n DESC");
$stmt->execute($args);
$types = $stmt->fetchAll();
$totalVisible = array_sum(array_column($types, 'n'));

$recent = $db->prepare("SELECT query, MAX(created_at) AS last FROM search_logs WHERE user_id = ? AND query <> '' GROUP BY query ORDER BY last DESC LIMIT 6");
$recent->execute([$user['id']]);
$recent = $recent->fetchAll();

$departments = Catalog::departments($db);
$canSee = array_values(array_filter(array_keys(Catalog::ACCESS), static function (string $level) use ($user): bool {
    return match ($user['role']) {
        'admin' => true,
        'records_officer', 'dept_staff' => $level !== 'restricted',
        'student' => in_array($level, ['public', 'confidential'], true),
        default => false,
    };
}));
$example = \App\I18n::lang() === 'fr' ? 'politique exonération frais' : 'fee waiver policy';

render_header(__('Search'), 'search');
?>
<section class="hero">
  <p class="eyebrow"><?= e(__('Search {count} records available to you', ['count' => format_number($totalVisible)])) ?></p>
  <h1><?= e(__('What are you looking for?')) ?></h1>

  <form class="search-hero" method="get" action="search.php" role="search">
    <label class="sr-only" for="q"><?= e(__('Search records')) ?></label>
    <div class="searchbox">
      <svg aria-hidden="true" viewBox="0 0 24 24" width="22" height="22"><circle cx="10.5" cy="10.5" r="6.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="m15.5 15.5 5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      <input id="q" name="q" type="search" autofocus autocomplete="off" placeholder="<?= e(__('Name, registration no., reference no. or keywords')) ?>">
      <button class="btn btn-primary" type="submit"><?= e(__('Search')) ?></button>
    </div>

    <details class="advanced">
      <summary><?= e(__('Advanced search')) ?></summary>
      <div class="advanced-grid">
        <div><label for="ref"><?= e(__('Reference number')) ?></label><input id="ref" name="ref" placeholder="LU/HR/2023/0042"></div>
        <div><label for="student_no"><?= e(__('Registration number')) ?></label><input id="student_no" name="student_no" placeholder="21/U/04567"></div>
        <div><label for="title"><?= e(__('Title contains')) ?></label><input id="title" name="title" placeholder="<?= e(__('Senate meeting')) ?>"></div>
        <div><label for="author"><?= e(__('Author / signatory')) ?></label><input id="author" name="author" placeholder="<?= e(__('Academic Registrar')) ?>"></div>
        <div>
          <label for="type"><?= e(__('Document type')) ?></label>
          <select id="type" name="type[]">
            <option value=""><?= e(__('Any type')) ?></option>
            <?php foreach (array_keys(Catalog::TYPES) as $key): ?><option value="<?= e($key) ?>"><?= e(Catalog::typeLabel($key)) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="dept"><?= e(__('Department')) ?></label>
          <select id="dept" name="dept[]">
            <option value=""><?= e(__('Any department')) ?></option>
            <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div><label for="date_from"><?= e(__('Created from')) ?></label><input id="date_from" name="date_from" type="date"></div>
        <div><label for="date_to"><?= e(__('Created to')) ?></label><input id="date_to" name="date_to" type="date"></div>
      </div>
      <button class="btn" type="submit"><?= e(__('Search with these criteria')) ?></button>
    </details>
  </form>

  <p class="tips">
    <?= e(__('Tips:')) ?> <code>"<?= e(__('exact phrase')) ?>"</code> · <code>-<?= e(__('word')) ?></code> <?= e(__('to exclude')) ?> · <code>admis*</code> <?= e(__('for word beginnings')) ?> ·
    <?= e(__('paste a reference number such as')) ?> <code>LU/HR/2023/0042</code> · <?= e(__('search in English or French')) ?>
  </p>
</section>

<div class="home-grid">
  <section class="card">
    <h2><?= e(__('Browse by document type')) ?></h2>
    <div class="type-grid">
      <?php foreach ($types as $t): ?>
        <a class="type-tile" href="<?= e(url('search.php', ['type' => [$t['document_type']]])) ?>">
          <span><?= e(Catalog::typeLabel($t['document_type'])) ?></span><strong><?= (int) $t['n'] ?></strong>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <aside class="stack">
    <section class="card">
      <h2><?= e(__('Your recent searches')) ?></h2>
      <?php if ($recent): ?>
        <ul class="linklist">
          <?php foreach ($recent as $r): ?><li><a href="<?= e(url('search.php', ['q' => $r['query']])) ?>"><?= e($r['query']) ?></a></li><?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="muted"><?= e(__('Nothing yet. Try')) ?> <a href="<?= e(url('search.php', ['q' => $example])) ?>"><?= e($example) ?></a>.</p>
      <?php endif; ?>
    </section>
    <section class="card">
      <h2><?= e(__('What your role can see')) ?></h2>
      <p class="muted"><?= e(__('Signed in as')) ?> <strong><?= e(Catalog::roleLabel($user['role'])) ?></strong><?= $user['department_name'] ? ' · ' . e(__($user['department_name'])) : '' ?>.</p>
      <ul class="access-list">
        <?php foreach (array_keys(Catalog::ACCESS) as $level): ?>
          <li class="<?= in_array($level, $canSee, true) ? '' : 'denied' ?>"><?= access_badge($level) ?> <span><?= e(Catalog::accessDescription($level)) ?></span></li>
        <?php endforeach; ?>
      </ul>
      <?php if ($user['role'] === 'dept_staff'): ?><p class="small muted"><?= e(__('Confidential documents are limited to your department.')) ?></p><?php endif; ?>
      <?php if ($user['role'] === 'student'): ?><p class="small muted"><?= e(__('Confidential documents are limited to your own records.')) ?></p><?php endif; ?>
    </section>
  </aside>
</div>
<?php
render_footer();
