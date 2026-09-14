<?php

declare(strict_types=1);

namespace App\Food\Provider;

use App\Food\ExternalFoodResult;

/**
 * A source of nutrition data outside our own database.
 *
 * The app talks to this interface, never to a concrete provider, so swapping
 * Open Food Facts for USDA - or adding a second provider behind the first - is
 * a change in the service configuration rather than in the controllers.
 *
 * Implementations must never throw on network trouble: an external lookup is a
 * best-effort enrichment, and a failing one has to degrade to "no results"
 * rather than take down the search.
 */
interface ExternalFoodProviderInterface
{
    /**
     * @return list<ExternalFoodResult>
     */
    public function searchByName(string $query, int $limit = 10): array;

    public function findByBarcode(string $barcode): ?ExternalFoodResult;
}
