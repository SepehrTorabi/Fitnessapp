<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import;

use App\Import\FoodDiaryData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Guards the transcription of the food-diary PDF.
 *
 * The data was typed in by hand from a printed table, which is exactly the kind
 * of thing that goes wrong silently. The PDF states its own total for every
 * person on every day, so those totals are the check: if a figure is mistyped,
 * the sum stops matching and this fails.
 */
#[CoversClass(FoodDiaryData::class)]
final class FoodDiaryDataTest extends TestCase
{
    #[DataProvider('personDays')]
    public function testTheItemsAddUpToTheTotalThePdfPrints(
        string $date,
        string $person,
        float $kcal,
        float $fat,
        float $protein,
    ): void {
        $day = FoodDiaryData::DAYS[$date][$person];

        $sums = [0.0, 0.0, 0.0];

        foreach ($day['items'] as $item) {
            $sums[0] += (float) $item[2];
            $sums[1] += (float) $item[3];
            $sums[2] += (float) $item[4];
        }

        self::assertEqualsWithDelta($kcal, $sums[0], 0.051, 'Energy does not match the PDF total.');
        self::assertEqualsWithDelta($fat, $sums[1], 0.051, 'Fat does not match the PDF total.');
        self::assertEqualsWithDelta($protein, $sums[2], 0.051, 'Protein does not match the PDF total.');
    }

    /**
     * @return iterable<string, array{string, string, float, float, float}>
     */
    public static function personDays(): iterable
    {
        foreach (FoodDiaryData::DAYS as $date => $people) {
            foreach (['cosima', 'sepehr'] as $person) {
                $sum = $people[$person]['sum'];

                yield \sprintf('%s %s', $date, $person) => [
                    $date, $person, (float) $sum[0], (float) $sum[1], (float) $sum[2],
                ];
            }
        }
    }

    /**
     * The food names used by the days and the names with a unit weight defined
     * have to be exactly the same set.
     *
     * Both directions matter. A name in the days with no weight cannot be turned
     * into grams; a weight for a name nothing uses means one of the two spellings
     * has a typo in it - which is the same bug seen from the other end.
     */
    public function testTheFoodNamesAndTheUnitWeightsAreTheSameSet(): void
    {
        $used = [];

        foreach (FoodDiaryData::DAYS as $people) {
            foreach (['cosima', 'sepehr'] as $person) {
                foreach ($people[$person]['items'] as $item) {
                    $used[(string) $item[0]] = true;
                }
            }
        }

        $usedNames = array_keys($used);
        $definedNames = array_keys(FoodDiaryData::UNIT_WEIGHTS);

        sort($usedNames);
        sort($definedNames);

        self::assertSame($definedNames, $usedNames);
    }

    /**
     * The same food should imply the same energy density wherever it appears.
     *
     * This is what catches a quantity typed wrongly: "5 Scheiben" entered as 6
     * still adds up on its own line, but the food suddenly has a different
     * calorie density from the day before. A few percent of slack is left for
     * the PDF's own rounding.
     */
    public function testAFoodHasAConsistentEnergyDensityAcrossDays(): void
    {
        /** @var array<string, list<array{float, string}>> $densities */
        $densities = [];

        foreach (FoodDiaryData::DAYS as $date => $people) {
            foreach (['cosima', 'sepehr'] as $person) {
                foreach ($people[$person]['items'] as $item) {
                    [$unit, $unitGrams] = FoodDiaryData::UNIT_WEIGHTS[$item[0]];
                    $grams = ('g' === $unit || 'g' === ($item[5] ?? null))
                        ? (float) $item[1]
                        : (float) $item[1] * $unitGrams;

                    if ($grams <= 0.0) {
                        continue;
                    }

                    $densities[$item[0]][] = [(float) $item[2] / $grams * 100.0, \sprintf('%s %s', $date, $person)];
                }
            }
        }

        $inconsistent = [];

        foreach ($densities as $name => $samples) {
            $values = array_column($samples, 0);
            $min = min($values);
            $max = max($values);

            if ($min > 0.0 && ($max - $min) / $min > 0.05) {
                $inconsistent[] = \sprintf('%s: %.1f to %.1f kcal/100 g', $name, $min, $max);
            }
        }

        self::assertSame([], $inconsistent, 'A food is listed with different energy densities on different days.');
    }
}
