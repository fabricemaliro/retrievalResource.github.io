<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\AccessControl;
use App\App;
use App\Catalog;
use App\Doc;
use App\Logger;
use App\Search\Highlighter;
use App\Search\QueryParser;
use App\Search\SearchEngine;

$user = require_login();
$db = App::db();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare('SELECT d.*, dp.name AS department_name, u.full_name AS created_by_name
                        FROM documents d JOIN departments dp ON dp.id = d.department_id
                        LEFT JOIN users u ON u.id = d.created_by WHERE d.id = ?');
$stmt->execute([$id]);
$doc = $stmt->fetch();

// Same message whether the document is missing or forbidden: do not reveal that a restricted file exists.
if (!$doc || !AccessControl::canView($user, $doc)) {
    if ($doc) {
        Logger::audit($user, 'access_denied', (int) $doc['id']);
    }
    abort(404, __('This document does not exist or you are not authorised to open it.'));
}

if (($sid = (int) ($_GET['sid'] ?? 0)) && ($pos = (int) ($_GET['pos'] ?? 0))) {
    Logger::click($sid, $user, (int) $doc['id'], $pos);
}
Logger::audit($user, 'view', (int) $doc['id']);

$q = mb_substr((string) ($_GET['q'] ?? ''), 0, 200);
$pattern = $q !== '' ? Highlighter::pattern(QueryParser::parse($q)) : null;
$back = (string) ($_GET['back'] ?? '');
$back = str_starts_with($back, 'search.php?') ? $back : '';

$related = (new SearchEngine($db))->related($doc, $user, 5);

$history = [];
if (AccessControl::isAdmin($user)) {
    $h = $db->prepare('SELECT a.action, a.created_at, a.ip, u.full_name FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
                        WHERE a.document_id = ? ORDER BY a.id DESC LIMIT 10');
    $h->execute([$doc['id']]);
    $history = $h->fetchAll();
}

$title = Doc::t($doc, 'title');
$description = Doc::t($doc, 'description');
$keywords = Doc::t($doc, 'keywords');
$content = Doc::t($doc, 'content');
$languages = array_filter([$doc['title'] !== '' ? __('English') : null, ($doc['title_fr'] ?? '') !== '' ? __('French') : null]);

$meta = [
    __('Reference number') => $doc['reference_no'],
    __('Document type') => Catalog::typeLabel($doc['document_type']),
    __('Department') => __($doc['department_name']),
    __('Date created') => format_date($doc['date_created']),
    __('Academic year') => $doc['academic_year'],
    __('Student') => $doc['student_name'] ? $doc['student_name'] . ' (' . $doc['student_no'] . ')' : null,
    __('Programme') => $doc['programme'] ? __($doc['programme']) : null,
    __('Author / signatory') => Doc::t($doc, 'author'),
    __('Retention period') => $doc['retention_period'] ? __($doc['retention_period']) : null,
    __('Status') => $doc['status'] === 'archived' ? __('Archived') : __('Active'),
    __('Available in') => implode(' · ', $languages),
    __('File') => $doc['file_name'] ? $doc['file_name'] . ' · ' . human_size((int) $doc['file_size']) : __('Text record (no scanned file)'),
    __('Added by') => $doc['created_by_name'],
];
$actionLabels = ['view' => __('viewed'), 'download' => __('downloaded'), 'upload' => __('added'), 'access_denied' => __('was refused access')];

render_header($title, 'search');
?>
<nav class="breadcrumb" aria-label="<?= e(__('Breadcrumb')) ?>">
  <?php if ($back): ?><a href="<?= e($back) ?>">← <?= e(__('Back to results')) ?></a><span>/</span><?php else: ?><a href="index.php"><?= e(__('Search')) ?></a><span>/</span><?php endif; ?>
  <span><?= e(Catalog::typeLabel($doc['document_type'])) ?></span>
</nav>

<div class="doc-layout">
  <article class="card doc">
    <div class="result-tags">
      <span class="type-badge"><?= e(Catalog::typeLabel($doc['document_type'])) ?></span>
      <?= access_badge($doc['access_level']) ?>
      <?php if ($doc['status'] === 'archived'): ?><span class="tag tag-archived"><?= e(__('Archived')) ?></span><?php endif; ?>
      <span class="ref"><?= e($doc['reference_no']) ?></span>
    </div>
    <?php if (!Doc::available($doc)): ?>
      <p class="lang-note"><?= e(__('This document is only available in {language}; it is shown in its original language.', ['language' => Doc::otherLanguageName()])) ?></p>
    <?php endif; ?>
    <h1><?= Highlighter::highlight($title, $pattern) ?></h1>
    <?php if ($description !== ''): ?><p class="lead"><?= Highlighter::highlight($description, $pattern) ?></p><?php endif; ?>

    <?php if ($keywords !== ''): ?>
      <div class="keywords">
        <?php foreach (array_filter(array_map('trim', explode(',', $keywords))) as $kw): ?>
          <a class="chip chip-light" href="<?= e(url('search.php', ['q' => $kw])) ?>"><?= e($kw) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($pattern): ?><p class="small muted"><?= e(__('Your search terms are highlighted.')) ?></p><?php endif; ?>
    <div class="doc-body"><?= nl2br(Highlighter::highlight($content !== '' ? $content : __('No text content was extracted from this file. Download it to read it.'), $pattern)) ?></div>
  </article>

  <aside class="stack">
    <section class="card">
      <a class="btn btn-primary btn-block" href="<?= e(url('download.php', ['id' => $doc['id']])) ?>"><?= e(__('Download document')) ?></a>
      <p class="small muted center"><?= e(__('Downloads are recorded in the audit log.')) ?></p>
    </section>

    <section class="card">
      <h2><?= e(__('Metadata')) ?></h2>
      <dl class="meta">
        <?php foreach ($meta as $label => $value): if ($value === null || $value === '') continue; ?>
          <dt><?= e($label) ?></dt><dd><?= e($value) ?></dd>
        <?php endforeach; ?>
        <dt><?= e(__('Access')) ?></dt><dd><?= access_badge($doc['access_level']) ?><br><span class="small muted"><?= e(Catalog::accessDescription($doc['access_level'])) ?></span></dd>
      </dl>
    </section>

    <?php if ($related): ?>
    <section class="card">
      <h2><?= e(__('Related documents')) ?></h2>
      <ul class="related">
        <?php foreach ($related as $r): ?>
          <li><a href="<?= e(url('document.php', ['id' => $r['id']])) ?>"><?= e(Doc::t($r, 'title')) ?></a>
            <span class="small muted"><?= e(Catalog::typeLabel($r['document_type'])) ?> · <?= format_date($r['date_created']) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>

    <?php if ($history): ?>
    <section class="card">
      <h2><?= e(__('Access history')) ?></h2>
      <ul class="history">
        <?php foreach ($history as $h): ?>
          <li><strong><?= e($h['full_name'] ?? __('Unknown')) ?></strong> <?= e($actionLabels[$h['action']] ?? str_replace('_', ' ', $h['action'])) ?><br><span class="small muted"><?= format_date($h['created_at']) ?> · <?= e($h['ip']) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>
  </aside>
</div>
<?php
render_footer();
