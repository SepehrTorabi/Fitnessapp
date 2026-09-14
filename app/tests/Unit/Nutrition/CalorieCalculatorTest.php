<?php

declare(strict_types=1);

namespace App\Tests\Unit\Nutrition;

use App\Enum\ActivityLevel;
use App\Enum\Goal;
use App\Enum\Sex;
use App\Nutrition\CalorieCalculator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The calculator is plain arithmetic with no dependencies, so these assert the
 * formulas against values worked out by hand rather than against whatever the
 * code happened to return when it was written.
 */
#[CoversClass(CalorieCalculator::class)]
final class CalorieCalculatorTest extends TestCase
{
    private CalorieCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new CalorieCalculator();
    }

    /**
     * Mifflin-St Jeor: 10w + 6.25h - 5a + 5 for men, - 161 for women.
     */
    public function testMifflinStJeorForAMan(): void
    {
        // 10*80 + 6.25*180 - 5*30 + 5 = 800 + 1125 - 150 + 5 = 1780
        [$bmr, $formula] = $this->calculator->basalMetabolicRate(80.0, 180.0, 30, Sex::Male);

        self::assertEqualsWithDelta(1780.0, $bmr, 0.01);
        self::assertSame('mifflin-st-jeor', $formula);
    }

    public function testMifflinStJeorForAWoman(): void
    {
        // 10*65 + 6.25*165 - 5*30 - 161 = 650 + 1031.25 - 150 - 161 = 1370.25
        [$bmr, $formula] = $this->calculator->basalMetabolicRate(65.0, 165.0, 30, Sex::Female);

        self::assertEqualsWithDelta(1370.25, $bmr, 0.01);
        self::assertSame('mifflin-st-jeor', $formula);
    }

    /**
     * Katch-McArdle: 370 + 21.6 * lean mass. It ignores height, age and sex
     * entirely, which is the whole point of preferring it when lean mass is
     * known.
     */
    public function testKatchMcArdleIsUsedWhenLeanMassIsKnown(): void
    {
        // 370 + 21.6*64 = 370 + 1382.4 = 1752.4
        [$bmr, $formula] = $this->calculator->basalMetabolicRate(80.0, 180.0, 30, Sex::Male, 64.0);

        self::assertEqualsWithDelta(1752.4, $bmr, 0.01);
        self::assertSame('katch-mcardle', $formula);
    }

    public function testKatchMcArdleIgnoresHeightAgeAndSex(): void
    {
        [$forOnePerson] = $this->calculator->basalMetabolicRate(80.0, 180.0, 30, Sex::Male, 64.0);
        [$forAnother] = $this->calculator->basalMetabolicRate(95.0, 155.0, 61, Sex::Female, 64.0);

        self::assertEqualsWithDelta($forOnePerson, $forAnother, 0.01);
    }

    /**
     * A zero or negative lean mass is not a measurement, it is bad data; the
     * calculator must fall back rather than return 370 kcal.
     */
    public function testZeroLeanMassFallsBackToMifflinStJeor(): void
    {
        [$bmr, $formula] = $this->calculator->basalMetabolicRate(80.0, 180.0, 30, Sex::Male, 0.0);

        self::assertSame('mifflin-st-jeor', $formula);
        self::assertEqualsWithDelta(1780.0, $bmr, 0.01);
    }

    #[DataProvider('activityLevels')]
    public function testActivityMultiplierScalesTheBmr(ActivityLevel $level, float $expectedMultiplier): void
    {
        $tdee = $this->calculator->totalDailyEnergyExpenditure(2000.0, $level);

        self::assertEqualsWithDelta(2000.0 * $expectedMultiplier, $tdee, 0.01);
    }

    /**
     * @return iterable<string, array{ActivityLevel, float}>
     */
    public static function activityLevels(): iterable
    {
        yield 'sedentary' => [ActivityLevel::Sedentary, 1.2];
        yield 'lightly active' => [ActivityLevel::LightlyActive, 1.375];
        yield 'moderately active' => [ActivityLevel::ModeratelyActive, 1.55];
        yield 'very active' => [ActivityLevel::VeryActive, 1.725];
        yield 'extra active' => [ActivityLevel::ExtraActive, 1.9];
    }

    #[DataProvider('goalAdjustments')]
    public function testGoalShiftsTheTargetAwayFromMaintenance(Goal $goal, float $expectedFactor): void
    {
        $target = $this->calculator->calculate(80.0, 180.0, 30, Sex::Male, ActivityLevel::Sedentary, $goal);

        // BMR 1780 * 1.2 = 2136 maintenance.
        self::assertEqualsWithDelta(2136.0, $target->tdee, 0.01);
        self::assertEqualsWithDelta(2136.0 * $expectedFactor, $target->targetKcal, 0.01);
    }

    /**
     * @return iterable<string, array{Goal, float}>
     */
    public static function goalAdjustments(): iterable
    {
        yield 'cutting is 20% under maintenance' => [Goal::LoseWeight, 0.8];
        yield 'maintaining sits at maintenance' => [Goal::MaintainWeight, 1.0];
        yield 'gaining is 10% over maintenance' => [Goal::GainMuscle, 1.1];
    }

    /**
     * The macro split has to actually add up to the calorie target, or the
     * dashboard shows two numbers that contradict each other.
     */
    public function testMacrosAddUpToTheCalorieTarget(): void
    {
        $target = $this->calculator->calculate(
            80.0, 180.0, 30, Sex::Male, ActivityLevel::ModeratelyActive, Goal::MaintainWeight,
        );

        $macros = $target->macros;
        $energyFromMacros = ($macros->getProteinG() * 4) + ($macros->getCarbsG() * 4) + ($macros->getFatG() * 9);

        self::assertEqualsWithDelta($target->targetKcal, $energyFromMacros, 0.5);
    }

    public function testProteinFollowsBodyWeightAndGoal(): void
    {
        // Maintenance is 1.6 g per kg: 1.6 * 80 = 128 g.
        $maintaining = $this->calculator->macrosFor(2500.0, 80.0, Goal::MaintainWeight);
        self::assertEqualsWithDelta(128.0, $maintaining->getProteinG(), 0.01);

        // Cutting raises it to 2.0 g per kg: 2.0 * 80 = 160 g.
        $cutting = $this->calculator->macrosFor(2500.0, 80.0, Goal::LoseWeight);
        self::assertEqualsWithDelta(160.0, $cutting->getProteinG(), 0.01);
    }

    /**
     * When body fat is known, protein is sized from lean mass instead - fat
     * tissue does not raise the requirement.
     */
    public function testProteinUsesLeanMassWhenItIsKnown(): void
    {
        $macros = $this->calculator->macrosFor(2500.0, 100.0, Goal::MaintainWeight, 70.0);

        self::assertEqualsWithDelta(1.6 * 70.0, $macros->getProteinG(), 0.01);
    }

    public function testFatTakesAQuarterOfTheEnergy(): void
    {
        // 2400 * 0.25 / 9 = 66.67 g
        $macros = $this->calculator->macrosFor(2400.0, 80.0, Goal::MaintainWeight);

        self::assertEqualsWithDelta(66.67, $macros->getFatG(), 0.01);
    }

    /**
     * A heavy, muscular user on an aggressive cut can need more protein and fat
     * than the budget holds. Carbohydrate must clamp at zero: a negative gram
     * figure would be nonsense, and would make the macro chart draw backwards.
     */
    public function testCarbsClampAtZeroInsteadOfGoingNegative(): void
    {
        $macros = $this->calculator->macrosFor(1200.0, 130.0, Goal::LoseWeight);

        self::assertSame(0.0, $macros->getCarbsG());
        self::assertGreaterThan(0.0, $macros->getProteinG());
    }
}
