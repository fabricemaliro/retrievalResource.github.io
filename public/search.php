<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\AccessControl;
use App\App;
use App\Catalog;
use App\Doc;
use App\Logger;
use App\Search\SearchEngine;

$user = require_login();
$db = App::db();
$p = SearchEngine::paramsFromRequest($_GET);

if ($p['q'] === '' && !SearchEngine::hasFilters($p)) {
    redirect('index.php');
}

$engine = new SearchEngine($db);
$res = $engine->search($p, $user, (int) App::config('per_page', 10));

// Log each new search (first page only); later pages keep the same search id for click tracking.
$sid = (int) ($_GET['sid'] ?? 0);
if ($p['page'] === 1 || $sid === 0) {
    $sid = Logger::search($user, $p, $res['total'], $res['took_ms']);
}

$departments = Catalog::departments($db);
$facetLabel = static fn (string $facet, string $value): string => match ($facet) {
    'type' => Catalog::typeLabel($value),
    'dept' => $departments[(int) $value]['name'] ?? $value,
    'access' => Catalog::accessLabel($value),
    default => $value,
};

/** URL of this search with some parameters changed. */
$link = static function (array $changes = []) use ($p): string {
    $params = array_merge($p, ['page' => 1], $changes);
    $params['archived'] = $params['archived'] ? 1 : '';
    if ((int) $params['page'] === 1) {
        unset($params['page']);
    }
    return url('search.php', $params);
};

$currentUrl = $link(['page' => $res['page'], 'sid' => $sid]);
$hasFilters = SearchEngine::hasFilters($p);

// Active filter chips (each removable)
$chips = [];
foreach (['type', 'dept', 'year', 'access'] as $facet) {
    foreach ($p[$facet] as $value) {
        $chips[] = [$facetLabel($facet, (string) $value), $link([$facet => array_values(array_diff($p[$facet], [$value]))])];
    }
}
$fieldLabels = ['ref' => __('Reference'), 'student_no' => __('Reg. no.'), 'title' => __('Title'), 'author' => __('Author'), 'date_from' => __('From'), 'date_to' => __('To')];
foreach ($fieldLabels as $key => $label) {
    if ($p[$key] !== '') {
        $chips[] = [__('{label}: {value}', ['label' => $label, 'value' => $p[$key]]), $link([$key => ''])];
    }
}
if ($p['archived']) {
    $chips[] = [__('Including archived'), $link(['archived' => false])];
}

$facetTitles = ['type' => __('Document type'), 'dept' => __('Department'), 'year' => __('Year created'), 'access' => __('Access level')];
$sorts = ['relevance' => __('Relevance'), 'newest' => __('Newest first'), 'oldest' => __('Oldest first'), 'title' => __('Title (A–Z)')];
$heading = $p['q'] !== '' ? '« ' . $p['q'] . ' »' : __('Browse');
if (\App\I18n::lang() === 'en') {
    $heading = $p['q'] !== '' ? '“' . $p['q'] . '”' : __('Browse');
}

render_header(($p['q'] !== '' ? $p['q'] : __('Browse')) . ' — ' . __('Search'), 'search');
?>
<form id="search-form" class="results-layout" method="get" action="search.php" role="search">
  <div class="results-top">
    <label class="sr-only" for="q"><?= e(__('Search records')) ?></label>
    <div class="searchbox compact">
      <svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20"><circle cx="10.5" cy="10.5" r="6.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="m15.5 15.5 5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      <input id="q" name="q" type="search" value="<?= e($p['q']) ?>" autocomplete="off" placeholder="<?= e(__('Search records')) ?>">
      <button class="btn btn-primary" type="submit"><?= e(__('Search')) ?></button>
    </div>
    <button class="btn filters-toggle" type="button" data-toggle-filters aria-controls="filters" aria-expanded="false"><?= e(__('Filters')) ?><?= $chips ? ' (' . count($chips) . ')' : '' ?></button>
  </div>

  <aside id="filters" class="filters" aria-label="<?= e(__('Filters')) ?>">
    <div class="filters-head">
      <h2><?= e(__('Refine results')) ?></h2>
      <?php if ($hasFilters): ?><a class="small" href="<?= e(url('search.php', ['q' => $p['q']])) ?>"><?= e(__('Clear all')) ?></a><?php endif; ?>
    </div>

    <?php foreach ($facetTitles as $facet => $title): ?>
      <?php
        $values = $res['facets'][$facet];
        foreach ($p[$facet] as $selected) {          // keep selected values visible even with 0 results
            $values[(string) $selected] ??= 0;
        }
        if (!$values) continue;
        $shown = 0;
      ?>
      <fieldset class="facet">
        <legend><?= e($title) ?></legend>
        <?php foreach ($values as $value => $count): $shown++; $checked = in_array((string) $value, array_map('strval', $p[$facet]), true); ?>
          <label class="check<?= $shown > 6 && !$checked ? ' more' : '' ?>">
            <input type="checkbox" name="<?= e($facet) ?>[]" value="<?= e((string) $value) ?>" <?= $checked ? 'checked' : '' ?> data-autosubmit>
            <span><?= e($facetLabel($facet, (string) $value)) ?></span><span class="count"><?= (int) $count ?></span>
          </label>
        <?php endforeach; ?>
        <?php if ($shown > 6): ?><button class="linkbtn small" type="button" data-show-more><?= e(__('Show all {count}', ['count' => $shown])) ?></button><?php endif; ?>
      </fieldset>
    <?php endforeach; ?>

    <fieldset class="facet">
      <legend><?= e(__('Date created')) ?></legend>
      <div class="date-range">
        <label class="small"><?= e(__('From')) ?> <input type="date" name="date_from" value="<?= e($p['date_from']) ?>"></label>
        <label class="small"><?= e(__('To')) ?> <input type="date" name="date_to" value="<?= e($p['date_to']) ?>"></label>
      </div>
    </fieldset>

    <details class="facet"<?= ($p['ref'] . $p['student_no'] . $p['title'] . $p['author']) !== '' ? ' open' : '' ?>>
      <summary><?= e(__('Search in a specific field')) ?></summary>
      <label class="small" for="f-ref"><?= e(__('Reference number')) ?></label><input id="f-ref" name="ref" value="<?= e($p['ref']) ?>" placeholder="LU/REG/2024/0005">
      <label class="small" for="f-sno"><?= e(__('Registration number')) ?></label><input id="f-sno" name="student_no" value="<?= e($p['student_no']) ?>" placeholder="21/U/04567">
      <label class="small" for="f-title"><?= e(__('Title contains')) ?></label><input id="f-title" name="title" value="<?= e($p['title']) ?>">
      <label class="small" for="f-author"><?= e(__('Author / signatory')) ?></label><input id="f-author" name="author" value="<?= e($p['author']) ?>">
    </details>

    <?php if (AccessControl::canSeeArchived($user)): ?>
      <label class="check"><input type="checkbox" name="archived" value="1" <?= $p['archived'] ? 'checked' : '' ?> data-autosubmit><span><?= e(__('Include archived records')) ?></span></label>
    <?php endif; ?>

    <button class="btn btn-block" type="submit"><?= e(__('Apply filters')) ?></button>
  </aside>

  <section class="results" aria-live="polite">
    <div class="results-header">
      <p class="results-count">
        <?php if ($res['total'] > 0): ?>
          <strong><?= format_number($res['total']) ?></strong> <?= e($res['total'] === 1 ? __('document') : __('documents')) ?>
          <?= $p['q'] !== '' ? e(__('for')) . ' <strong>' . e($heading) . '</strong>' : '' ?>
          <span class="muted">· <?= e(format_number($res['took_ms'], 1)) ?> ms</span>
        <?php else: ?>
          <?= e(__('No documents found')) ?>
        <?php endif; ?>
      </p>
      <?php if ($res['total'] > 1): ?>
      <label class="sort"><?= e(__('Sort by')) ?>
        <select name="sort" data-autosubmit>
          <?php foreach ($sorts as $key => $label): if ($key === 'relevance' && !$res['has_query']) continue; ?>
            <option value="<?= e($key) ?>" <?= $res['sort'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <?php endif; ?>
    </div>

    <?php if ($chips): ?>
      <div class="chips" aria-label="<?= e(__('Active filters')) ?>">
        <?php foreach ($chips as [$label, $href]): ?>
          <a class="chip" href="<?= e($href) ?>" title="<?= e(__('Remove this filter')) ?>"><?= e($label) ?> <span aria-hidden="true">×</span><span class="sr-only">(<?= e(__('remove')) ?>)</span></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($res['suggestion']): ?>
      <p class="suggestion"><?= __('Did you mean {suggestion}?', ['suggestion' => '<a href="' . e($link(['q' => $res['suggestion']])) . '"><strong>' . e($res['suggestion']) . '</strong></a>']) ?></p>
    <?php endif; ?>
    <?php if ($res['parsed']['ignored'] && $res['total'] > 0): ?>
      <p class="small muted"><?= e(__('Common words ignored:')) ?> <?= e(implode(', ', $res['parsed']['ignored'])) ?>.</p>
    <?php endif; ?>

    <?php if ($res['total'] === 0): ?>
      <div class="empty card">
        <h2><?= e(__('Nothing matches')) ?> <?= $p['q'] !== '' ? e($heading) : e(__('these filters')) ?></h2>
        <ul>
          <?php if ($res['suggestion']): ?><li><?= e(__('Try the suggested spelling:')) ?> <a href="<?= e($link(['q' => $res['suggestion']])) ?>"><?= e($res['suggestion']) ?></a>.</li><?php endif; ?>
          <?php if ($hasFilters): ?><li><a href="<?= e(url('search.php', ['q' => $p['q']])) ?>"><?= e(__('Search again without filters')) ?></a> — <?= e(__('a filter may be excluding the document.')) ?></li><?php endif; ?>
          <li><?= e(__('Check the spelling of names and use fewer, more general words (e.g. transcript Okello instead of a full sentence).')) ?></li>
          <li><?= e(__('If you know it, search by reference number ({ref}) or registration number ({sno}).', ['ref' => 'LU/REG/2024/0005', 'sno' => '21/U/04567'])) ?></li>
          <li><?= e(__('Use admis* to match the beginning of a word.')) ?></li>
          <li><a href="index.php"><?= e(__('Browse by document type')) ?></a> <?= e(__('instead of searching.')) ?></li>
        </ul>
        <p class="muted small"><?= e(__('Some records are confidential or restricted and only appear for authorised staff. If you believe a record should exist,')) ?>
          <a href="mailto:<?= e(App::config('records_office_email')) ?>?subject=<?= rawurlencode(__('Record request:') . ' ' . $p['q']) ?>"><?= e(__('ask the Records Office')) ?></a>.</p>
      </div>
    <?php else: ?>
      <ol class="result-list" start="<?= ($res['page'] - 1) * $res['per_page'] + 1 ?>">
        <?php foreach ($res['results'] as $i => $r):
          $position = ($res['page'] - 1) * $res['per_page'] + $i + 1;
          $href = url('document.php', ['id' => $r['id'], 'q' => $p['q'], 'sid' => $sid, 'pos' => $position, 'back' => $currentUrl]);
        ?>
        <li class="result">
          <div class="result-tags">
            <span class="type-badge"><?= e(Catalog::typeLabel($r['document_type'])) ?></span>
            <?= access_badge($r['access_level']) ?>
            <?php if ($r['status'] === 'archived'): ?><span class="tag tag-archived"><?= e(__('Archived')) ?></span><?php endif; ?>
            <?php if ($r['exact']): ?><span class="tag tag-exact"><?= e(__('Exact number match')) ?></span><?php endif; ?>
            <?php if (!$r['translated']): ?><span class="tag tag-lang"><?= e(__('In {language} only', ['language' => Doc::otherLanguageName()])) ?></span><?php endif; ?>
            <span class="ref"><?= e($r['reference_no']) ?></span>
          </div>
          <h3><a href="<?= e($href) ?>"><?= $r['title_html'] ?></a></h3>
          <p class="snippet"><?= $r['snippet'] ?></p>
          <p class="result-meta">
            <span><?= e($r['department']) ?></span>
            <span><?= format_date($r['date_created']) ?></span>
            <?php if ($r['student_name']): ?><span><?= e($r['student_name']) ?> · <?= e($r['student_no']) ?></span><?php endif; ?>
            <?php if ($r['author'] && !$r['student_name']): ?><span><?= e($r['author']) ?></span><?php endif; ?>
          </p>
        </li>
        <?php endforeach; ?>
      </ol>

      <?php if ($res['pages'] > 1): ?>
      <nav class="pagination" aria-label="<?= e(__('Pages')) ?>">
        <?php if ($res['page'] > 1): ?><a href="<?= e($link(['page' => $res['page'] - 1, 'sid' => $sid])) ?>">← <?= e(__('Previous')) ?></a><?php endif; ?>
        <?php for ($i = max(1, $res['page'] - 3); $i <= min($res['pages'], $res['page'] + 3); $i++): ?>
          <?php if ($i === $res['page']): ?><span aria-current="page"><?= $i ?></span>
          <?php else: ?><a href="<?= e($link(['page' => $i, 'sid' => $sid])) ?>"><?= $i ?></a><?php endif; ?>
        <?php endfor; ?>
        <?php if ($res['page'] < $res['pages']): ?><a href="<?= e($link(['page' => $res['page'] + 1, 'sid' => $sid])) ?>"><?= e(__('Next')) ?> →</a><?php endif; ?>
      </nav>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</form>
<?php
render_footer();
