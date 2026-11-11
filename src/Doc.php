<?php

declare(strict_types=1);

namespace App;

/**
 * Language-aware access to document fields.
 * English text is stored in title/description/keywords/content/author, French text in the *_fr columns.
 * A document uploaded in only one language falls back to the other one (and says so).
 */
final class Doc
{
    public const TRANSLATED_FIELDS = ['title', 'description', 'keywords', 'content', 'author'];

    public static function t(array $doc, string $field, ?string $lang = null): string
    {
        $lang ??= I18n::lang();
        $en = (string) ($doc[$field] ?? '');
        $fr = (string) ($doc[$field . '_fr'] ?? '');
        return $lang === 'fr' ? ($fr !== '' ? $fr : $en) : ($en !== '' ? $en : $fr);
    }

    /** Is the document available in this language (judged on its title)? */
    public static function available(array $doc, ?string $lang = null): bool
    {
        $lang ??= I18n::lang();
        return trim((string) ($lang === 'fr' ? ($doc['title_fr'] ?? '') : ($doc['title'] ?? ''))) !== '';
    }

    /** Language of the text actually shown when the document is not available in $lang. */
    public static function otherLanguageName(?string $lang = null): string
    {
        $lang ??= I18n::lang();
        $name = $lang === 'fr' ? __('English') : __('French');
        return I18n::lang() === 'fr' ? mb_strtolower($name) : $name;
    }
}
