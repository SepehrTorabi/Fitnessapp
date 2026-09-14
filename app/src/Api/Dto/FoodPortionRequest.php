<?php

declare(strict_types=1);

namespace App\Api\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class FoodPortionRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'A portion needs a name, for example "slice".')]
        #[Assert\Length(max: 64)]
        public string $label = '',

        #[Assert\NotNull]
        #[Assert\Positive(message: 'A portion must weigh more than zero grams.')]
        #[Assert\LessThanOrEqual(5000)]
        public ?float $grams = null,
    ) {
    }
}
