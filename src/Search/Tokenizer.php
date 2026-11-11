<?php

declare(strict_types=1);

namespace App\Search;

/**
 * Text analysis pipeline (English and French) used for BOTH indexing and querying, so that documents and queries
 * are reduced to the same terms:
 *   1. normalisation  (lower case, accents removed, punctuation → space)
 *   2. tokenisation   (split into words)
 *   3. stop-word removal ("the", "of", "and"...)
 *   4. light stemming ("transcripts" → "transcript", "admitted" → "admit")
 *   5. identifier tokens (reference / registration numbers such as LU/HR/2023/0042 → "luhr20230042")
 */
final class Tokenizer
{
    private const STOPWORDS = [
        'a', 'about', 'above', 'after', 'again', 'against', 'all', 'also', 'am', 'an', 'and', 'any', 'are', 'as', 'at',
        'be', 'because', 'been', 'before', 'being', 'below', 'between', 'both', 'but', 'by', 'can', 'could', 'did', 'do',
        'does', 'doing', 'down', 'during', 'each', 'few', 'for', 'from', 'further', 'had', 'has', 'have', 'having', 'he',
        'her', 'here', 'hers', 'him', 'his', 'how', 'i', 'if', 'in', 'into', 'is', 'it', 'its', 'itself', 'just', 'me',
        'more', 'most', 'my', 'no', 'nor', 'not', 'now', 'of', 'off', 'on', 'once', 'only', 'or', 'other', 'our', 'ours',
        'out', 'over', 'own', 'per', 'same', 'shall', 'she', 'should', 'so', 'some', 'such', 'than', 'that', 'the',
        'their', 'them', 'then', 'there', 'these', 'they', 'this', 'those', 'through', 'to', 'too', 'under', 'until',
        'up', 'upon', 'very', 'via', 'was', 'we', 'were', 'what', 'when', 'where', 'which', 'while', 'who', 'whom',
        'why', 'will', 'with', 'within', 'would', 'you', 'your', 'yours',
        // French
        'afin', 'ainsi', 'au', 'aux', 'avec', 'avoir', 'ce', 'ces', 'cet', 'cette', 'chaque', 'dans', 'de', 'des', 'dont', 'du',
        'elle', 'elles', 'en', 'entre', 'est', 'et', 'etre', 'ete', 'il', 'ils', 'la', 'le', 'les', 'leur', 'leurs', 'lors', 'ne',
        'nous', 'ou', 'par', 'pas', 'pour', 'qu', 'que', 'qui', 'sa', 'se', 'selon', 'ses', 'son', 'sont', 'sous', 'sur', 'toute',
        'toutes', 'tous', 'tout', 'un', 'une', 'vos', 'votre', 'vous',
    ];

    private const ACCENTS = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a', 'ç' => 'c', 'è' => 'e', 'é' => 'e',
        'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o',
        'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y',
        'œ' => 'oe', 'æ' => 'ae', 'ß' => 'ss', '’' => "'", '–' => '-', '—' => '-',
        'À' => 'a', 'Â' => 'a', 'Ç' => 'c', 'É' => 'e', 'È' => 'e', 'Ê' => 'e', 'Î' => 'i', 'Ô' => 'o', 'Û' => 'u', 'Œ' => 'oe',
    ];

    private static ?array $stop = null;

    public static function normalize(string $text): string
    {
        $text = strtr(mb_strtolower($text, 'UTF-8'), self::ACCENTS);
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text) ?? '';
        return trim($text);
    }

    /** @return list<string> normalised words, stop words included */
    public static function words(string $text): array
    {
        $normal = self::normalize($text);
        return $normal === '' ? [] : explode(' ', $normal);
    }

    public static function isStopword(string $word): bool
    {
        self::$stop ??= array_flip(self::STOPWORDS);
        return isset(self::$stop[$word]);
    }

    /** Light English/French stemmer (plurals, -ing, -ed, final -e). Deliberately simple and predictable:
     *  the same function is applied to documents and queries, so both languages are reduced consistently. */
    public static function stem(string $w): string
    {
        if (strlen($w) <= 3 || ctype_digit($w)) {
            return $w;
        }
        if (str_ends_with($w, 'eaux')) {                 // French plurals: bureaux → bureau, journaux → journal
            $w = substr($w, 0, -1);
        } elseif (str_ends_with($w, 'aux') && strlen($w) > 4) {
            $w = substr($w, 0, -3) . 'al';
        } elseif (str_ends_with($w, 'ies') && strlen($w) > 4) {
            $w = substr($w, 0, -3) . 'y';
        } elseif (str_ends_with($w, 'sses')) {
            $w = substr($w, 0, -2);
        } elseif (preg_match('/(xes|ches|shes)$/', $w)) {
            $w = substr($w, 0, -2);
        } elseif (str_ends_with($w, 's') && !preg_match('/(ss|us|is)$/', $w)) {
            $w = substr($w, 0, -1);
        }

        foreach (['ing', 'ed'] as $suffix) {
            if (str_ends_with($w, $suffix) && strlen($w) - strlen($suffix) >= 3) {
                $base = substr($w, 0, -strlen($suffix));
                if (preg_match('/[aeiouy]/', $base)) {
                    $w = preg_match('/([bdgmnprt])\1$/', $base) ? substr($base, 0, -1) : $base;
                    break;
                }
            }
        }

        if (str_ends_with($w, 'e') && strlen($w) > 4) {
            $w = substr($w, 0, -1);
        }
        return $w;
    }

    /** Compact form of an identifier: "LU/HR/2023/0042" → "luhr20230042". */
    public static function compact(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($value)) ?? '';
    }

    /** @return array<string,string> compact identifier => original text */
    public static function identifiers(string $text): array
    {
        $out = [];
        if (preg_match_all('~\b[0-9A-Za-z]+(?:[/\-][0-9A-Za-z]+)+\b~', $text, $m)) {
            foreach ($m[0] as $id) {
                if (preg_match('/\d/', $id)) {
                    $compact = self::compact($id);
                    if (strlen($compact) >= 4 && strlen($compact) <= 60) {
                        $out[$compact] = $id;
                    }
                }
            }
        }
        return $out;
    }

    /**
     * Full analysis of a text for indexing.
     * @return list<array{0:string, 1:string}> list of [term, surface word]
     */
    public static function analyze(string $text): array
    {
        $terms = [];
        foreach (self::words($text) as $word) {
            if (self::isStopword($word) || (strlen($word) < 2 && !ctype_digit($word)) || strlen($word) > 40) {
                continue;
            }
            $terms[] = [self::stem($word), $word];
        }
        foreach (self::identifiers($text) as $compact => $original) {
            $terms[] = [$compact, $original];
        }
        return $terms;
    }
}
