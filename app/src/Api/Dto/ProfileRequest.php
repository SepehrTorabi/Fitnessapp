<?php

declare(strict_types=1);

namespace App\Api\Dto;

use App\Enum\ActivityLevel;
use App\Enum\Goal;
use App\Enum\Sex;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ProfileRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Please enter your date of birth.')]
        #[Assert\Date(message: 'Use the format YYYY-MM-DD.')]
        public string $birthDate = '',

        #[Assert\NotNull]
        public ?Sex $sex = null,

        #[Assert\NotNull(message: 'Please enter your height.')]
        #[Assert\Range(
            min: 80,
            max: 250,
            notInRangeMessage: 'Height must be between {{ min }} and {{ max }} cm.',
        )]
        public ?float $heightCm = null,

        #[Assert\NotNull]
        public ?ActivityLevel $activityLevel = null,

        #[Assert\NotNull]
        public ?Goal $goal = null,
    ) {
    }
}
