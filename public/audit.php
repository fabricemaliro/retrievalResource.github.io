<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\AccessControl;
use App\App;
use App\Logger;
use App\Search\Indexer;

$user = require_login();
if (!AccessControl::isAdmin($user)) {
    abort(403, __('The audit log is reserved for the Records Manager.'));
}
$db = App::db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reindex') {
    csrf_check();
    $stats = (new Indexer($db))->rebuild();
    Logger::audit($user, 'reindex', null, $stats);
    flash(__('Index rebuilt: {documents} documents, {terms} terms, {postings} postings in {seconds} s.', $stats), 'success');
    redirect('audit.php');
}

$actions = $db->query('SELECT DISTINCT action FROM audit_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
$filter = in_array($_GET['action'] ?? '', $actions, true) ? $_GET['action'] : '';
$sql = 'SELECT a.*, u.full_name, u.role, d.reference_no, d.title, d.title_fr FROM audit_logs a
          LEFT JOIN users u ON u.id = a.user_id LEFT JOIN documents d ON d.id = a.document_id';
$args = [];
if ($filter !== '') {
    $sql .= ' WHERE a.action = ?';
    $args[] = $filter;
}
$stmt = $db->prepare($sql . ' ORDER BY a.id DESC LIMIT 200');
$stmt->execute($args);
$logs = $stmt->fetchAll();

$index = $db->query('SELECT (SELECT COUNT(*) FROM documents) AS docs, (SELECT COUNT(*) FROM terms) AS terms, (SELECT COUNT(*) FROM postings) AS postings')->fetch();

$actionLabel = static fn (string $a): string => __(ucfirst(str_replace('_', ' ', $a)));
render_header(__('Audit log'), 'audit');
?>
<div class="page-head with-action">
  <div>
    <p class="eyebrow"><?= e(__('Accountability')) ?></p>
    <h1><?= e(__('Audit log')) ?></h1>
    <p class="muted"><?= e(__('Every sign-in, view, download, upload and refused access is recorded. Showing the latest 200 events.')) ?></p>
  </div>
  <form method="post" action="audit.php" class="no-print">
    <?= csrf_field() ?><input type="hidden" name="action" value="reindex">
    <button class="btn" type="submit"><?= e(__('Rebuild search index')) ?></button>
    <p class="small muted"><?= e(__('Index: {docs} docs · {terms} terms · {postings} postings', ['docs' => format_number((int) $index['docs']), 'terms' => format_number((int) $index['terms']), 'postings' => format_number((int) $index['postings'])])) ?></p>
  </form>
</div>

<form class="filter-row" method="get" action="audit.php">
  <label for="action"><?= e(__('Event')) ?></label>
  <select id="action" name="action" data-autosubmit>
    <option value=""><?= e(__('All events')) ?></option>
    <?php foreach ($actions as $a): ?><option value="<?= e($a) ?>" <?= $filter === $a ? 'selected' : '' ?>><?= e($actionLabel($a)) ?></option><?php endforeach; ?>
  </select>
  <noscript><button class="btn" type="submit"><?= e(__('Filter')) ?></button></noscript>
</form>

<div class="card table-wrap">
  <table class="table">
    <thead><tr><th><?= e(__('When')) ?></th><th><?= e(__('User')) ?></th><th><?= e(__('Event')) ?></th><th><?= e(__('Document')) ?></th><th>IP</th></tr></thead>
    <tbody>
      <?php foreach ($logs as $l): ?>
        <tr class="<?= $l['action'] === 'access_denied' || $l['action'] === 'login_failed' ? 'warn' : '' ?>">
          <td class="nowrap"><?= format_date($l['created_at']) ?></td>
          <td><?= e($l['full_name'] ?? '—') ?></td>
          <td><span class="tag"><?= e($actionLabel($l['action'])) ?></span></td>
          <td><?php if ($l['document_id']): ?><a href="<?= e(url('document.php', ['id' => $l['document_id']])) ?>"><?= e($l['reference_no']) ?></a> <span class="small muted"><?= e(mb_strimwidth(\App\Doc::t($l, 'title'), 0, 60, '…')) ?></span><?php elseif ($l['details']): ?><span class="small muted"><?= e(mb_strimwidth($l['details'], 0, 80, '…')) ?></span><?php endif; ?></td>
          <td class="small muted"><?= e($l['ip']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$logs): ?><tr><td colspan="5" class="muted"><?= e(__('No events yet.')) ?></td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php
render_footer();
