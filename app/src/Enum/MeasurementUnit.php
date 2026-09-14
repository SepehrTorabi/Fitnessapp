<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * How a user expresses "how much did I eat".
 *
 * Two families of unit live here:
 *
 *  - Volumetric and mass units (gram, millilitre, teaspoon, tablespoon, cup)
 *    convert to grams on their own, using the food's density for the
 *    volume-based ones. They are exact enough to resolve without any per-food
 *    knowledge.
 *
 *  - Countable units (piece, slice, portion, handful) mean nothing without the
 *    food: a slice of bread and a slice of cake are not the same weight. These
 *    resolve only when the food defines a matching {@see \App\Entity\FoodPortion},
 *    otherwise the resolver rejects the entry instead of inventing a number.
 */
enum MeasurementUnit: string
{
    case Gram = 'g';
    case Milliliter = 'ml';
    case Teaspoon = 'tsp';
    case Tablespoon = 'tbsp';
    case Cup = 'cup';
    case Piece = 'piece';
    case Slice = 'slice';
    case Portion = 'portion';
    case Handful = 'handful';

    /**
     * Volume in millilitres for the volumetric units, null for everything else.
     */
    public function milliliters(): ?float
    {
        return match ($this) {
            self::Milliliter => 1.0,
            self::Teaspoon => 5.0,
            self::Tablespoon => 15.0,
            self::Cup => 240.0,
            default => null,
        };
    }

    /**
     * Whether this unit needs a per-food portion definition to become grams.
     */
    public function requiresFoodPortion(): bool
    {
        return match ($this) {
            self::Piece, self::Slice, self::Portion, self::Handful => true,
            default => false,
        };
    }
}
