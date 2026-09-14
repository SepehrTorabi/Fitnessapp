<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Where the nutrition values of a food came from. Kept on the record so the UI
 * can show how trustworthy a number is, and so an import can be re-run without
 * touching hand-entered data.
 */
enum FoodSource: string
{
    case UserDefined = 'user_defined';
    case OpenFoodFacts = 'open_food_facts';
    case Seed = 'seed';
}
