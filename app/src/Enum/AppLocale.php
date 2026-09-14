<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The languages the application is translated into.
 *
 * Named AppLocale rather than Locale to avoid colliding with the Locale class
 * from the intl extension, which is in the global namespace.
 *
 * Adding a language means adding a case here, a messages file on the frontend
 * and a translations file for the e-mails - and nothing else. Everything that
 * accepts a locale validates against these cases, so an unknown code is
 * rejected at the edge instead of silently falling through to English.
 */
enum AppLocale: string
{
    case English = 'en';
    case German = 'de';

    /**
     * The language's name in that language itself, which is how a language
     * picker should list it: someone looking for German is looking for
     * "Deutsch", not for the word "German" in a language they do not read.
     */
    public function endonym(): string
    {
        return match ($this) {
            self::English => 'English',
            self::German => 'Deutsch',
        };
    }

    public static function default(): self
    {
        return self::English;
    }

    /**
     * Best match for an Accept-Language header, or null if we speak none of the
     * languages the browser asked for.
     *
     * Used at registration, when the user has had no chance to choose yet and
     * the browser's preference is the only signal available.
     */
    public static function fromAcceptLanguage(?string $header): ?self
    {
        if (null === $header || '' === trim($header)) {
            return null;
        }

        // "de-DE,de;q=0.9,en;q=0.8" - take the primary subtag of each entry in
        // order and return the first one we support. Quality values are ignored:
        // browsers already send the header in preference order.
        foreach (explode(',', $header) as $entry) {
            $tag = trim(explode(';', $entry)[0]);
            $primary = strtolower(explode('-', $tag)[0]);

            $match = self::tryFrom($primary);

            if (null !== $match) {
                return $match;
            }
        }

        return null;
    }
}
