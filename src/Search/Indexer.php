<?php

declare(strict_types=1);

namespace App\Search;

use App\Catalog;
use App\I18n;
use PDO;

/**
 * Builds the inverted index: for each term, the list of documents containing it (postings)
 * with a field-weighted term frequency. A word in the title counts 3×, in the keywords or
 * identifying metadata 2×, in the summary 1.5× and in the body 1×.
 */
final class Indexer
{
    public const FIELD_WEIGHTS = [
        'title' => 3.0,
        'keywords' => 2.0,
        'metadata' => 2.0,
        'description' => 1.5,
        'content' => 1.0,
    ];

    /** @var array<string,int> */
    private array $termIds = [];

    public function __construct(private readonly PDO $db)
    {
    }

    public function index(int $documentId, bool $updateDocumentFrequencies = true): void
    {
        $stmt = $this->db->prepare(
            'SELECT d.*, dp.name AS department_name FROM documents d JOIN departments dp ON dp.id = d.department_id WHERE d.id = ?'
        );
        $stmt->execute([$documentId]);
        $doc = $stmt->fetch();
        if (!$doc) {
            return;
        }

        $fields = self::fields($doc);
        $weights = [];
        $surface = [];
        $length = 0.0;
        // Original spelling of each normalised word (keeps French accents for "Did you mean…?")
        $accented = [];
        preg_match_all('/[\\p{L}\\p{N}]+/u', mb_strtolower(implode(' ', $fields)), $m);
        foreach ($m[0] as $original) {
            $accented[Tokenizer::normalize($original)] ??= $original;
        }
        foreach ($fields as $field => $text) {
            $w = self::FIELD_WEIGHTS[$field];
            foreach (Tokenizer::analyze($text) as [$term, $word]) {
                $weights[$term] = ($weights[$term] ?? 0) + $w;
                $surface[$term] ??= $accented[$word] ?? $word;
                $length += $w;
            }
        }

        $old = [];
        if ($updateDocumentFrequencies) {
            $q = $this->db->prepare('SELECT term_id FROM postings WHERE doc_id = ?');
            $q->execute([$documentId]);
            $old = $q->fetchAll(PDO::FETCH_COLUMN);
        }
        $this->db->prepare('DELETE FROM postings WHERE doc_id = ?')->execute([$documentId]);

        $insert = $this->db->prepare('INSERT INTO postings (term_id, doc_id, tf) VALUES (?, ?, ?)');
        $new = [];
        foreach ($weights as $term => $tf) {
            $termId = $this->termId((string) $term, $surface[$term]);
            $insert->execute([$termId, $documentId, $tf]);
            $new[] = $termId;
        }

        $this->db->prepare('UPDATE documents SET doc_length = ?, search_text = ? WHERE id = ?')
            ->execute([$length, Tokenizer::normalize(implode(' ', $fields)), $documentId]);

        if ($updateDocumentFrequencies) {
            $this->updateDocumentFrequencies(array_unique(array_merge($old, $new)));
        }
    }

    /** Rebuilds the whole index. @return array{documents:int, terms:int, postings:int, seconds:float} */
    public function rebuild(): array
    {
        $start = microtime(true);
        $this->db->beginTransaction();
        $this->db->exec('DELETE FROM postings');
        $this->db->exec('DELETE FROM terms');
        $this->termIds = [];

        $ids = $this->db->query('SELECT id FROM documents ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            $this->index((int) $id, false);
        }
        $this->db->exec('UPDATE terms SET df = (SELECT COUNT(*) FROM postings p WHERE p.term_id = terms.id)');
        $this->db->commit();

        return [
            'documents' => count($ids),
            'terms' => (int) $this->db->query('SELECT COUNT(*) FROM terms')->fetchColumn(),
            'postings' => (int) $this->db->query('SELECT COUNT(*) FROM postings')->fetchColumn(),
            'seconds' => round(microtime(true) - $start, 2),
        ];
    }

    /** @return array<string,string> searchable text per field */
    /** @return array<string,string> searchable text per field — both languages are indexed. */
    public static function fields(array $doc): array
    {
        $both = static fn (string $f): string => trim(($doc[$f] ?? '') . "\n" . ($doc[$f . '_fr'] ?? ''));
        $label = static fn (?string $en): string => $en ? $en . ' ' . I18n::in('fr', $en) : '';
        return [
            'title' => $both('title'),
            'keywords' => $both('keywords'),
            'metadata' => implode(' ', array_filter([
                $doc['reference_no'],
                $doc['student_no'],
                $doc['student_name'],
                $both('author'),
                $label($doc['programme']),
                $doc['academic_year'],
                $label(Catalog::TYPES[$doc['document_type']] ?? $doc['document_type']),
                $label($doc['department_name'] ?? ''),
            ])),
            'description' => $both('description'),
            'content' => $both('content'),
        ];
    }

    private function termId(string $term, string $display): int
    {
        if (isset($this->termIds[$term])) {
            return $this->termIds[$term];
        }
        $find = $this->db->prepare('SELECT id FROM terms WHERE term = ?');
        $find->execute([$term]);
        $id = $find->fetchColumn();
        if ($id === false) {
            $this->db->prepare('INSERT INTO terms (term, display, df) VALUES (?, ?, 0)')
                ->execute([$term, mb_substr($display, 0, 64)]);
            $id = $this->db->lastInsertId();
        }
        return $this->termIds[$term] = (int) $id;
    }

    private function updateDocumentFrequencies(array $termIds): void
    {
        foreach (array_chunk(array_map('intval', $termIds), 500) as $chunk) {
            $this->db->exec(
                'UPDATE terms SET df = (SELECT COUNT(*) FROM postings p WHERE p.term_id = terms.id) WHERE id IN (' . implode(',', $chunk) . ')'
            );
        }
    }
}
