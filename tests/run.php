<?php

declare(strict_types=1);

/*
 * Automated checks of the retrieval engine and access control (no web server needed).
 *   php tests/run.php
 * Uses a temporary in-memory SQLite database with the same demo data as the application.
 */

require __DIR__ . '/../src/bootstrap.php';

use App\AccessControl;
use App\App;
use App\Database;
use App\Evaluation;
use App\Installer;
use App\Search\QueryParser;
use App\Search\SearchEngine;
use App\Search\Tokenizer;

$db = Database::connect(['driver' => 'sqlite', 'sqlite_path' => ':memory:']);
App::setDb($db);
Installer::install($db);
$engine = new SearchEngine($db);

$pass = 0;
$fail = 0;
function check(string $label, bool $ok, string $info = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $label . ($info !== '' ? "  [$info]" : '') . "\n";
}
function user(string $username): array
{
    $s = App::db()->prepare('SELECT * FROM users WHERE username = ?');
    $s->execute([$username]);
    return $s->fetch();
}
function run(string $q, array $user, array $extra = []): array
{
    global $engine;
    return $engine->search(array_merge(SearchEngine::defaults(), ['q' => $q], $extra), $user, 10);
}

echo "\nText analysis\n";
check('stemming plural', Tokenizer::stem('transcripts') === 'transcript');
check('stemming -ies', Tokenizer::stem('policies') === Tokenizer::stem('policy'));
check('stemming -ed / doubling', Tokenizer::stem('admitted') === 'admit');
check('stop words removed', !in_array('the', array_column(Tokenizer::analyze('the senate'), 0), true));
check('identifier token', isset(Tokenizer::identifiers('ref LU/HR/2023/0042')['luhr20230042']));
$p = QueryParser::parse('"exam results" -draft admis* LU/REG/2024/0005 okello');
check('query parser', $p['phrases'] === ['exam results'] && $p['exclude'] === ['draft'] && $p['prefixes'] === ['admis'] && isset($p['ids']['lureg20240005']) && isset($p['words']['okello']));

$manager = user('manager');
echo "\nRetrieval\n";
$r = run('Brian Okello transcript', $manager);
check('Q1 top result is Brian\'s transcript', str_contains($r['results'][0]['title'], 'Transcript') && str_contains($r['results'][0]['title'], 'Brian Okello'));
$r = run('LU/HR/2023/0042', $manager);
check('exact reference → 1 result flagged exact', $r['total'] === 1 && $r['results'][0]['exact']);
$r = run('tarnscript okelo', $manager);
check('spelling suggestion', $r['total'] === 0 && $r['suggestion'] === 'transcript okello', (string) $r['suggestion']);
$r = run('"examination malpractice" -senate', $manager);
check('phrase + exclusion', $r['total'] > 0 && !array_filter($r['results'], fn ($x) => stripos($x['title'], 'senate') !== false));
$r = run('admis*', $manager);
check('prefix search', $r['total'] > 10);
$r = run('fee waiver policy', $manager, ['type' => ['policy']]);
check('facet filter', $r['total'] > 0 && count(array_unique(array_column($r['results'], 'document_type'))) === 1);
check('facet counts keep other values', count($r['facets']['type']) > 1);
$r = run('', $manager, ['type' => ['graduation_list'], 'sort' => 'oldest']);
check('browse without query + sort', $r['total'] > 0 && $r['results'][0]['date_created'] <= end($r['results'])['date_created']);
check('snippet highlights', str_contains(run('waiver', $manager)['results'][0]['snippet'], '<mark>'));

echo "\nAccess control\n";
$student = user('student');
$r = run('transcript', $student);
check('student sees only own transcripts', $r['total'] > 0 && !array_filter($r['results'], fn ($x) => $x['document_type'] === 'transcript' && $x['student_no'] !== '21/U/04567'));
check('student sees no internal docs', run('minutes', $student)['facets']['access'] === [] || !isset(run('minutes', $student)['facets']['access']['internal']));
check('nobody but manager sees restricted', !isset(run('legal opinion dismissal', user('officer'))['facets']['access']['restricted']) && isset(run('legal opinion dismissal', $manager)['facets']['access']['restricted']));
$fci = user('fci.admin');
check('dept staff: no confidential HR contracts', !isset(run('employment contract', $fci)['facets']['type']['staff_contract']));
check('dept staff: own faculty confidential visible', run('Brian Okello transcript', $fci)['total'] > 0);
$doc = $db->query("SELECT * FROM documents WHERE access_level = 'restricted' LIMIT 1")->fetch();
check('canView blocks direct access', !AccessControl::canView($student, $doc) && !AccessControl::canView($fci, $doc) && AccessControl::canView($manager, $doc));

echo "\nQuery expansion\n";
$r = run('cheating in exams', $manager);
check('synonyms: "cheating in exams" finds malpractice minutes', $r['total'] > 5 && stripos($r['results'][0]['title'], 'malpractice') !== false);

echo "\nBilingual\n";
\App\I18n::set('fr');
$r = run('relevé de notes Brian Okello', $manager);
check('French query: Brian\'s transcript first, shown in French', str_starts_with($r['results'][0]['title'], 'Relevé de notes') && str_contains($r['results'][0]['title'], 'Brian Okello'), $r['results'][0]['title']);
check('French snippet highlighted (accents)', str_contains($r['results'][0]['snippet'], '<mark>Relevé</mark>') || str_contains($r['results'][0]['snippet'], '<mark>RELEVÉ</mark>'));
check('French interface strings', __('Search') === 'Rechercher' && \App\Catalog::typeLabel('transcript') === 'Relevé de notes');
$r = run('fraude examens', $manager);
check('French query finds malpractice minutes', $r['total'] > 5 && stripos($r['results'][0]['title'], 'fraude') !== false, $r['results'][0]['title'] ?? '');
$frRows = (new Evaluation($db))->runTests('fr');
check('5 French test queries find a relevant document', count(array_filter($frRows, fn ($x) => $x['first_rank'] !== null)) === 5);
$s = Evaluation::summary($frRows);
printf("    FR: MAP %.2f · MRR %.2f · P@5 %.2f · steps %.1f\n", $s['map'], $s['mrr'], $s['p5'], $s['steps']);
\App\I18n::set('en');
$r = run('Brian Okello transcript', $manager);
check('English display after switching back', str_starts_with($r['results'][0]['title'], 'Academic Transcript'));
$missing = array_filter(\App\Doc::TRANSLATED_FIELDS, fn ($f) => (int) $db->query("SELECT COUNT(*) FROM documents WHERE COALESCE({$f}_fr, '') = '' AND COALESCE($f, '') <> ''")->fetchColumn() > 0);
check('every demo document has French title, summary, keywords, text and author', $missing === [], implode(',', $missing));

echo "\nEvaluation\n";
$rows = (new Evaluation($db))->runTests();
$sum = Evaluation::summary($rows);
check('5 test queries all find a relevant document', count(array_filter($rows, fn ($x) => $x['first_rank'] !== null)) === 5);
printf("    MAP %.2f · MRR %.2f · P@5 %.2f · mean recall %.0f%% · %.1f steps\n", $sum['map'], $sum['mrr'], $sum['p5'], $sum['recall'] * 100, $sum['steps']);

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
