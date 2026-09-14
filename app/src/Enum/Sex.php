<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Biological sex, required by the BMR formulas (Mifflin-St Jeor applies a
 * different constant per sex). This is intentionally not the same thing as
 * gender identity - it is a parameter of a metabolic equation.
 */
enum Sex: string
{
    case Male = 'male';
    case Female = 'female';

    /**
     * Constant added in the Mifflin-St Jeor equation.
     */
    public function mifflinStJeorConstant(): float
    {
        return match ($this) {
            self::Male => 5.0,
            self::Female => -161.0,
        };
    }
}
