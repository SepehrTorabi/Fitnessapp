<?php

declare(strict_types=1);

namespace App\Tests\Unit\Nutrition;

use App\Entity\Food;
use App\Entity\FoodPortion;
use App\Enum\MeasurementUnit;
use App\Nutrition\Nutrients;
use App\Nutrition\PortionResolver;
use App\Nutrition\UnresolvablePortionException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PortionResolver::class)]
final class PortionResolverTest extends TestCase
{
    private PortionResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new PortionResolver();
    }

    public function testGramsPassThroughUnchanged(): void
    {
        $grams = $this->resolver->toGrams($this->bread(), 125.0, MeasurementUnit::Gram);

        self::assertSame(125.0, $grams);
    }

    /**
     * Volume converts through the food's own density, which is why a millilitre
     * of oil and a millilitre of honey do not weigh the same.
     */
    #[DataProvider('volumetricUnits')]
    public function testVolumeConvertsThroughDensity(MeasurementUnit $unit, float $quantity, float $expectedGrams): void
    {
        // Density 0.92 g/ml, roughly olive oil.
        $oil = $this->food('Olive oil', density: 0.92);

        self::assertEqualsWithDelta($expectedGrams, $this->resolver->toGrams($oil, $quantity, $unit), 0.001);
    }

    /**
     * @return iterable<string, array{MeasurementUnit, float, float}>
     */
    public static function volumetricUnits(): iterable
    {
        yield '1 ml' => [MeasurementUnit::Milliliter, 1.0, 0.92];
        yield '1 teaspoon is 5 ml' => [MeasurementUnit::Teaspoon, 1.0, 4.6];
        yield '2 tablespoons are 30 ml' => [MeasurementUnit::Tablespoon, 2.0, 27.6];
        yield '1 cup is 240 ml' => [MeasurementUnit::Cup, 1.0, 220.8];
    }

    public function testWaterlikeDensityLeavesMillilitresEqualToGrams(): void
    {
        $water = $this->food('Water', density: 1.0);

        self::assertEqualsWithDelta(250.0, $this->resolver->toGrams($water, 250.0, MeasurementUnit::Milliliter), 0.001);
    }

    public function testCountableUnitsUseTheFoodsOwnPortion(): void
    {
        $bread = $this->bread(); // one slice is 45 g

        self::assertEqualsWithDelta(90.0, $this->resolver->toGrams($bread, 2.0, MeasurementUnit::Slice), 0.001);
    }

    public function testPortionLookupIgnoresCase(): void
    {
        $bread = $this->bread();

        self::assertEqualsWithDelta(45.0, $this->resolver->toGrams($bread, 1.0, MeasurementUnit::Portion, 'SLICE'), 0.001);
    }

    /**
     * The important one: an amount that cannot honestly be converted must be
     * refused. Guessing a weight would quietly corrupt every total it feeds.
     */
    public function testAnUndefinedPortionIsRejected(): void
    {
        $this->expectException(UnresolvablePortionException::class);
        $this->expectExceptionMessageMatches('/has no portion called "handful"/');

        $this->resolver->toGrams($this->bread(), 1.0, MeasurementUnit::Handful);
    }

    public function testTheErrorSaysWhatToDoInstead(): void
    {
        try {
            $this->resolver->toGrams($this->bread(), 1.0, MeasurementUnit::Piece);
            self::fail('Expected an UnresolvablePortionException.');
        } catch (UnresolvablePortionException $e) {
            self::assertStringContainsString('enter the amount in grams', $e->getMessage());
        }
    }

    #[DataProvider('nonPositiveQuantities')]
    public function testANonPositiveAmountIsRejected(float $quantity): void
    {
        $this->expectException(UnresolvablePortionException::class);

        $this->resolver->toGrams($this->bread(), $quantity, MeasurementUnit::Gram);
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function nonPositiveQuantities(): iterable
    {
        yield 'zero' => [0.0];
        yield 'negative' => [-50.0];
    }

    public function testAvailableUnitsIncludeTheFoodsNamedPortions(): void
    {
        $units = $this->resolver->availableUnitsFor($this->bread());

        $labels = array_column($units, 'label');

        self::assertContains('g', $labels);
        self::assertContains('tbsp', $labels);
        self::assertContains('slice', $labels);
    }

    public function testAvailableUnitsOmitPortionsTheFoodDoesNotDefine(): void
    {
        $units = $this->resolver->availableUnitsFor($this->food('Milk', density: 1.03));

        self::assertNotContains('slice', array_column($units, 'label'));
    }

    private function bread(): Food
    {
        $bread = $this->food('Wholegrain bread', density: 0.45);
        $bread->addPortion(new FoodPortion($bread, 'slice', 45.0));

        return $bread;
    }

    private function food(string $name, float $density): Food
    {
        $food = new Food($name, new Nutrients(250.0, 10.0, 30.0, 8.0));
        $food->setDensityGPerMl($density);

        return $food;
    }
}
