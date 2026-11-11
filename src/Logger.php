<?php

declare(strict_types=1);

namespace App;

/** Audit trail (who opened what) and search/click logs (used by the evaluation module). */
final class Logger
{
    public static function audit(?array $user, string $action, ?int $documentId = null, array $details = []): void
    {
        App::db()->prepare(
            'INSERT INTO audit_logs (user_id, action, document_id, details, ip, created_at) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $user['id'] ?? null,
            $action,
            $documentId,
            $details ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            $_SERVER['REMOTE_ADDR'] ?? 'cli',
            date('Y-m-d H:i:s'),
        ]);
    }

    public static function search(array $user, array $params, int $results, float $ms): int
    {
        $filters = array_filter(
            array_diff_key($params, ['q' => 1, 'page' => 1, 'sort' => 1]),
            static fn ($v) => $v !== '' && $v !== [] && $v !== false && $v !== null
        );
        $db = App::db();
        $db->prepare(
            'INSERT INTO search_logs (user_id, query, filters, results_count, duration_ms, created_at) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $user['id'],
            mb_substr($params['q'], 0, 255),
            $filters ? json_encode($filters, JSON_UNESCAPED_SLASHES) : null,
            $results,
            round($ms, 2),
            date('Y-m-d H:i:s'),
        ]);
        return (int) $db->lastInsertId();
    }

    /** Records that a result was opened from a results page (once per search/document). */
    public static function click(int $searchId, array $user, int $documentId, int $position): void
    {
        $db = App::db();
        $own = $db->prepare('SELECT 1 FROM search_logs WHERE id = ? AND user_id = ?');
        $own->execute([$searchId, $user['id']]);
        if (!$own->fetchColumn()) {
            return;
        }
        $dup = $db->prepare('SELECT 1 FROM click_logs WHERE search_id = ? AND document_id = ?');
        $dup->execute([$searchId, $documentId]);
        if ($dup->fetchColumn()) {
            return;
        }
        $db->prepare(
            'INSERT INTO click_logs (search_id, user_id, document_id, position, created_at) VALUES (?, ?, ?, ?, ?)'
        )->execute([$searchId, $user['id'], $documentId, $position, date('Y-m-d H:i:s')]);
    }
}
