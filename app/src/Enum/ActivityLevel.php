<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Physical Activity Level: the multiplier applied to the basal metabolic rate
 * to obtain the total daily energy expenditure.
 */
enum ActivityLevel: string
{
    case Sedentary = 'sedentary';
    case LightlyActive = 'lightly_active';
    case ModeratelyActive = 'moderately_active';
    case VeryActive = 'very_active';
    case ExtraActive = 'extra_active';

    public function multiplier(): float
    {
        return match ($this) {
            self::Sedentary => 1.2,
            self::LightlyActive => 1.375,
            self::ModeratelyActive => 1.55,
            self::VeryActive => 1.725,
            self::ExtraActive => 1.9,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Sedentary => 'Little or no exercise, desk job',
            self::LightlyActive => 'Light exercise 1-3 days per week',
            self::ModeratelyActive => 'Moderate exercise 3-5 days per week',
            self::VeryActive => 'Hard exercise 6-7 days per week',
            self::ExtraActive => 'Very hard exercise, physical job or training twice a day',
        };
    }
}
