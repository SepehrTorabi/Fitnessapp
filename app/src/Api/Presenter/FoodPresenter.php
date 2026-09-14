<?php

declare(strict_types=1);

namespace App\Api\Presenter;

use App\Entity\Food;
use App\Nutrition\PortionResolver;

final class FoodPresenter
{
    public function __construct(private readonly PortionResolver $portionResolver)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Food $food): array
    {
        return [
            'id' => $food->getId(),
            'name' => $food->getName(),
            'brand' => $food->getBrand(),
            'label' => $food->getLabel(),
            'barcode' => $food->getBarcode(),
            'source' => $food->getSource()->value,
            'densityGPerMl' => $food->getDensityGPerMl(),
            'per100' => $food->getPer100()->rounded(1)->toArray(),
            'portions' => array_map(
                static fn ($portion): array => [
                    'id' => $portion->getId(),
                    'label' => $portion->getLabel(),
                    'grams' => $portion->getGrams(),
                ],
                $food->getPortions()->toArray(),
            ),
            // Everything this particular food can be measured in, so the client
            // never offers "slice" for a bottle of milk.
            'availableUnits' => $this->portionResolver->availableUnitsFor($food),
        ];
    }

    /**
     * @param iterable<Food> $foods
     *
     * @return list<array<string, mixed>>
     */
    public function presentMany(iterable $foods): array
    {
        $out = [];

        foreach ($foods as $food) {
            $out[] = $this->present($food);
        }

        return $out;
    }
}
