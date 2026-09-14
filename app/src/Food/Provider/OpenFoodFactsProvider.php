<?php

declare(strict_types=1);

namespace App\Food\Provider;

use App\Enum\FoodSource;
use App\Food\ExternalFoodResult;
use App\Nutrition\Nutrients;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads product data from Open Food Facts, the open barcode database.
 *
 * Open Food Facts is crowd-sourced, so records are uneven: plenty of products
 * have a name and a barcode but no usable nutrition table at all. Anything
 * without an energy value per 100 g is dropped here rather than being allowed
 * into the catalogue as a food worth zero calories.
 *
 * @see https://openfoodfacts.github.io/openfoodfacts-server/api/
 */
final class OpenFoodFactsProvider implements ExternalFoodProviderInterface
{
    /**
     * The fields we actually read. Asking for a subset keeps the responses
     * small - a full Open Food Facts product document is well over 100 kB.
     */
    private const string FIELDS = 'code,product_name,product_name_de,product_name_en,brands,nutriments,serving_quantity,serving_size,quantity';

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly LoggerInterface $logger,
        private readonly string $baseUri = 'https://world.openfoodfacts.org',
    ) {
    }

    public function searchByName(string $query, int $limit = 10): array
    {
        $query = trim($query);

        if ('' === $query) {
            return [];
        }

        try {
            $response = $this->client->request('GET', $this->baseUri.'/cgi/search.pl', [
                'query' => [
                    'search_terms' => $query,
                    'search_simple' => 1,
                    'action' => 'process',
                    'json' => 1,
                    'page_size' => $limit,
                    'fields' => self::FIELDS,
                ],
            ]);

            $data = $response->toArray(false);
        } catch (HttpExceptionInterface|JsonException $e) {
            // An unreachable third party must not break our own search.
            $this->logger->warning('Open Food Facts search failed: {message}', [
                'message' => $e->getMessage(),
                'query' => $query,
            ]);

            return [];
        }

        $results = [];

        foreach ($data['products'] ?? [] as $product) {
            $result = $this->toResult(\is_array($product) ? $product : []);

            if (null !== $result) {
                $results[] = $result;
            }
        }

        return $results;
    }

    public function findByBarcode(string $barcode): ?ExternalFoodResult
    {
        $barcode = preg_replace('/\D+/', '', $barcode) ?? '';

        if ('' === $barcode) {
            return null;
        }

        try {
            $response = $this->client->request('GET', \sprintf('%s/api/v2/product/%s.json', $this->baseUri, $barcode), [
                'query' => ['fields' => self::FIELDS],
            ]);

            // A barcode that is simply not in the database answers 404. That is
            // an expected outcome, not an error worth logging.
            if (404 === $response->getStatusCode()) {
                return null;
            }

            $data = $response->toArray(false);
        } catch (HttpExceptionInterface|JsonException $e) {
            $this->logger->warning('Open Food Facts barcode lookup failed: {message}', [
                'message' => $e->getMessage(),
                'barcode' => $barcode,
            ]);

            return null;
        }

        if (1 !== ($data['status'] ?? 0) || !\is_array($data['product'] ?? null)) {
            return null;
        }

        return $this->toResult($data['product']);
    }

    /**
     * Map one Open Food Facts product onto our own shape, or null if it carries
     * too little information to be useful.
     *
     * @param array<string, mixed> $product
     */
    private function toResult(array $product): ?ExternalFoodResult
    {
        $name = $this->firstNonEmpty([
            $product['product_name_de'] ?? null,
            $product['product_name'] ?? null,
            $product['product_name_en'] ?? null,
        ]);

        $code = isset($product['code']) ? (string) $product['code'] : null;

        if (null === $name || null === $code || '' === $code) {
            return null;
        }

        $nutriments = \is_array($product['nutriments'] ?? null) ? $product['nutriments'] : [];

        $kcal = $this->energyKcalPer100($nutriments);

        // No energy value means the product has not been filled in properly.
        // Importing it would put a zero-calorie record into the catalogue.
        if (null === $kcal) {
            return null;
        }

        $per100 = new Nutrients(
            $kcal,
            $this->float($nutriments, 'proteins_100g') ?? 0.0,
            $this->float($nutriments, 'carbohydrates_100g') ?? 0.0,
            $this->float($nutriments, 'fat_100g') ?? 0.0,
            $this->float($nutriments, 'fiber_100g'),
            $this->float($nutriments, 'sugars_100g'),
            $this->float($nutriments, 'salt_100g'),
        );

        $portions = [];
        $servingGrams = $this->float($product, 'serving_quantity');

        if (null !== $servingGrams && $servingGrams > 0.0) {
            $portions['serving'] = $servingGrams;
        }

        $brands = isset($product['brands']) ? trim((string) $product['brands']) : '';
        // "brands" is a comma-separated list; the first entry is the main brand.
        $brand = '' === $brands ? null : trim(explode(',', $brands)[0]);

        return new ExternalFoodResult(
            name: $name,
            per100: $per100,
            source: FoodSource::OpenFoodFacts,
            externalId: $code,
            brand: $brand,
            barcode: $code,
            portions: $portions,
        );
    }

    /**
     * Energy per 100 g in kilocalories.
     *
     * Open Food Facts stores kJ for most European products and kcal for others,
     * so fall back to converting the kJ figure when the kcal one is missing.
     *
     * @param array<string, mixed> $nutriments
     */
    private function energyKcalPer100(array $nutriments): ?float
    {
        $kcal = $this->float($nutriments, 'energy-kcal_100g');

        if (null !== $kcal && $kcal > 0.0) {
            return $kcal;
        }

        $kj = $this->float($nutriments, 'energy-kj_100g') ?? $this->float($nutriments, 'energy_100g');

        if (null !== $kj && $kj > 0.0) {
            return $kj / 4.184;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function float(array $data, string $key): ?float
    {
        $value = $data[$key] ?? null;

        if (null === $value || '' === $value || !is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    /**
     * @param list<mixed> $candidates
     */
    private function firstNonEmpty(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (\is_string($candidate) && '' !== trim($candidate)) {
                return trim($candidate);
            }
        }

        return null;
    }
}
