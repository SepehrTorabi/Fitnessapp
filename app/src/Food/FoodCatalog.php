<?php

declare(strict_types=1);

namespace App\Food;

use App\Entity\Food;
use App\Entity\FoodPortion;
use App\Entity\User;
use App\Food\Provider\ExternalFoodProviderInterface;
use App\Repository\FoodRepository;

/**
 * The single entry point for "find me this food".
 *
 * Search order is always local first. Our own catalogue holds the foods the user
 * defined and everything previously imported, so the common case never touches
 * the network; the external provider only fills in what we do not have yet.
 */
final class FoodCatalog
{
    public function __construct(
        private readonly FoodRepository $foods,
        private readonly ExternalFoodProviderInterface $provider,
    ) {
    }

    /**
     * Local hits, plus external candidates when the local catalogue is thin.
     *
     * External results are *not* saved here - they are offers. A food row is
     * written only once the user picks one, in {@see self::import()}.
     *
     * @return array{local: list<Food>, external: list<ExternalFoodResult>}
     */
    public function search(string $query, User $user, int $limit = 25): array
    {
        $local = $this->foods->search($query, $user, $limit);

        // Only go out to the network when our own answer is unconvincing.
        if (\count($local) >= 5) {
            return ['local' => $local, 'external' => []];
        }

        $external = $this->provider->searchByName($query, $limit - \count($local));

        return [
            'local' => $local,
            'external' => $this->rejectAlreadyKnown($external, $local),
        ];
    }

    /**
     * Resolve a scanned barcode to a food, importing it on first sight.
     *
     * Unlike a text search this does persist straight away: scanning a specific
     * product is a deliberate act, and the next scan of the same package should
     * not need the network at all.
     */
    public function findByBarcode(string $barcode): ?Food
    {
        $normalized = preg_replace('/\D+/', '', $barcode) ?? '';

        if ('' === $normalized) {
            return null;
        }

        $known = $this->foods->findOneByBarcode($normalized);

        if (null !== $known) {
            return $known;
        }

        $result = $this->provider->findByBarcode($normalized);

        if (null === $result) {
            return null;
        }

        return $this->import($result);
    }

    /**
     * Turn an external result into a catalogue entry.
     *
     * If the barcode is already known, the existing food wins: re-importing must
     * not create a duplicate, and must not overwrite values a user has corrected
     * by hand.
     */
    public function import(ExternalFoodResult $result): Food
    {
        if (null !== $result->barcode) {
            $existing = $this->foods->findOneByBarcode($result->barcode);

            if (null !== $existing) {
                return $existing;
            }
        }

        $food = new Food(
            $result->name,
            $result->per100,
            $result->source,
            $result->brand,
            $result->barcode,
        );
        $food->setExternalId($result->externalId);

        foreach ($result->portions as $label => $grams) {
            $food->addPortion(new FoodPortion($food, (string) $label, $grams));
        }

        $this->foods->save($food);

        return $food;
    }

    /**
     * Fetch a single external candidate again by its identifier, so the frontend
     * can import a search hit without sending the whole payload back to us -
     * which would let a client write arbitrary nutrition values into a food
     * labelled as coming from Open Food Facts.
     */
    public function importByExternalId(string $externalId): ?Food
    {
        $known = $this->foods->findOneBy(['externalId' => $externalId]);

        if (null !== $known) {
            return $known;
        }

        $result = $this->provider->findByBarcode($externalId);

        return null === $result ? null : $this->import($result);
    }

    /**
     * Drop external hits we already have locally, comparing on barcode.
     *
     * @param list<ExternalFoodResult> $external
     * @param list<Food>               $local
     *
     * @return list<ExternalFoodResult>
     */
    private function rejectAlreadyKnown(array $external, array $local): array
    {
        $knownBarcodes = [];

        foreach ($local as $food) {
            if (null !== $food->getBarcode()) {
                $knownBarcodes[$food->getBarcode()] = true;
            }
        }

        return array_values(array_filter(
            $external,
            static fn (ExternalFoodResult $r): bool => null === $r->barcode || !isset($knownBarcodes[$r->barcode]),
        ));
    }
}
