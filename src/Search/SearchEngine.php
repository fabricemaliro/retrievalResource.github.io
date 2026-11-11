<?php

declare(strict_types=1);

namespace App\Search;

use App\AccessControl;
use App\Catalog;
use App\Database;
use App\Doc;
use PDO;

/**
 * Retrieval process:
 *   query → parse & analyse → look up terms in the inverted index
 *         → candidate documents (security-trimmed by AccessControl + SQL filters)
 *         → BM25 ranking (+ coordination and exact-identifier boosts)
 *         → facet counts → facet filters → sort → paginate → snippets
 */
final class SearchEngine
{
    private const K1 = 1.2;
    private const B = 0.75;

    /** URL parameter => documents column */
    public const FACETS = [
        'type' => 'document_type',
        'dept' => 'department_id',
        'year' => 'doc_year',
        'access' => 'access_level',
    ];

    public const SORTS = [
        'relevance' => 'Relevance',
        'newest' => 'Newest first',
        'oldest' => 'Oldest first',
        'title' => 'Title (A–Z)',
    ];

    private ?array $stats = null;

    public function __construct(private readonly PDO $db)
    {
    }

    public static function defaults(): array
    {
        return [
            'q' => '', 'type' => [], 'dept' => [], 'year' => [], 'access' => [],
            'date_from' => '', 'date_to' => '', 'ref' => '', 'student_no' => '', 'author' => '', 'title' => '',
            'archived' => false, 'sort' => '', 'page' => 1,
        ];
    }

    /** Cleans raw $_GET input. */
    public static function paramsFromRequest(array $in): array
    {
        $p = self::defaults();
        $p['q'] = mb_substr(trim((string) ($in['q'] ?? '')), 0, 200);

        $list = static fn (string $k) => array_values(array_unique(array_filter(
            array_map('strval', is_array($in[$k] ?? null) ? $in[$k] : (($in[$k] ?? '') !== '' ? [$in[$k]] : [])),
            static fn ($v) => $v !== '' && strlen($v) <= 40
        )));
        $p['type'] = array_values(array_intersect($list('type'), array_keys(Catalog::TYPES)));
        $p['access'] = array_values(array_intersect($list('access'), array_keys(Catalog::ACCESS)));
        $p['dept'] = array_map('intval', array_filter($list('dept'), 'ctype_digit'));
        $p['year'] = array_map('intval', array_filter($list('year'), 'ctype_digit'));

        foreach (['date_from', 'date_to'] as $k) {
            $v = (string) ($in[$k] ?? '');
            $p[$k] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
        }
        foreach (['ref', 'student_no', 'author', 'title'] as $k) {
            $p[$k] = mb_substr(trim((string) ($in[$k] ?? '')), 0, 100);
        }
        $p['archived'] = !empty($in['archived']);
        $p['sort'] = array_key_exists($in['sort'] ?? '', self::SORTS) ? $in['sort'] : '';
        $p['page'] = max(1, min(1000, (int) ($in['page'] ?? 1)));
        return $p;
    }

    public static function hasFilters(array $p): bool
    {
        foreach (['type', 'dept', 'year', 'access'] as $k) {
            if ($p[$k]) {
                return true;
            }
        }
        foreach (['date_from', 'date_to', 'ref', 'student_no', 'author', 'title'] as $k) {
            if ($p[$k] !== '') {
                return true;
            }
        }
        return (bool) $p['archived'];
    }

    public function search(array $p, array $user, int $perPage = 10, bool $withSnippets = true): array
    {
        $start = microtime(true);
        $parsed = QueryParser::parse($p['q']);
        $hasQuery = !QueryParser::isEmpty($parsed);
        $groups = $hasQuery ? $this->resolveGroups($parsed) : [];
        [$where, $args] = $this->buildWhere($p, $user, $parsed);

        // 1. Candidates and relevance scores
        $scores = $hasQuery ? $this->score($groups, $where, $args) : [];
        $rows = $hasQuery ? $this->rowsByIds(array_keys($scores)) : $this->rowsWhere($where, $args);

        // 2. Exact identifier match (reference number or registration number)
        $exact = [];
        if ($parsed['ids']) {
            foreach ($rows as $r) {
                if (isset($parsed['ids'][Tokenizer::compact((string) $r['reference_no'])])
                    || ($r['student_no'] && isset($parsed['ids'][Tokenizer::compact($r['student_no'])]))) {
                    $exact[(int) $r['id']] = true;
                    $scores[(int) $r['id']] = ($scores[(int) $r['id']] ?? 0) + 100;
                }
            }
        }

        // 3. Facets: each facet is counted with all the OTHER facet filters applied
        $facets = array_fill_keys(array_keys(self::FACETS), []);
        $final = [];
        foreach ($rows as $r) {
            $fails = [];
            foreach (self::FACETS as $f => $col) {
                if ($p[$f] && !in_array((string) $r[$col], array_map('strval', $p[$f]), true)) {
                    $fails[] = $f;
                }
            }
            if (!$fails) {
                $final[] = $r;
            }
            foreach (self::FACETS as $f => $col) {
                if (!$fails || $fails === [$f]) {
                    $facets[$f][(string) $r[$col]] = ($facets[$f][(string) $r[$col]] ?? 0) + 1;
                }
            }
        }
        arsort($facets['type']);
        arsort($facets['dept']);
        krsort($facets['year']);
        $facets['access'] = array_intersect_key(array_merge(array_fill_keys(array_keys(Catalog::ACCESS), 0), $facets['access']), $facets['access']);

        // 4. Sort
        $sort = $p['sort'] ?: ($hasQuery ? 'relevance' : 'newest');
        usort($final, function (array $a, array $b) use ($sort, $scores): int {
            return match ($sort) {
                'relevance' => [$scores[(int) $b['id']] ?? 0, $b['date_created']] <=> [$scores[(int) $a['id']] ?? 0, $a['date_created']],
                'oldest' => [$a['date_created'], (int) $a['id']] <=> [$b['date_created'], (int) $b['id']],
                'title' => strcasecmp(Doc::t($a, 'title'), Doc::t($b, 'title')),
                default => [$b['date_created'], (int) $b['id']] <=> [$a['date_created'], (int) $a['id']],
            };
        });

        // 5. Paginate and build result items
        $total = count($final);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($p['page'], $pages);
        $slice = array_slice($final, ($page - 1) * $perPage, $perPage);
        $pattern = Highlighter::pattern($parsed);
        $results = [];

        if ($withSnippets && $slice) {
            $full = $this->fullRows(array_map(static fn ($r) => (int) $r['id'], $slice));
            foreach ($slice as $r) {
                $id = (int) $r['id'];
                $d = $full[$id];
                $results[] = [
                    'id' => $id,
                    'title_html' => Highlighter::highlight(Doc::t($d, 'title'), $pattern),
                    'title' => Doc::t($d, 'title'),
                    'translated' => Doc::available($d),
                    'reference_no' => $d['reference_no'],
                    'document_type' => $d['document_type'],
                    'department' => __($d['department_name']),
                    'date_created' => $d['date_created'],
                    'access_level' => $d['access_level'],
                    'status' => $d['status'],
                    'student_name' => $d['student_name'],
                    'student_no' => $d['student_no'],
                    'author' => Doc::t($d, 'author'),
                    'snippet' => Highlighter::snippet(Doc::t($d, 'content') ?: Doc::t($d, 'description'), $pattern),
                    'score' => round($scores[$id] ?? 0, 3),
                    'exact' => isset($exact[$id]),
                ];
            }
        } else {
            foreach ($slice as $r) {
                $results[] = ['id' => (int) $r['id'], 'title' => Doc::t($r, 'title'), 'score' => round($scores[(int) $r['id']] ?? 0, 3)];
            }
        }

        $suggestion = ($hasQuery && $total < 3) ? $this->suggest($parsed) : null;

        return [
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
            'sort' => $sort,
            'results' => $results,
            'facets' => $facets,
            'parsed' => $parsed,
            'has_query' => $hasQuery,
            'suggestion' => $suggestion,
            'took_ms' => round((microtime(true) - $start) * 1000, 1),
        ];
    }

    /** "More like this": the document's most characteristic terms used as a query. */
    public function related(array $doc, array $user, int $limit = 5): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.term_id, p.tf, t.df FROM postings p JOIN terms t ON t.id = p.term_id WHERE p.doc_id = ? AND t.df > 1'
        );
        $stmt->execute([$doc['id']]);
        $n = $this->stats()['n'];
        $weighted = [];
        foreach ($stmt as $row) {
            $weighted[(int) $row['term_id']] = [(float) $row['tf'] * log($n / (int) $row['df']), (int) $row['df']];
        }
        uasort($weighted, static fn ($a, $b) => $b[0] <=> $a[0]);
        $groups = [];
        foreach (array_slice($weighted, 0, 10, true) as $termId => [, $df]) {
            $groups[] = ['label' => '', 'terms' => [$termId => $df]];
        }
        if (!$groups) {
            return [];
        }
        [$acl, $args] = AccessControl::where($user, 'd');
        $where = "$acl AND d.status = 'active' AND d.id <> ?";
        $args[] = (int) $doc['id'];
        $scores = $this->score($groups, $where, $args);
        arsort($scores);
        $ids = array_slice(array_keys($scores), 0, $limit);
        $rows = $this->fullRows($ids);
        return array_values(array_filter(array_map(static fn ($id) => $rows[$id] ?? null, $ids)));
    }

    /** Each query element becomes a "group" of index terms (a prefix expands to several terms). */
    private function resolveGroups(array $parsed): array
    {
        $lookup = array_merge(array_map('strval', array_keys($parsed['words'])), array_map('strval', array_keys($parsed['ids'])));
        foreach (array_keys($parsed['words']) as $stem) {
            $lookup = array_merge($lookup, Synonyms::for((string) $stem));
        }
        $lookup = array_values(array_unique($lookup));
        $known = [];
        if ($lookup) {
            $stmt = $this->db->prepare('SELECT id, term, df FROM terms WHERE term IN (' . Database::placeholders($lookup) . ')');
            $stmt->execute($lookup);
            foreach ($stmt as $row) {
                $known[$row['term']] = [(int) $row['id'], (int) $row['df']];
            }
        }

        $groups = [];
        foreach ($parsed['words'] as $stem => $surface) {
            $k = $known[(string) $stem] ?? null;
            $group = ['label' => (string) $surface, 'terms' => $k ? [$k[0] => $k[1]] : [], 'weights' => []];
            foreach (Synonyms::for((string) $stem) as $synonym) {        // query expansion
                if (isset($known[$synonym]) && !isset($group['terms'][$known[$synonym][0]])) {
                    $group['terms'][$known[$synonym][0]] = $known[$synonym][1];
                    $group['weights'][$known[$synonym][0]] = Synonyms::WEIGHT;
                }
            }
            $groups[] = $group;
        }
        foreach ($parsed['ids'] as $compact => $original) {
            $k = $known[(string) $compact] ?? null;
            $groups[] = ['label' => (string) $original, 'terms' => $k ? [$k[0] => $k[1]] : []];
        }
        foreach ($parsed['prefixes'] as $prefix) {
            $stmt = $this->db->prepare("SELECT id, df FROM terms WHERE term LIKE ? ESCAPE '!' ORDER BY df DESC LIMIT 50");
            $stmt->execute([ltrim(Database::like($prefix), '%')]);
            $terms = [];
            foreach ($stmt as $row) {
                $terms[(int) $row['id']] = (int) $row['df'];
            }
            $groups[] = ['label' => $prefix . '*', 'terms' => $terms];
        }
        return $groups;
    }

    /** BM25 scores for documents matching at least one group. @return array<int,float> */
    private function score(array $groups, string $where, array $args): array
    {
        ['n' => $n, 'avgdl' => $avgdl] = $this->stats();
        $termGroups = [];
        $idf = [];
        foreach ($groups as $gi => $g) {
            foreach ($g['terms'] as $termId => $df) {
                $termGroups[$termId][] = $gi;
                $idf[$termId] = log(1 + ($n - $df + 0.5) / ($df + 0.5));
            }
        }
        if (!$termGroups) {
            return [];
        }

        $ids = implode(',', array_map('intval', array_keys($termGroups)));
        $stmt = $this->db->prepare(
            "SELECT p.doc_id, p.term_id, p.tf, d.doc_length FROM postings p JOIN documents d ON d.id = p.doc_id
              WHERE p.term_id IN ($ids) AND $where"
        );
        $stmt->execute($args);

        $perDoc = [];
        foreach ($stmt as $row) {
            $tf = (float) $row['tf'];
            $norm = 1 - self::B + self::B * ((float) $row['doc_length'] / $avgdl);
            $s = $idf[(int) $row['term_id']] * ($tf * (self::K1 + 1)) / ($tf + self::K1 * $norm);
            foreach ($termGroups[(int) $row['term_id']] as $gi) {
                $doc = (int) $row['doc_id'];
                $weighted = $s * ($groups[$gi]['weights'][(int) $row['term_id']] ?? 1.0);
                $perDoc[$doc][$gi] = max($perDoc[$doc][$gi] ?? 0, $weighted);
            }
        }

        // Coordination: a document matching 3 of 3 query words beats one matching 1 of 3.
        $total = count($groups);
        $scores = [];
        foreach ($perDoc as $doc => $gs) {
            $scores[$doc] = array_sum($gs) * (0.4 + 0.6 * count($gs) / $total);
        }
        return $scores;
    }

    private function buildWhere(array $p, array $user, array $parsed): array
    {
        [$acl, $args] = AccessControl::where($user, 'd');
        $w = [$acl];

        if (!($p['archived'] && AccessControl::canSeeArchived($user))) {
            $w[] = "d.status = 'active'";
        }
        if ($p['date_from'] !== '') {
            $w[] = 'd.date_created >= ?';
            $args[] = $p['date_from'];
        }
        if ($p['date_to'] !== '') {
            $w[] = 'd.date_created <= ?';
            $args[] = $p['date_to'];
        }
        foreach (['ref' => 'reference_no', 'student_no' => 'student_no', 'author' => 'author', 'title' => 'title'] as $k => $col) {
            if ($p[$k] !== '') {
                $w[] = "d.$col LIKE ? ESCAPE '!'";
                $args[] = Database::like($p[$k]);
            }
        }
        foreach ($parsed['phrases'] as $phrase) {
            $w[] = "d.search_text LIKE ? ESCAPE '!'";
            $args[] = Database::like($phrase);
        }
        if ($parsed['exclude']) {
            $stmt = $this->db->prepare('SELECT id FROM terms WHERE term IN (' . Database::placeholders($parsed['exclude']) . ')');
            $stmt->execute($parsed['exclude']);
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if ($ids) {
                $w[] = 'NOT EXISTS (SELECT 1 FROM postings px WHERE px.doc_id = d.id AND px.term_id IN (' . implode(',', array_map('intval', $ids)) . '))';
            }
        }
        return [implode(' AND ', $w), $args];
    }

    private const LIGHT_COLUMNS = 'd.id, d.document_type, d.department_id, d.doc_year, d.access_level, d.date_created, d.title, d.title_fr, d.reference_no, d.student_no';

    private function rowsWhere(string $where, array $args): array
    {
        $stmt = $this->db->prepare('SELECT ' . self::LIGHT_COLUMNS . " FROM documents d WHERE $where");
        $stmt->execute($args);
        return $stmt->fetchAll();
    }

    private function rowsByIds(array $ids): array
    {
        $rows = [];
        foreach (array_chunk(array_map('intval', $ids), 500) as $chunk) {
            $rows = array_merge($rows, $this->db->query(
                'SELECT ' . self::LIGHT_COLUMNS . ' FROM documents d WHERE d.id IN (' . implode(',', $chunk) . ')'
            )->fetchAll());
        }
        return $rows;
    }

    /** @return array<int,array> */
    private function fullRows(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $stmt = $this->db->query(
            'SELECT d.*, dp.name AS department_name FROM documents d JOIN departments dp ON dp.id = d.department_id
              WHERE d.id IN (' . implode(',', array_map('intval', $ids)) . ')'
        );
        $out = [];
        foreach ($stmt as $row) {
            $out[(int) $row['id']] = $row;
        }
        return $out;
    }

    /** "Did you mean…?" — closest indexed word (edit distance) for unknown or rare query words. */
    private function suggest(array $parsed): ?string
    {
        $query = $parsed['raw'];
        $changed = false;
        foreach ($parsed['words'] as $stem => $word) {
            $word = (string) $word;
            if (strlen($word) < 4 || ctype_digit($word)) {
                continue;
            }
            $known = $this->db->prepare('SELECT df FROM terms WHERE term = ?');
            $known->execute([(string) $stem]);
            if ((int) $known->fetchColumn() >= 2) {
                continue;
            }
            $stmt = $this->db->prepare("SELECT display, df FROM terms WHERE df >= 2 AND term LIKE ? ESCAPE '!' AND display NOT LIKE '%/%'");
            $stmt->execute([ltrim(Database::like($word[0]), '%')]);
            $best = null;
            $bestDistance = strlen($word) <= 5 ? 2 : 3;
            $bestDf = 0;
            foreach ($stmt as $row) {
                $candidate = mb_strtolower($row['display']);
                $plain = Tokenizer::normalize($candidate);          // compare without accents
                if ($plain === '' || str_contains($plain, ' ') || abs(strlen($plain) - strlen($word)) > 2) {
                    continue;
                }
                $d = levenshtein($word, $plain);
                if ($d < $bestDistance || ($d === $bestDistance && $best !== null && (int) $row['df'] > $bestDf)) {
                    if ($d > 0) {
                        $best = $candidate;
                        $bestDistance = $d;
                        $bestDf = (int) $row['df'];
                    }
                }
            }
            if ($best !== null) {
                $done = false;
                $query = preg_replace_callback('/[\p{L}\p{N}]+/u', static function (array $m) use ($word, $best, &$done): string {
                    if (!$done && Tokenizer::normalize($m[0]) === $word) {
                        $done = true;
                        return $best;
                    }
                    return $m[0];
                }, $query) ?? $query;
                $changed = $changed || $done;
            }
        }
        return $changed ? $query : null;
    }

    /** @return array{n:int, avgdl:float} */
    private function stats(): array
    {
        if ($this->stats === null) {
            $row = $this->db->query('SELECT COUNT(*) AS n, AVG(doc_length) AS avgdl FROM documents')->fetch();
            $this->stats = ['n' => max(1, (int) $row['n']), 'avgdl' => max(1.0, (float) $row['avgdl'])];
        }
        return $this->stats;
    }
}
