<?php

declare(strict_types=1);

namespace App\Nutrition;

use App\Entity\Food;
use App\Enum\MeasurementUnit;

/**
 * Turns "how much" into grams.
 *
 * Grams are the only unit the rest of the app deals in; this is the single place
 * where spoons, cups and slices are allowed to exist. When an amount cannot be
 * converted the resolver throws instead of guessing - a made-up slice weight
 * would quietly corrupt every total that day feeds into.
 */
final class PortionResolver
{
    /**
     * @throws UnresolvablePortionException if the unit needs a portion definition the food does not have
     */
    public function toGrams(
        Food $food,
        float $quantity,
        MeasurementUnit $unit,
        ?string $portionLabel = null,
    ): float {
        if ($quantity <= 0.0) {
            throw new UnresolvablePortionException('The amount must be greater than zero.');
        }

        if (MeasurementUnit::Gram === $unit) {
            return $quantity;
        }

        $milliliters = $unit->milliliters();

        if (null !== $milliliters) {
            // Volume to mass: 1 ml of water is 1 g, everything else scales by
            // its density. Oil comes out lighter, honey heavier.
            return $quantity * $milliliters * $food->getDensityGPerMl();
        }

        // Countable units: only the food itself knows what one of them weighs.
        $label = $portionLabel ?? $unit->value;
        $portion = $food->findPortion($label);

        if (null === $portion) {
            throw new UnresolvablePortionException(\sprintf(
                'The food "%s" has no portion called "%s". Define what one %s weighs, or enter the amount in grams.',
                $food->getLabel(),
                $label,
                $label,
            ));
        }

        return $quantity * $portion->getGrams();
    }

    /**
     * The units that can actually be used for a given food: the generic ones,
     * plus whatever named portions that food defines. The frontend uses this to
     * populate the unit dropdown, so the user is never offered "slice" for milk.
     *
     * @return list<array{unit: string, label: string, grams: float|null}>
     */
    public function availableUnitsFor(Food $food): array
    {
        $units = [];

        foreach ([MeasurementUnit::Gram, MeasurementUnit::Milliliter, MeasurementUnit::Teaspoon, MeasurementUnit::Tablespoon, MeasurementUnit::Cup] as $unit) {
            $milliliters = $unit->milliliters();

            $units[] = [
                'unit' => $unit->value,
                'label' => $unit->value,
                'grams' => null === $milliliters ? 1.0 : $milliliters * $food->getDensityGPerMl(),
            ];
        }

        foreach ($food->getPortions() as $portion) {
            $units[] = [
                'unit' => MeasurementUnit::Portion->value,
                'label' => $portion->getLabel(),
                'grams' => $portion->getGrams(),
            ];
        }

        return $units;
    }
}
