<?php

declare(strict_types=1);

namespace App\Tests\Unit\Food;

use App\Enum\FoodSource;
use App\Food\Provider\OpenFoodFactsProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Exercises the mapping and the failure handling against a mocked client, so
 * the suite never touches the real Open Food Facts service.
 */
#[CoversClass(OpenFoodFactsProvider::class)]
final class OpenFoodFactsProviderTest extends TestCase
{
    public function testABarcodeLookupIsMappedOntoOurOwnShape(): void
    {
        $provider = $this->providerReturning([
            'status' => 1,
            'product' => [
                'code' => '4000417025005',
                'product_name' => 'Vollkornbrot',
                'brands' => 'Harry, Harry Brot',
                'serving_quantity' => 50,
                'nutriments' => [
                    'energy-kcal_100g' => 210,
                    'proteins_100g' => 7.2,
                    'carbohydrates_100g' => 36.1,
                    'fat_100g' => 1.9,
                    'fiber_100g' => 7.4,
                    'sugars_100g' => 2.1,
                    'salt_100g' => 1.15,
                ],
            ],
        ]);

        $result = $provider->findByBarcode('4000417025005');

        self::assertNotNull($result);
        self::assertSame('Vollkornbrot', $result->name);
        self::assertSame('4000417025005', $result->barcode);
        self::assertSame(FoodSource::OpenFoodFacts, $result->source);
        self::assertEqualsWithDelta(210.0, $result->per100->getKcal(), 0.01);
        self::assertEqualsWithDelta(7.2, $result->per100->getProteinG(), 0.01);
        self::assertEqualsWithDelta(1.15, $result->per100->getSaltG(), 0.01);
    }

    /**
     * "brands" is a comma-separated list; only the first entry is the brand.
     */
    public function testOnlyTheFirstBrandIsKept(): void
    {
        $provider = $this->providerReturning($this->product(['brands' => 'Harry, Harry Brot, Harry GmbH']));

        self::assertSame('Harry', $provider->findByBarcode('4000417025005')?->brand);
    }

    public function testAServingSizeBecomesANamedPortion(): void
    {
        $provider = $this->providerReturning($this->product(['serving_quantity' => 30]));

        self::assertSame(['serving' => 30.0], $provider->findByBarcode('4000417025005')?->portions);
    }

    /**
     * Most European products carry kilojoules only. Dropping them would lose a
     * large part of the database, so they are converted.
     */
    public function testKilojoulesAreConvertedWhenNoKcalValueIsGiven(): void
    {
        $provider = $this->providerReturning($this->product(nutriments: [
            'energy-kj_100g' => 1000,
            'proteins_100g' => 5,
            'carbohydrates_100g' => 20,
            'fat_100g' => 3,
        ]));

        // 1000 kJ / 4.184 = 239.0 kcal
        self::assertEqualsWithDelta(239.0, $provider->findByBarcode('4000417025005')?->per100->getKcal(), 0.1);
    }

    /**
     * Crowd-sourced records are often skeletons: a name and a barcode, no
     * nutrition table. Importing one would put a zero-calorie food into the
     * catalogue, which is worse than finding nothing.
     */
    public function testAProductWithNoEnergyValueIsRejected(): void
    {
        $provider = $this->providerReturning($this->product(nutriments: ['proteins_100g' => 5]));

        self::assertNull($provider->findByBarcode('4000417025005'));
    }

    public function testAProductWithoutANameIsRejected(): void
    {
        $provider = $this->providerReturning([
            'status' => 1,
            'product' => [
                'code' => '4000417025005',
                'nutriments' => ['energy-kcal_100g' => 210],
            ],
        ]);

        self::assertNull($provider->findByBarcode('4000417025005'));
    }

    public function testAnUnknownBarcodeReturnsNull(): void
    {
        $provider = new OpenFoodFactsProvider(
            new MockHttpClient(new MockResponse('{"status":0}', ['http_code' => 404])),
            new NullLogger(),
        );

        self::assertNull($provider->findByBarcode('0000000000000'));
    }

    /**
     * The contract says a provider never throws: an external lookup is an
     * optional extra, and a failing one must degrade to "no results" rather
     * than take the whole search down with it.
     */
    public function testAServerErrorDegradesToNoResultInsteadOfThrowing(): void
    {
        $provider = new OpenFoodFactsProvider(
            new MockHttpClient(new MockResponse('gateway down', ['http_code' => 502])),
            new NullLogger(),
        );

        self::assertNull($provider->findByBarcode('4000417025005'));
        self::assertSame([], $provider->searchByName('bread'));
    }

    public function testMalformedJsonDegradesToNoResult(): void
    {
        $provider = new OpenFoodFactsProvider(
            new MockHttpClient(new MockResponse('<html>not json</html>')),
            new NullLogger(),
        );

        self::assertSame([], $provider->searchByName('bread'));
    }

    public function testSearchMapsEveryUsableProductAndSkipsTheRest(): void
    {
        $provider = $this->providerReturning([
            'products' => [
                ['code' => '1', 'product_name' => 'Usable', 'nutriments' => ['energy-kcal_100g' => 100]],
                ['code' => '2', 'product_name' => 'No nutriments at all'],
                ['code' => '3', 'product_name' => 'Also usable', 'nutriments' => ['energy-kcal_100g' => 250]],
            ],
        ]);

        $results = $provider->searchByName('something');

        self::assertCount(2, $results);
        self::assertSame(['Usable', 'Also usable'], array_column($results, 'name'));
    }

    public function testAnEmptySearchTermNeverReachesTheNetwork(): void
    {
        $client = new MockHttpClient(static function (): MockResponse {
            self::fail('The provider should not have made a request for an empty query.');
        });

        self::assertSame([], (new OpenFoodFactsProvider($client, new NullLogger()))->searchByName('   '));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function providerReturning(array $payload): OpenFoodFactsProvider
    {
        return new OpenFoodFactsProvider(
            new MockHttpClient(new MockResponse(json_encode($payload, \JSON_THROW_ON_ERROR))),
            new NullLogger(),
        );
    }

    /**
     * @param array<string, mixed>      $overrides
     * @param array<string, mixed>|null $nutriments
     *
     * @return array<string, mixed>
     */
    private function product(array $overrides = [], ?array $nutriments = null): array
    {
        return [
            'status' => 1,
            'product' => array_merge([
                'code' => '4000417025005',
                'product_name' => 'Vollkornbrot',
                'nutriments' => $nutriments ?? ['energy-kcal_100g' => 210, 'proteins_100g' => 7.2],
            ], $overrides),
        ];
    }
}
