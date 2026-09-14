<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Food;
use App\Enum\FoodSource;
use App\Food\ExternalFoodResult;
use App\Food\Provider\ExternalFoodProviderInterface;
use App\Nutrition\Nutrients;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\HttpFoundation\Response;

#[CoversNothing]
final class FoodApiTest extends ApiTestCase
{
    public function testSearchingFindsAFoodByName(): void
    {
        $user = $this->createUser();
        $this->createFood('Wholegrain bread');
        $this->login($user);

        $this->jsonRequest('GET', '/api/foods?q=bread');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->responseData()['local']);
    }

    public function testSearchingNeedsAtLeastTwoCharacters(): void
    {
        $this->login($this->createUser());

        $this->jsonRequest('GET', '/api/foods?q=b');

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertSame('query_too_short', $this->responseData()['error']);
    }

    /**
     * Foods somebody defined for themselves are theirs; shared ones belong to
     * nobody and everyone sees them.
     */
    public function testAUserDefinedFoodIsInvisibleToOtherUsers(): void
    {
        $owner = $this->createUser('owner@example.test');
        $this->createFood('Grandmas secret cake', owner: $owner);

        $this->login($owner);
        $this->jsonRequest('GET', '/api/foods?q=secret');
        self::assertCount(1, $this->responseData()['local']);

        $this->login($this->createUser('stranger@example.test'));
        $this->jsonRequest('GET', '/api/foods?q=secret');
        self::assertCount(0, $this->responseData()['local']);
    }

    public function testDefiningAFoodStoresItAndReturnsItsUnits(): void
    {
        $this->login($this->createUser());

        $this->jsonRequest('POST', '/api/foods', [
            'name' => 'Rolled oats',
            'brand' => 'Kölln',
            'per100' => ['kcal' => 372, 'proteinG' => 13.5, 'carbsG' => 58.7, 'fatG' => 7.0, 'fiberG' => 10.0],
            'densityGPerMl' => 0.4,
            'portions' => [['label' => 'scoop', 'grams' => 40]],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $food = $this->responseData()['food'];
        self::assertSame('Rolled oats (Kölln)', $food['label']);
        self::assertSame(FoodSource::UserDefined->value, $food['source']);
        self::assertContains('scoop', array_column($food['availableUnits'], 'label'));
    }

    public function testANewlyDefinedFoodIsImmediatelySearchable(): void
    {
        $this->login($this->createUser());

        $this->jsonRequest('POST', '/api/foods', [
            'name' => 'Skyr natur',
            'per100' => ['kcal' => 63, 'proteinG' => 11.0, 'carbsG' => 4.0, 'fatG' => 0.2],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->jsonRequest('GET', '/api/foods?q=skyr');

        self::assertSame('Skyr natur', $this->responseData()['local'][0]['name']);
    }

    /**
     * Calories that disagree with the macros usually mean a per-serving figure
     * landed in a per-100 g field. Worth a warning, not worth a rejection.
     */
    public function testMacrosThatContradictTheCaloriesProduceAWarning(): void
    {
        $this->login($this->createUser());

        $this->jsonRequest('POST', '/api/foods', [
            'name' => 'Suspicious food',
            'per100' => ['kcal' => 50, 'proteinG' => 20.0, 'carbsG' => 30.0, 'fatG' => 10.0],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertNotEmpty($this->responseData()['warnings']);
    }

    public function testConsistentValuesProduceNoWarning(): void
    {
        $this->login($this->createUser());

        // 10*4 + 20*4 + 5*9 = 165 kcal
        $this->jsonRequest('POST', '/api/foods', [
            'name' => 'Consistent food',
            'per100' => ['kcal' => 165, 'proteinG' => 10.0, 'carbsG' => 20.0, 'fatG' => 5.0],
        ]);

        self::assertSame([], $this->responseData()['warnings']);
    }

    public function testImpossibleNutritionValuesAreRejected(): void
    {
        $this->login($this->createUser());

        $this->jsonRequest('POST', '/api/foods', [
            'name' => 'Impossible food',
            'per100' => ['kcal' => 5000, 'proteinG' => -3.0, 'carbsG' => 20.0, 'fatG' => 5.0],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testTheSameBarcodeCannotBeUsedTwice(): void
    {
        $this->login($this->createUser());

        $payload = [
            'name' => 'Barcoded food',
            'barcode' => '4000417025005',
            'per100' => ['kcal' => 200, 'proteinG' => 5.0, 'carbsG' => 30.0, 'fatG' => 6.0],
        ];

        $this->jsonRequest('POST', '/api/foods', $payload);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->jsonRequest('POST', '/api/foods', $payload);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('barcode_taken', $this->responseData()['error']);
    }

    public function testScanningAKnownBarcodeReturnsTheLocalFoodWithoutTheNetwork(): void
    {
        $user = $this->createUser();

        $food = new Food(
            'Vollkornbrot',
            new Nutrients(210.0, 7.2, 36.1, 1.9),
            FoodSource::OpenFoodFacts,
            'Harry',
            '4000417025005',
        );
        $this->entityManager->persist($food);
        $this->entityManager->flush();

        $this->login($user);
        $this->jsonRequest('GET', '/api/foods/barcode/4000417025005');

        self::assertResponseIsSuccessful();
        self::assertSame('Vollkornbrot', $this->responseData()['food']['name']);
    }

    /**
     * The external provider is stubbed rather than called, so the suite neither
     * depends on Open Food Facts being up nor sends it traffic.
     */
    public function testScanningAnUnknownBarcodeImportsItFromTheProvider(): void
    {
        static::getContainer()->set(
            ExternalFoodProviderInterface::class,
            new StubFoodProvider(new ExternalFoodResult(
                name: 'Imported product',
                per100: new Nutrients(180.0, 6.0, 28.0, 4.0),
                source: FoodSource::OpenFoodFacts,
                externalId: '1234567890123',
                brand: 'Somebrand',
                barcode: '1234567890123',
                portions: ['serving' => 30.0],
            )),
        );

        $this->login($this->createUser());
        $this->jsonRequest('GET', '/api/foods/barcode/1234567890123');

        self::assertResponseIsSuccessful();

        $food = $this->responseData()['food'];
        self::assertSame('Imported product', $food['name']);
        self::assertSame(FoodSource::OpenFoodFacts->value, $food['source']);
        self::assertContains('serving', array_column($food['availableUnits'], 'label'));
    }

    public function testAnImportedProductIsStoredSoTheSecondScanIsLocal(): void
    {
        static::getContainer()->set(
            ExternalFoodProviderInterface::class,
            new StubFoodProvider(new ExternalFoodResult(
                name: 'Imported once',
                per100: new Nutrients(180.0, 6.0, 28.0, 4.0),
                source: FoodSource::OpenFoodFacts,
                externalId: '1234567890123',
                barcode: '1234567890123',
            )),
        );

        $this->login($this->createUser());

        $this->jsonRequest('GET', '/api/foods/barcode/1234567890123');
        $firstId = $this->responseData()['food']['id'];

        $this->jsonRequest('GET', '/api/foods/barcode/1234567890123');

        self::assertSame($firstId, $this->responseData()['food']['id'], 'A second scan must reuse the imported row.');
    }

    public function testAnUnknownBarcodeThatTheProviderCannotResolveIsA404(): void
    {
        $this->login($this->createUser());

        $this->jsonRequest('GET', '/api/foods/barcode/9999999999999');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('barcode_not_found', $this->responseData()['error']);
    }

    public function testTheFoodCatalogueRequiresAuthentication(): void
    {
        $this->jsonRequest('GET', '/api/foods?q=bread');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}

/**
 * A provider that always answers with one canned result. Test-only.
 */
final class StubFoodProvider implements ExternalFoodProviderInterface
{
    public function __construct(private readonly ExternalFoodResult $result)
    {
    }

    public function searchByName(string $query, int $limit = 10): array
    {
        return [$this->result];
    }

    public function findByBarcode(string $barcode): ?ExternalFoodResult
    {
        return $this->result->barcode === $barcode ? $this->result : null;
    }
}
