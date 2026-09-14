<?php

declare(strict_types=1);

namespace App\Api\Dto;

use App\Enum\MealType;
use App\Enum\MeasurementUnit;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Logging something eaten. Either foodId or recipeId is set, never both - the
 * controller enforces that, because it is a rule about the pair rather than
 * about either field on its own.
 */
final readonly class DiaryEntryRequest
{
    public function __construct(
        #[Assert\NotNull]
        public ?MealType $mealType = null,

        #[Assert\NotNull(message: 'Please enter an amount.')]
        #[Assert\Positive(message: 'The amount must be greater than zero.')]
        #[Assert\LessThanOrEqual(10000)]
        public ?float $quantity = null,

        #[Assert\NotNull]
        public ?MeasurementUnit $unit = null,

        #[Assert\Positive]
        public ?int $foodId = null,

        #[Assert\Positive]
        public ?int $recipeId = null,

        /** Which named portion was meant, when the unit is a countable one. */
        #[Assert\Length(max: 64)]
        public ?string $portionLabel = null,

        /** Defaults to today. */
        #[Assert\Date(message: 'Use the format YYYY-MM-DD.')]
        public ?string $loggedOn = null,
    ) {
    }
}
