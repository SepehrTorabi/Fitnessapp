<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\AppLocale;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AppLocale::class)]
final class AppLocaleTest extends TestCase
{
    #[DataProvider('acceptLanguageHeaders')]
    public function testItPicksTheBestSupportedLanguage(?string $header, ?AppLocale $expected): void
    {
        self::assertSame($expected, AppLocale::fromAcceptLanguage($header));
    }

    /**
     * @return iterable<string, array{string|null, AppLocale|null}>
     */
    public static function acceptLanguageHeaders(): iterable
    {
        yield 'a bare tag' => ['de', AppLocale::German];

        // The region has to be stripped: "de-AT" is still German to us.
        yield 'a regional tag' => ['de-AT', AppLocale::German];

        yield 'a full browser header' => ['de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7', AppLocale::German];

        // Browsers send the header in preference order, so the first match wins
        // even though English appears later with a lower quality value.
        yield 'first supported entry wins' => ['en-GB,en;q=0.9,de;q=0.8', AppLocale::English];

        // A language we do not speak must not silently become English here -
        // the caller decides what to do about "none of these".
        yield 'a language we do not have' => ['fr-FR,fr;q=0.9', null];

        // ...but a later entry we do speak is still found.
        yield 'an unsupported language before a supported one' => ['fr,de;q=0.8', AppLocale::German];

        yield 'empty' => ['', null];
        yield 'absent' => [null, null];
        yield 'nonsense' => ['%%%', null];
    }

    public function testEveryLanguageNamesItselfInItsOwnLanguage(): void
    {
        // A language picker has to be readable by someone who cannot read the
        // current language - that is the whole reason they are using it.
        self::assertSame('English', AppLocale::English->endonym());
        self::assertSame('Deutsch', AppLocale::German->endonym());
    }

    public function testTheDefaultIsEnglish(): void
    {
        self::assertSame(AppLocale::English, AppLocale::default());
    }
}
