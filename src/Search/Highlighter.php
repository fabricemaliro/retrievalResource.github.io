<?php

declare(strict_types=1);

namespace App\Search;

/** Builds result snippets (the passage that best matches the query) and highlights query words. */
final class Highlighter
{
    public static function pattern(array $parsed): ?string
    {
        $lit = static fn (string $s): string => self::accentInsensitive(preg_quote($s, '/'));
        $word = '[\\p{L}]*';
        $parts = [];
        $words = $parsed['words'];
        foreach (array_keys($parsed['words']) as $stem) {
            foreach (Synonyms::for((string) $stem) as $synonym) {
                $words[$synonym] ??= $synonym;
            }
        }
        foreach ($words as $stem => $surface) {
            $stem = (string) $stem;
            if (strlen($stem) < 3) {
                $parts[] = $lit((string) $surface) . '\\b';
            } elseif (str_ends_with($stem, 'y')) {
                $parts[] = $lit(substr($stem, 0, -1)) . '(?:y|ies|ied)' . $word;
            } else {
                $parts[] = $lit($stem) . $word;
            }
        }
        foreach ($parsed['prefixes'] as $prefix) {
            $parts[] = $lit($prefix) . '[\\p{L}0-9]*';
        }
        foreach ($parsed['phrases'] as $phrase) {
            $parts[] = implode('\\W+', array_map($lit, explode(' ', $phrase)));
        }
        foreach ($parsed['ids'] as $original) {
            $parts[] = preg_quote((string) $original, '/');
        }
        if (!$parts) {
            return null;
        }
        usort($parts, static fn ($a, $b) => strlen($b) <=> strlen($a));
        return '/(*UCP)\\b(' . implode('|', $parts) . ')/iu';
    }

    /** Applied to literal text only: "releve" also matches "relevé", "exoneration" matches "exonération". */
    private static function accentInsensitive(string $quoted): string
    {
        return strtr($quoted, [
            'a' => '[aàâä]', 'e' => '[eéèêë]', 'i' => '[iîï]', 'o' => '[oôö]', 'u' => '[uùûü]', 'c' => '[cç]',
        ]);
    }

    /** Escapes text and wraps matches in <mark>. */
    public static function highlight(string $text, ?string $pattern): string
    {
        if ($pattern === null) {
            return e($text);
        }
        $parts = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return e($text);
        }
        $html = '';
        foreach ($parts as $i => $part) {
            $html .= $i % 2 === 1 ? '<mark>' . e($part) . '</mark>' : e($part);
        }
        return $html;
    }

    /** Picks the ~$length-character window containing the most distinct query words. */
    public static function snippet(string $text, ?string $pattern, int $length = 280): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if ($text === '') {
            return '';
        }
        if ($pattern === null || !preg_match_all($pattern, $text, $m, PREG_OFFSET_CAPTURE)) {
            return self::highlight(self::cut($text, 0, $length), $pattern);
        }

        $hits = array_slice($m[0], 0, 80);
        $bestStart = 0;
        $bestScore = -1;
        foreach ($hits as [, $pos]) {
            $start = max(0, $pos - 70);
            $distinct = [];
            foreach ($hits as [$word, $p]) {
                if ($p >= $start && $p < $start + $length) {
                    $distinct[strtolower($word)] = true;
                }
            }
            if (count($distinct) > $bestScore) {
                $bestScore = count($distinct);
                $bestStart = $start;
            }
        }
        return self::highlight(self::cut($text, $bestStart, $length), $pattern);
    }

    private static function cut(string $text, int $start, int $length): string
    {
        if ($start > 0) {
            $space = strpos($text, ' ', $start);
            $start = $space !== false && $space - $start < 25 ? $space + 1 : $start;
        }
        $piece = mb_strcut($text, $start, $length, 'UTF-8');
        $truncatedEnd = $start + strlen($piece) < strlen($text);
        if ($truncatedEnd) {
            $lastSpace = strrpos($piece, ' ');
            if ($lastSpace !== false && $lastSpace > $length * 0.6) {
                $piece = substr($piece, 0, $lastSpace);
            }
        }
        return ($start > 0 ? '… ' : '') . $piece . ($truncatedEnd ? ' …' : '');
    }
}
