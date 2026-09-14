<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * What the user wants their weight to do. Shifts the calorie target away from
 * maintenance and changes how the macro split is chosen.
 */
enum Goal: string
{
    case LoseWeight = 'lose_weight';
    case MaintainWeight = 'maintain_weight';
    case GainMuscle = 'gain_muscle';

    /**
     * Fraction of the total daily energy expenditure added or removed.
     *
     * -20% is the usual recommendation for a sustainable cut; +10% keeps a
     * lean bulk from turning into mostly fat gain.
     */
    public function calorieAdjustmentFactor(): float
    {
        return match ($this) {
            self::LoseWeight => -0.20,
            self::MaintainWeight => 0.0,
            self::GainMuscle => 0.10,
        };
    }

    /**
     * Grams of protein per kilogram of body weight (or of lean mass when it is
     * known). Higher while cutting, to protect lean mass in a deficit.
     */
    public function proteinPerKilogram(): float
    {
        return match ($this) {
            self::LoseWeight => 2.0,
            self::MaintainWeight => 1.6,
            self::GainMuscle => 1.8,
        };
    }
}
