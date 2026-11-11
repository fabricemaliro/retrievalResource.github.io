<?php

declare(strict_types=1);

namespace App\Search;

/**
 * Query syntax supported in the search box:
 *   transcript okello          words (all are scored, documents matching more words rank higher)
 *   "examination malpractice"  exact phrase
 *   -draft                     exclude documents containing a word
 *   admis*                     prefix (admission, admissions, admitted...)
 *   LU/HR/2023/0042            reference / registration number (exact identifier match is boosted)
 */
final class QueryParser
{
    public static function parse(string $query): array
    {
        $out = [
            'raw' => $query,
            'words' => [],      // stem => surface word
            'phrases' => [],    // normalised phrases
            'exclude' => [],    // stems
            'prefixes' => [],   // normalised prefixes
            'ids' => [],        // compact identifier => original
            'ignored' => [],    // stop words removed from the query
        ];

        preg_match_all('/(-?)"([^"]*)"|(-?)([^\s"]+)/u', $query, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $token = $m[4] ?? '';
            if ($token === '') {                      // quoted phrase
                $negative = $m[1] === '-';
                $phrase = Tokenizer::normalize($m[2] ?? '');
                if ($phrase === '') {
                    continue;
                }
                if ($negative) {
                    foreach (explode(' ', $phrase) as $w) {
                        if (!Tokenizer::isStopword($w)) {
                            $out['exclude'][] = Tokenizer::stem($w);
                        }
                    }
                    continue;
                }
                $out['phrases'][] = $phrase;
                self::addWords($out, $phrase);
                continue;
            }

            $negative = $m[3] === '-';
            $ids = Tokenizer::identifiers($token);
            if ($ids && !$negative) {
                $out['ids'] += $ids;
                continue;
            }
            if (str_ends_with($token, '*') && !$negative) {
                $prefix = Tokenizer::normalize(rtrim($token, '*'));
                if (strlen($prefix) >= 3 && !str_contains($prefix, ' ')) {
                    $out['prefixes'][] = $prefix;
                    continue;
                }
            }
            if ($negative) {
                foreach (Tokenizer::words($token) as $w) {
                    if (!Tokenizer::isStopword($w)) {
                        $out['exclude'][] = Tokenizer::stem($w);
                    }
                }
                continue;
            }
            self::addWords($out, $token);
        }

        $out['exclude'] = array_values(array_unique($out['exclude']));
        $out['prefixes'] = array_values(array_unique($out['prefixes']));
        $out['ignored'] = array_values(array_unique($out['ignored']));
        return $out;
    }

    private static function addWords(array &$out, string $text): void
    {
        foreach (Tokenizer::words($text) as $w) {
            if (Tokenizer::isStopword($w)) {
                $out['ignored'][] = $w;
                continue;
            }
            if (strlen($w) < 2 && !ctype_digit($w)) {
                continue;
            }
            $out['words'][Tokenizer::stem($w)] ??= $w;
        }
    }

    public static function isEmpty(array $parsed): bool
    {
        return !$parsed['words'] && !$parsed['phrases'] && !$parsed['prefixes'] && !$parsed['ids'];
    }
}
