<?php

declare(strict_types=1);

namespace App\Api\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RecipeIngredientRequest
{
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Positive]
        public ?int $foodId = null,

        #[Assert\NotNull]
        #[Assert\Positive(message: 'An ingredient must weigh more than zero grams.')]
        #[Assert\LessThanOrEqual(20000)]
        public ?float $grams = null,
    ) {
    }
}
