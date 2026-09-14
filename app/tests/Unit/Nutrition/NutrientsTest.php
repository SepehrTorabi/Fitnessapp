<?php

declare(strict_types=1);

namespace App\Tests\Unit\Nutrition;

use App\Nutrition\Nutrients;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Nutrients::class)]
final class NutrientsTest extends TestCase
{
    public function testScalingMultipliesEveryValue(): void
    {
        $per100 = new Nutrients(250.0, 10.0, 30.0, 8.0, 4.0, 2.0, 1.0);

        $for150g = $per100->scaled(1.5);

        self::assertEqualsWithDelta(375.0, $for150g->getKcal(), 0.001);
        self::assertEqualsWithDelta(15.0, $for150g->getProteinG(), 0.001);
        self::assertEqualsWithDelta(45.0, $for150g->getCarbsG(), 0.001);
        self::assertEqualsWithDelta(12.0, $for150g->getFatG(), 0.001);
        self::assertEqualsWithDelta(6.0, $for150g->getFiberG(), 0.001);
    }

    public function testScalingIsImmutable(): void
    {
        $original = new Nutrients(250.0, 10.0, 30.0, 8.0);

        $original->scaled(2.0);

        self::assertSame(250.0, $original->getKcal());
    }

    public function testUnknownValuesStayUnknownWhenScaled(): void
    {
        $nutrients = new Nutrients(100.0, 5.0, 10.0, 2.0);

        self::assertNull($nutrients->scaled(3.0)->getFiberG());
    }

    public function testAddingSumsTheCoreMacros(): void
    {
        $a = new Nutrients(100.0, 5.0, 10.0, 2.0);
        $b = new Nutrients(250.0, 12.0, 30.0, 9.0);

        $sum = $a->plus($b);

        self::assertEqualsWithDelta(350.0, $sum->getKcal(), 0.001);
        self::assertEqualsWithDelta(17.0, $sum->getProteinG(), 0.001);
        self::assertEqualsWithDelta(40.0, $sum->getCarbsG(), 0.001);
        self::assertEqualsWithDelta(11.0, $sum->getFatG(), 0.001);
    }

    /**
     * A day's fibre total should be "at least this much" rather than collapsing
     * to unknown because one food in it has no fibre figure.
     */
    public function testAddingAKnownValueToAnUnknownOneKeepsTheKnownOne(): void
    {
        $withFiber = new Nutrients(100.0, 5.0, 10.0, 2.0, fiberG: 3.0);
        $withoutFiber = new Nutrients(100.0, 5.0, 10.0, 2.0);

        self::assertEqualsWithDelta(3.0, $withFiber->plus($withoutFiber)->getFiberG(), 0.001);
        self::assertEqualsWithDelta(3.0, $withoutFiber->plus($withFiber)->getFiberG(), 0.001);
    }

    public function testAddingTwoUnknownsStaysUnknown(): void
    {
        $a = new Nutrients(100.0, 5.0, 10.0, 2.0);
        $b = new Nutrients(200.0, 8.0, 20.0, 4.0);

        self::assertNull($a->plus($b)->getFiberG());
    }

    /**
     * Zero and "not known" are different things: a food with 0 g of fibre has
     * been measured, one with null has not.
     */
    public function testZeroIsNotTheSameAsUnknown(): void
    {
        $measuredAsZero = new Nutrients(100.0, 5.0, 10.0, 2.0, fiberG: 0.0);
        $notMeasured = new Nutrients(100.0, 5.0, 10.0, 2.0);

        self::assertSame(0.0, $measuredAsZero->getFiberG());
        self::assertNull($notMeasured->getFiberG());
    }

    public function testZeroIsTheAdditiveIdentity(): void
    {
        $nutrients = new Nutrients(123.4, 5.6, 7.8, 9.1);

        $sum = Nutrients::zero()->plus($nutrients);

        self::assertEqualsWithDelta(123.4, $sum->getKcal(), 0.001);
        self::assertEqualsWithDelta(5.6, $sum->getProteinG(), 0.001);
    }
}
