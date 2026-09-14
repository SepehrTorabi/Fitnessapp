<?php

declare(strict_types=1);

namespace App\Api\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class MeasurementRequest
{
    public function __construct(
        #[Assert\NotNull(message: 'Please enter your weight.')]
        #[Assert\Range(
            min: 20,
            max: 500,
            notInRangeMessage: 'Weight must be between {{ min }} and {{ max }} kg.',
        )]
        public ?float $weightKg = null,

        #[Assert\Range(min: 0, max: 200)]
        public ?float $muscleMassKg = null,

        #[Assert\Range(min: 0, max: 300)]
        public ?float $fatMassKg = null,

        /** Defaults to today when the client does not say. */
        #[Assert\Date(message: 'Use the format YYYY-MM-DD.')]
        public ?string $measuredOn = null,
    ) {
    }
}
