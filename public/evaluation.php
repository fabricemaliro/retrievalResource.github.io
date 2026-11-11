<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\App;
use App\Catalog;
use App\Evaluation;
use App\I18n;

$user = require_manager();
$eval = new Evaluation(App::db());
$tests = $eval->runTests(I18n::lang());
$sum = Evaluation::summary($tests);
$an = $eval->analytics();
$fb = $eval->feedback();
$matrix = $eval->accessMatrix();

$pct = static fn (float $v): string => format_number($v * 100) . ' %';
if (I18n::lang() === 'en') {
    $pct = static fn (float $v): string => number_format($v * 100) . '%';
}
$dec = static fn (?float $v, int $d = 2): string => $v === null ? '—' : format_number($v, $d);

render_header(__('Evaluation'), 'evaluation');
?>
<div class="page-head with-action">
  <div>
    <p class="eyebrow"><?= e(__('Information retrieval effectiveness')) ?></p>
    <h1><?= e(__('Evaluation report')) ?></h1>
    <p class="muted"><?= e(__('Generated live on {date} from the current index ({count} active documents). Test queries are typed in {language}.', [
        'date' => format_date(date('Y-m-d H:i:s')), 'count' => format_number(array_sum($matrix['totals'])), 'language' => I18n::lang() === 'fr' ? 'français' : 'English',
    ])) ?> <a href="<?= e(I18n::switchUrl(I18n::lang() === 'fr' ? 'en' : 'fr')) ?>"><?= e(__('Run them in the other language')) ?></a></p>
  </div>
  <button class="btn no-print" type="button" onclick="window.print()"><?= e(__('Print / save as PDF')) ?></button>
</div>

<section class="kpis">
  <div class="kpi"><span><?= e(__('Mean Average Precision')) ?></span><strong><?= $dec($sum['map']) ?></strong><small><?= e(__('1.00 = every relevant document ranked first')) ?></small></div>
  <div class="kpi"><span><?= e(__('Mean Reciprocal Rank')) ?></span><strong><?= $dec($sum['mrr']) ?></strong><small><?= e(__('1.00 = first result always relevant')) ?></small></div>
  <div class="kpi"><span><?= e(__('Average recall')) ?></span><strong><?= $pct($sum['recall']) ?></strong><small><?= e(__('share of relevant documents retrieved')) ?></small></div>
  <div class="kpi"><span><?= e(__('Steps to a document')) ?></span><strong><?= $dec($sum['steps'], 1) ?></strong><small><?= e(__('average actions, query → open document')) ?></small></div>
  <div class="kpi"><span><?= e(__('Average response')) ?></span><strong><?= $dec($sum['ms'], 1) ?> ms</strong><small><?= e(__('search engine time per query')) ?></small></div>
</section>

<section class="card">
  <h2><?= e(__('1. Test 11 queries with relevance judgements')) ?></h2>
  <p class="muted"><?= e(__('Five realistic information needs of the records office users. Each query is run exactly as a user would type it; the relevant set is defined independently from the metadata.')) ?></p>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th>#</th><th><?= e(__('User and need')) ?></th><th><?= e(__('Query typed')) ?></th><th><?= e(__('Relevant')) ?></th><th><?= e(__('Retrieved')) ?></th><th><?= e(__('1st relevant at')) ?></th><th>P@5</th><th><?= e(__('R-prec.')) ?></th><th><?= e(__('Recall@10')) ?></th><th>AP</th><th><?= e(__('Steps')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($tests as $t): ?>
      <tr>
        <td><strong><?= e($t['id']) ?></strong></td>
        <td><strong><?= e($t['persona']) ?></strong><br><span class="small muted"><?= e($t['need']) ?></span></td>
        <td><code><?= e($t['query']) ?></code><?php if ($t['filters']): ?><br><span class="small muted">+ <?= e(__('filter:')) ?> <?= e(implode(', ', array_map(static fn ($v) => Catalog::typeLabel($v), $t['filters']['type'] ?? []))) ?></span><?php endif; ?></td>
        <td><?= $t['relevant_count'] ?><br><span class="small muted"><?= e($t['judgement']) ?></span></td>
        <td><?= $t['retrieved'] ?></td>
        <td><?= $t['first_rank'] ? e(__('rank {n}', ['n' => $t['first_rank']])) : '<span class="bad">' . e(__('not found')) . '</span>' ?></td>
        <td><?= $dec($t['p5']) ?></td>
        <td><?= $dec($t['p_at_r']) ?></td>
        <td><?= $pct($t['r10']) ?></td>
        <td><?= $dec($t['ap']) ?></td>
        <td><?= $t['steps'] ?? '—' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td colspan="6"><strong><?= e(__('Mean')) ?></strong></td><td><?= $dec($sum['p5']) ?></td><td><?= $dec($sum['p_at_r']) ?></td><td><?= $pct($sum['r10']) ?></td><td><strong><?= $dec($sum['map']) ?></strong></td><td><?= $dec($sum['steps'], 1) ?></td></tr></tfoot>
  </table>
  </div>
  <details class="top-results">
    <summary><?= e(__('Show the top 5 results of each test query')) ?></summary>
    <?php foreach ($tests as $t): ?>
      <h3><?= e($t['id']) ?> · <code><?= e($t['query']) ?></code></h3>
      <ol>
        <?php foreach ($t['top'] as $i => $r): ?>
          <li class="<?= $t['top_relevant'][$i] ? 'rel' : 'nonrel' ?>"><?= $t['top_relevant'][$i] ? '✓' : '✗' ?> <a href="<?= e(url('document.php', ['id' => $r['id'], 'q' => $t['query']])) ?>"><?= e($r['title']) ?></a></li>
        <?php endforeach; ?>
      </ol>
    <?php endforeach; ?>
  </details>
  <p class="small muted"><?= e(__('P@5 is capped by the number of relevant documents (Q1 has 2, Q5 has 1), so R-precision and AP are the fairer measures for those queries. Steps = type the query + press Enter + each filter click + each “next page” + open the document.')) ?></p>
</section>

<div class="two-col">
  <section class="card">
    <h2><?= e(__('2. Usage analytics (search logs)')) ?></h2>
    <dl class="stats">
      <dt><?= e(__('Searches logged')) ?></dt><dd><?= e(__('{count} by {users} user(s)', ['count' => format_number($an['searches']), 'users' => $an['users']])) ?></dd>
      <dt><?= e(__('Zero-result searches')) ?></dt><dd><?= $an['zero'] ?> (<?= $pct($an['zero_rate']) ?>)</dd>
      <dt><?= e(__('Average results per search')) ?></dt><dd><?= $dec($an['avg_results'], 1) ?></dd>
      <dt><?= e(__('Average response time')) ?></dt><dd><?= $dec($an['avg_ms'], 1) ?> ms</dd>
      <dt><?= e(__('Click-through rate')) ?></dt><dd><?= e(__('{rate} of searches led to opening a document', ['rate' => $pct($an['ctr'])])) ?></dd>
      <dt><?= e(__('Average position clicked')) ?></dt><dd><?= $an['clicks'] ? $dec($an['avg_position'], 1) : '—' ?> (<?= e(__('{rate} on the first result', ['rate' => $pct($an['first_result_share'])])) ?>)</dd>
    </dl>
    <?php if ($an['zero_queries']): ?>
      <h3><?= e(__('Queries with no results')) ?></h3>
      <p class="small muted"><?= e(__('Candidates for synonyms, new metadata or missing records.')) ?></p>
      <ul class="linklist"><?php foreach ($an['zero_queries'] as $z): ?><li><a href="<?= e(url('search.php', ['q' => $z['q']])) ?>"><?= e($z['q']) ?></a> <span class="muted small">× <?= $z['n'] ?></span></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <?php if ($an['top_queries']): ?>
      <h3><?= e(__('Most frequent queries')) ?></h3>
      <ul class="linklist"><?php foreach ($an['top_queries'] as $tq): ?><li><a href="<?= e(url('search.php', ['q' => $tq['q']])) ?>"><?= e($tq['q']) ?></a> <span class="muted small">× <?= $tq['n'] ?> · <?= e(__('{count} results', ['count' => $dec((float) $tq['results'], 0)])) ?></span></li><?php endforeach; ?></ul>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2><?= e(__('3. Peer feedback')) ?></h2>
    <?php $f = $fb['summary']; ?>
    <?php if ((int) $f['n'] === 0): ?>
      <p><?= e(__('No feedback yet. Ask classmates or records staff to complete a task (e.g. one of Q1–Q5) and then fill in the')) ?> <a href="feedback.php"><?= e(__('feedback form')) ?></a>.</p>
    <?php else: ?>
      <p class="muted"><?= e(__('{count} response(s). Scores out of 5.', ['count' => (int) $f['n']])) ?></p>
      <dl class="stats">
        <?php foreach (['ease' => __('Easy to use'), 'relevance' => __('Results were relevant'), 'speed' => __('Fast enough'), 'filters_useful' => __('Filters were useful'), 'satisfaction' => __('Overall satisfaction')] as $k => $label): ?>
          <dt><?= e($label) ?></dt><dd><span class="bar"><span style="width: <?= round((float) $f[$k] / 5 * 100) ?>%"></span></span> <?= $dec((float) $f[$k], 1) ?></dd>
        <?php endforeach; ?>
        <dt><?= e(__('Task completed')) ?></dt><dd><?= $f['found'] !== null ? $pct((float) $f['found']) : '—' ?></dd>
        <dt><?= e(__('Clicks reported')) ?></dt><dd><?= $f['clicks'] !== null ? $dec((float) $f['clicks'], 1) : '—' ?></dd>
      </dl>
      <?php if ($fb['comments']): ?>
        <h3><?= e(__('Comments')) ?></h3>
        <ul class="comments">
          <?php foreach ($fb['comments'] as $c): ?><li>“<?= e($c['comment']) ?>”<br><span class="small muted"><?= e($c['full_name'] ?? __('Anonymous')) ?> · <?= e($c['role'] ? Catalog::roleLabel($c['role']) : '') ?><?= $c['task'] ? ' · ' . e($c['task']) : '' ?></span></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
    <?php endif; ?>
    <p><a class="btn" href="feedback.php"><?= e(__('Open the feedback form')) ?></a></p>
  </section>
</div>

<section class="card">
  <h2><?= e(__('4. Access control check')) ?></h2>
  <p class="muted"><?= e(__('Number of active documents each account can find and open. Restricted documents must only be visible to the Records Manager; a student must only see public documents and his own records.')) ?></p>
  <div class="table-wrap">
  <table class="table">
    <thead><tr><th><?= e(__('Account')) ?></th><th><?= e(__('Role')) ?></th><?php foreach (array_keys(Catalog::ACCESS) as $level): ?><th><?= access_badge($level) ?></th><?php endforeach; ?><th><?= e(__('Total')) ?></th></tr></thead>
    <tbody>
      <?php foreach ($matrix['rows'] as $row): ?>
        <tr>
          <td><code><?= e($row['user']['username']) ?></code><br><span class="small muted"><?= e($row['user']['full_name']) ?></span></td>
          <td><?= e(Catalog::roleLabel($row['user']['role'])) ?><br><span class="small muted"><?= e(__($row['user']['department_name'] ?? '')) ?></span></td>
          <?php foreach ($row['counts'] as $level => $n): ?><td><?= $n ?> <span class="small muted">/ <?= $matrix['totals'][$level] ?></span></td><?php endforeach; ?>
          <td><strong><?= $row['total'] ?></strong></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</section>
<?php
render_footer();
