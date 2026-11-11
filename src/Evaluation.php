<?php

declare(strict_types=1);

namespace App;

use App\Search\SearchEngine;
use PDO;

/**
 * Evaluation of retrieval effectiveness:
 *   1. Five realistic test queries with relevance judgements → Precision@5, Recall@10, R-precision,
 *      Average Precision, Reciprocal Rank and the number of steps needed to reach the first relevant document.
 *   2. Search analytics from real usage logs (zero-result rate, click-through rate, click position, speed).
 *   3. Peer feedback questionnaire.
 *   4. Access-control matrix (what each role can see).
 *
 * Relevance judgements are written as SQL conditions on the metadata, so they stay correct for the generated data
 * set and can be checked by anyone.
 */
final class Evaluation
{
    public const QUERIES = [
        [
            'id' => 'Q1', 'persona' => 'Registry officer', 'persona_fr' => 'Agent de la scolarité',
            'need' => 'An employer asks to verify the academic record of Brian Okello: retrieve his transcripts.',
            'need_fr' => "Un employeur demande à vérifier le dossier de Brian Okello : retrouver ses relevés de notes.",
            'query' => 'Brian Okello transcript', 'query_fr' => 'relevé de notes Brian Okello', 'filters' => [],
            'relevant' => "document_type = 'transcript' AND student_name = 'Brian Okello'",
            'judgement' => 'Transcripts whose student is Brian Okello', 'judgement_fr' => "Relevés de notes dont l'étudiant est Brian Okello",
        ],
        [
            'id' => 'Q2', 'persona' => 'Admissions officer', 'persona_fr' => 'Agent des admissions',
            'need' => 'Compile the 2024 admission letters of the Computer Science programme.',
            'need_fr' => "Rassembler les lettres d'admission 2024 de la licence en informatique.",
            'query' => 'admission letter computer science 2024', 'query_fr' => "lettre d'admission informatique 2024", 'filters' => ['type' => ['admission_letter']],
            'relevant' => "document_type = 'admission_letter' AND doc_year = 2024 AND programme = 'Bachelor of Science in Computer Science'",
            'judgement' => 'Admission letters dated 2024 for the BSc Computer Science', 'judgement_fr' => "Lettres d'admission datées de 2024 pour la licence en informatique",
        ],
        [
            'id' => 'Q3', 'persona' => 'Finance officer', 'persona_fr' => 'Agent des finances',
            'need' => 'A student asks about fee waivers: find the policy and how it was changed.',
            'need_fr' => "Un étudiant s'informe sur les exonérations : retrouver la politique et ses modifications.",
            'query' => 'fee waiver policy', 'query_fr' => 'politique exonération des frais', 'filters' => [],
            'relevant' => "reference_no IN ('LU/FIN/2022/0007', 'LU/FIN/2024/0019', 'LU/FIN/2024/0021')",
            'judgement' => 'The waiver policy, its 2024 amendment and the minutes approving it', 'judgement_fr' => "La politique d'exonération, son amendement de 2024 et le procès-verbal qui l'approuve",
        ],
        [
            'id' => 'Q4', 'persona' => 'Faculty administrator', 'persona_fr' => 'Administrateur de faculté',
            'need' => 'Prepare a report: find every meeting where examination malpractice was discussed.',
            'need_fr' => 'Préparer un rapport : retrouver toutes les réunions où la fraude aux examens a été discutée.',
            'query' => 'minutes examination malpractice', 'query_fr' => 'procès-verbal fraude aux examens', 'filters' => [],
            'relevant' => "document_type = 'minutes' AND search_text LIKE '%malpractice%'",
            'judgement' => 'Any set of minutes that mentions malpractice', 'judgement_fr' => 'Tout procès-verbal qui mentionne la fraude aux examens',
        ],
        [
            'id' => 'Q5', 'persona' => 'HR officer', 'persona_fr' => 'Agent des ressources humaines',
            'need' => 'Open a staff contract quoted by its reference number in a letter.',
            'need_fr' => 'Ouvrir un contrat de travail cité par sa référence dans un courrier.',
            'query' => 'LU/HR/2023/0042', 'query_fr' => 'LU/HR/2023/0042', 'filters' => [],
            'relevant' => "reference_no = 'LU/HR/2023/0042'",
            'judgement' => 'The contract with this exact reference', 'judgement_fr' => 'Le contrat portant exactement cette référence',
        ],
    ];

    /** A test query in the requested language (persona, need, query and judgement). */
    public static function localized(array $q, string $lang): array
    {
        if ($lang === 'fr') {
            foreach (['persona', 'need', 'query', 'judgement'] as $k) {
                $q[$k] = $q[$k . '_fr'];
            }
        }
        return $q;
    }

    public function __construct(private readonly PDO $db)
    {
    }

    /** Runs the five test queries, typed in $lang (English or French). */
    public function runTests(?string $lang = null): array
    {
        $lang ??= I18n::lang();
        $engine = new SearchEngine($this->db);
        $evaluator = ['id' => 0, 'role' => 'admin', 'department_id' => null, 'student_no' => null];
        $rows = [];

        foreach (self::QUERIES as $q) {
            $q = self::localized($q, $lang);
            $relevant = array_map('intval', $this->db->query(
                "SELECT id FROM documents WHERE status = 'active' AND ({$q['relevant']})"
            )->fetchAll(PDO::FETCH_COLUMN));
            $params = array_merge(SearchEngine::defaults(), ['q' => $q['query']], $q['filters']);
            $res = $engine->search($params, $evaluator, 1000, false);
            $ranked = array_column($res['results'], 'id');

            $relSet = array_flip($relevant);
            $total = count($relevant);
            $first = null;
            $hits = 0;
            $sumPrecision = 0.0;
            foreach ($ranked as $i => $id) {
                if (isset($relSet[$id])) {
                    $hits++;
                    $sumPrecision += $hits / ($i + 1);
                    $first ??= $i + 1;
                }
            }
            $inTop = static fn (int $k) => count(array_intersect(array_slice($ranked, 0, $k), $relevant));
            $filterClicks = array_sum(array_map('count', $q['filters']));
            $perPage = (int) App::config('per_page', 10);

            $rows[] = $q + [
                'retrieved' => count($ranked),
                'relevant_count' => $total,
                'relevant_found' => $hits,
                'first_rank' => $first,
                'p5' => $inTop(5) / 5,
                'p_at_r' => $total ? $inTop($total) / $total : 0.0,
                'r10' => $total ? $inTop(10) / $total : 0.0,
                'recall' => $total ? $hits / $total : 0.0,
                'ap' => $total ? $sumPrecision / $total : 0.0,
                'rr' => $first ? 1 / $first : 0.0,
                // type query + press Enter + filters + "next page" clicks + open the result
                'steps' => $first ? 2 + $filterClicks + (int) (ceil($first / $perPage) - 1) + 1 : null,
                'ms' => $res['took_ms'],
                'top' => array_slice($res['results'], 0, 5),
                'top_relevant' => array_map(static fn ($r) => isset($relSet[$r['id']]), array_slice($res['results'], 0, 5)),
            ];
        }
        return $rows;
    }

    public static function summary(array $rows): array
    {
        $n = max(1, count($rows));
        $avg = static fn (string $k) => array_sum(array_column($rows, $k)) / $n;
        return [
            'map' => $avg('ap'), 'mrr' => $avg('rr'), 'p5' => $avg('p5'), 'r10' => $avg('r10'), 'p_at_r' => $avg('p_at_r'),
            'recall' => $avg('recall'), 'steps' => $avg('steps'), 'ms' => $avg('ms'),
        ];
    }

    public function analytics(): array
    {
        $one = fn (string $sql) => $this->db->query($sql)->fetch();
        $s = $one('SELECT COUNT(*) AS searches, COUNT(DISTINCT user_id) AS users, AVG(results_count) AS avg_results,
                          AVG(duration_ms) AS avg_ms, SUM(CASE WHEN results_count = 0 THEN 1 ELSE 0 END) AS zero
                     FROM search_logs');
        $c = $one('SELECT COUNT(*) AS clicks, COUNT(DISTINCT search_id) AS searches_clicked, AVG(position) AS avg_position FROM click_logs');
        $searches = (int) $s['searches'];
        return [
            'searches' => $searches,
            'users' => (int) $s['users'],
            'avg_results' => (float) $s['avg_results'],
            'avg_ms' => (float) $s['avg_ms'],
            'zero' => (int) $s['zero'],
            'zero_rate' => $searches ? (int) $s['zero'] / $searches : 0.0,
            'clicks' => (int) $c['clicks'],
            'ctr' => $searches ? (int) $c['searches_clicked'] / $searches : 0.0,
            'avg_position' => (float) $c['avg_position'],
            'first_result_share' => $c['clicks'] ? (int) $this->db->query('SELECT COUNT(*) FROM click_logs WHERE position = 1')->fetchColumn() / (int) $c['clicks'] : 0.0,
            'top_queries' => $this->db->query("SELECT LOWER(query) AS q, COUNT(*) AS n, AVG(results_count) AS results FROM search_logs WHERE query <> ''
                                               GROUP BY LOWER(query) ORDER BY n DESC, q LIMIT 8")->fetchAll(),
            'zero_queries' => $this->db->query("SELECT LOWER(query) AS q, COUNT(*) AS n, MAX(created_at) AS last FROM search_logs
                                                WHERE results_count = 0 AND query <> '' GROUP BY LOWER(query) ORDER BY n DESC, last DESC LIMIT 8")->fetchAll(),
        ];
    }

    public function feedback(): array
    {
        $row = $this->db->query('SELECT COUNT(*) AS n, AVG(ease) AS ease, AVG(relevance) AS relevance, AVG(speed) AS speed,
                                        AVG(filters_useful) AS filters_useful, AVG(satisfaction) AS satisfaction,
                                        AVG(found) AS found, AVG(clicks) AS clicks
                                   FROM feedback')->fetch();
        $comments = $this->db->query("SELECT f.comment, f.task, f.created_at, u.full_name, u.role FROM feedback f LEFT JOIN users u ON u.id = f.user_id
                                       WHERE f.comment IS NOT NULL AND f.comment <> '' ORDER BY f.id DESC LIMIT 10")->fetchAll();
        return ['summary' => $row, 'comments' => $comments];
    }

    /** Number of documents of each access level visible to each demo account. */
    public function accessMatrix(): array
    {
        $out = [];
        foreach ($this->db->query('SELECT u.*, d.name AS department_name FROM users u LEFT JOIN departments d ON d.id = u.department_id ORDER BY u.id') as $user) {
            [$where, $args] = AccessControl::where($user, 'd');
            $stmt = $this->db->prepare("SELECT d.access_level, COUNT(*) AS n FROM documents d WHERE $where AND d.status = 'active' GROUP BY d.access_level");
            $stmt->execute($args);
            $counts = array_fill_keys(array_keys(Catalog::ACCESS), 0);
            foreach ($stmt as $r) {
                $counts[$r['access_level']] = (int) $r['n'];
            }
            $out[] = ['user' => $user, 'counts' => $counts, 'total' => array_sum($counts)];
        }
        $totals = array_fill_keys(array_keys(Catalog::ACCESS), 0);
        foreach ($this->db->query("SELECT access_level, COUNT(*) AS n FROM documents WHERE status = 'active' GROUP BY access_level") as $r) {
            $totals[$r['access_level']] = (int) $r['n'];
        }
        return ['rows' => $out, 'totals' => $totals];
    }
}
