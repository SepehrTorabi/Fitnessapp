<?php

declare(strict_types=1);

namespace App\Nutrition;

use App\Enum\ActivityLevel;
use App\Enum\Goal;
use App\Enum\Sex;

/**
 * Turns body data into a daily calorie and macro target.
 *
 * Deliberately free of Doctrine, the container and the clock: everything it
 * needs arrives as arguments, which is what makes it straightforward to test
 * against the published worked examples of the two formulas.
 */
final class CalorieCalculator
{
    /** Kilocalories per gram of each macronutrient (Atwater factors). */
    public const float KCAL_PER_GRAM_PROTEIN = 4.0;
    public const float KCAL_PER_GRAM_CARBS = 4.0;
    public const float KCAL_PER_GRAM_FAT = 9.0;

    /**
     * Share of the daily energy that comes from fat. Protein is fixed by body
     * weight and carbohydrate takes whatever is left, so this one number decides
     * the whole split. 25% sits inside the 20-35% range that the usual dietary
     * guidelines recommend.
     */
    private const float FAT_ENERGY_SHARE = 0.25;

    /**
     * Basal metabolic rate: the energy a body uses at complete rest.
     *
     * Uses Katch-McArdle when the body-fat figure is known, because a formula
     * based on lean mass beats one based on total weight - two people of the
     * same height and weight burn different amounts if one carries far more
     * muscle. Falls back to Mifflin-St Jeor, the most accurate of the formulas
     * that need only height, weight, age and sex.
     *
     * @param float|null $leanBodyMassKg fat-free mass, when a body-fat measurement exists
     *
     * @return array{0: float, 1: string} the BMR and the name of the formula used
     */
    public function basalMetabolicRate(
        float $weightKg,
        float $heightCm,
        int $ageYears,
        Sex $sex,
        ?float $leanBodyMassKg = null,
    ): array {
        if (null !== $leanBodyMassKg && $leanBodyMassKg > 0.0) {
            return [370.0 + (21.6 * $leanBodyMassKg), 'katch-mcardle'];
        }

        $bmr = (10.0 * $weightKg)
            + (6.25 * $heightCm)
            - (5.0 * $ageYears)
            + $sex->mifflinStJeorConstant();

        return [$bmr, 'mifflin-st-jeor'];
    }

    /**
     * Total daily energy expenditure: the BMR scaled by how much the user moves.
     */
    public function totalDailyEnergyExpenditure(float $bmr, ActivityLevel $activityLevel): float
    {
        return $bmr * $activityLevel->multiplier();
    }

    /**
     * The full daily target: calories, then the macro split that fills them.
     *
     * @param float|null $leanBodyMassKg fat-free mass, when known
     */
    public function calculate(
        float $weightKg,
        float $heightCm,
        int $ageYears,
        Sex $sex,
        ActivityLevel $activityLevel,
        Goal $goal,
        ?float $leanBodyMassKg = null,
    ): CalorieTarget {
        [$bmr, $formula] = $this->basalMetabolicRate($weightKg, $heightCm, $ageYears, $sex, $leanBodyMassKg);

        $tdee = $this->totalDailyEnergyExpenditure($bmr, $activityLevel);
        $targetKcal = $tdee * (1.0 + $goal->calorieAdjustmentFactor());

        return new CalorieTarget(
            $bmr,
            $tdee,
            $targetKcal,
            $this->macrosFor($targetKcal, $weightKg, $goal, $leanBodyMassKg),
            $formula,
        );
    }

    /**
     * Split a calorie budget into grams of protein, fat and carbohydrate.
     *
     * Protein first, because it is the one macro tied to body size rather than
     * to the calorie budget. When lean mass is known it is the better basis:
     * muscle is what the protein is there to maintain, and body fat does not
     * increase the requirement.
     *
     * Fat takes a fixed share of the energy, and carbohydrate absorbs the rest.
     * If protein and fat already exceed the budget - which happens on an
     * aggressive cut for a heavy, muscular user - carbohydrate clamps at zero
     * rather than going negative.
     */
    public function macrosFor(
        float $targetKcal,
        float $weightKg,
        Goal $goal,
        ?float $leanBodyMassKg = null,
    ): Nutrients {
        $proteinBasisKg = $leanBodyMassKg ?? $weightKg;
        $proteinG = $goal->proteinPerKilogram() * $proteinBasisKg;

        $fatG = ($targetKcal * self::FAT_ENERGY_SHARE) / self::KCAL_PER_GRAM_FAT;

        $remainingKcal = $targetKcal
            - ($proteinG * self::KCAL_PER_GRAM_PROTEIN)
            - ($fatG * self::KCAL_PER_GRAM_FAT);

        $carbsG = max(0.0, $remainingKcal / self::KCAL_PER_GRAM_CARBS);

        return new Nutrients($targetKcal, $proteinG, $carbsG, $fatG);
    }
}
