<?php

declare(strict_types=1);

namespace App\Api\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ActivityRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Please describe the activity.')]
        #[Assert\Length(min: 2, max: 160)]
        public string $description = '',

        #[Assert\NotNull(message: 'Please enter how many calories you burned.')]
        #[Assert\Range(min: 0, max: 20000)]
        public ?float $caloriesBurned = null,

        #[Assert\Positive]
        #[Assert\LessThanOrEqual(1440)]
        public ?int $durationMinutes = null,

        /** Defaults to today. */
        #[Assert\Date(message: 'Use the format YYYY-MM-DD.')]
        public ?string $performedOn = null,
    ) {
    }
}
