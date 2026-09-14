<?php

declare(strict_types=1);

namespace App\Api\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateFoodRequest
{
    /**
     * @param list<FoodPortionRequest> $portions
     */
    public function __construct(
        #[Assert\NotBlank(message: 'Please give the food a name.')]
        #[Assert\Length(min: 2, max: 200)]
        public string $name = '',

        // Nested DTOs need Valid, otherwise their own constraints are skipped.
        #[Assert\NotNull]
        #[Assert\Valid]
        public ?NutrientsRequest $per100 = null,

        #[Assert\Length(max: 120)]
        public ?string $brand = null,

        #[Assert\Regex(
            pattern: '/^\d{8,14}$/',
            message: 'A barcode is 8 to 14 digits.',
        )]
        public ?string $barcode = null,

        #[Assert\Range(min: 0.1, max: 5.0, notInRangeMessage: 'Density must be between {{ min }} and {{ max }} g/ml.')]
        public float $densityGPerMl = 1.0,

        #[Assert\Valid]
        #[Assert\Count(max: 20, maxMessage: 'A food can have at most {{ limit }} named portions.')]
        public array $portions = [],
    ) {
    }
}
