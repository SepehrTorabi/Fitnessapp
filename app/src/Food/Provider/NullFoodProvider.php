<?php

declare(strict_types=1);

namespace App\Food\Provider;

use App\Food\ExternalFoodResult;

/**
 * A provider that finds nothing.
 *
 * Wired in for the test environment, so the suite never depends on a third-party
 * service being reachable, and usable in production as a kill switch if the
 * external lookup ever has to be turned off.
 */
final class NullFoodProvider implements ExternalFoodProviderInterface
{
    public function searchByName(string $query, int $limit = 10): array
    {
        return [];
    }

    public function findByBarcode(string $barcode): ?ExternalFoodResult
    {
        return null;
    }
}
