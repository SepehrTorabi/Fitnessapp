<?php

declare(strict_types=1);

namespace App\Nutrition;

/**
 * The result of the daily calorie calculation: how much the user should eat and
 * where that number came from.
 *
 * The intermediate values are part of the result on purpose. A bare "2 341 kcal"
 * is not something a user can sanity-check, whereas "BMR 1 680, times 1.55 for
 * your activity level, minus 20% for the cut" is.
 */
final readonly class CalorieTarget
{
    public function __construct(
        public float $bmr,
        public float $tdee,
        public float $targetKcal,
        public Nutrients $macros,
        public string $formula,
    ) {
    }

    /**
     * @return array{bmr: float, tdee: float, targetKcal: float, formula: string, macros: array<string, float|null>}
     */
    public function toArray(): array
    {
        return [
            'bmr' => round($this->bmr),
            'tdee' => round($this->tdee),
            'targetKcal' => round($this->targetKcal),
            'formula' => $this->formula,
            'macros' => $this->macros->rounded(0)->toArray(),
        ];
    }
}
