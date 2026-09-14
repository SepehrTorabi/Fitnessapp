<?php

declare(strict_types=1);

namespace App\Api\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RecipeRequest
{
    /**
     * @param list<RecipeIngredientRequest> $ingredients
     */
    public function __construct(
        #[Assert\NotBlank(message: 'Please give the recipe a name.')]
        #[Assert\Length(min: 2, max: 200)]
        public string $name = '',

        #[Assert\NotNull]
        #[Assert\Positive]
        #[Assert\LessThanOrEqual(100)]
        public ?int $servings = 1,

        #[Assert\Valid]
        #[Assert\Count(
            min: 1,
            max: 100,
            minMessage: 'A recipe needs at least one ingredient.',
            maxMessage: 'A recipe can have at most {{ limit }} ingredients.',
        )]
        public array $ingredients = [],

        #[Assert\Length(max: 2000)]
        public ?string $description = null,

        public bool $public = false,
    ) {
    }
}
