<?php

declare(strict_types=1);

namespace App;

/**
 * Bilingual interface (English / French).
 *
 * The English text itself is the translation key: __('Search') returns "Rechercher" in French,
 * and the key unchanged in English (or when no translation exists). Translations live in lang/fr.php.
 * The language is chosen with ?lang=en|fr (switcher in the header), remembered in the session and in a
 * cookie for one year, and defaults to the browser's preferred language.
 */
final class I18n
{
    public const LANGUAGES = ['en' => 'English', 'fr' => 'Français'];

    private static string $lang = 'en';
    private static array $dictionary = [];

    public static function init(): void
    {
        $requested = $_GET['lang'] ?? null;
        if (is_string($requested) && isset(self::LANGUAGES[$requested])) {
            $lang = $requested;
            if (PHP_SAPI !== 'cli' && !headers_sent()) {
                setcookie('lang', $lang, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax', 'httponly' => true]);
            }
        } else {
            $lang = $_SESSION['lang'] ?? $_COOKIE['lang'] ?? self::fromBrowser();
        }
        self::set(isset(self::LANGUAGES[$lang]) ? $lang : 'en');
    }

    public static function set(string $lang): void
    {
        self::$lang = isset(self::LANGUAGES[$lang]) ? $lang : 'en';
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['lang'] = self::$lang;
        }
        self::$dictionary = self::$lang === 'en' ? [] : (require APP_ROOT . '/lang/' . self::$lang . '.php');
    }

    public static function lang(): string
    {
        return self::$lang;
    }

    public static function translate(string $text, array $params = []): string
    {
        $out = self::$dictionary[$text] ?? $text;
        if ($params) {
            $out = strtr($out, array_combine(array_map(static fn ($k) => '{' . $k . '}', array_keys($params)), array_map('strval', $params)));
        }
        return $out;
    }

    /** Translation into a given language, whatever the current interface language (used by the indexer). */
    public static function in(string $lang, string $text): string
    {
        static $cache = [];
        if ($lang === 'en') {
            return $text;
        }
        $cache[$lang] ??= require APP_ROOT . '/lang/' . $lang . '.php';
        return $cache[$lang][$text] ?? $text;
    }

    public static function has(string $text): bool
    {
        return self::$lang === 'en' || isset(self::$dictionary[$text]);
    }

    /** Current URL with the language parameter replaced (for the switcher). */
    public static function switchUrl(string $lang): string
    {
        $path = strtok($_SERVER['REQUEST_URI'] ?? 'index.php', '?') ?: 'index.php';
        $query = $_GET;
        $query['lang'] = $lang;
        return basename($path) . '?' . http_build_query($query);
    }

    private static function fromBrowser(): string
    {
        $header = strtolower((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
        return str_starts_with($header, 'fr') ? 'fr' : 'en';
    }
}
