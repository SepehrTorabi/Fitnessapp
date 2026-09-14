<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\ActivityEntry;
use App\Entity\DiaryEntry;
use App\Entity\Food;
use App\Entity\FoodPortion;
use App\Entity\User;
use App\Enum\FoodSource;
use App\Enum\MealType;
use App\Enum\MeasurementUnit;
use App\Nutrition\Nutrients;
use App\Repository\FoodRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Loads the food diary PDF into the catalogue and the two users' diaries.
 *
 * Safe to run twice: foods are matched by name, and each user's entries for the
 * days being imported are cleared first. Re-running replaces, never duplicates.
 */
final class FoodDiaryImporter
{
    /** Kilocalories per gram (Atwater), for deriving the missing carbohydrate. */
    private const float KCAL_PROTEIN = 4.0;
    private const float KCAL_CARBS = 4.0;
    private const float KCAL_FAT = 9.0;

    /**
     * How far a recomputed daily total may sit from the PDF's own "Summe" line.
     *
     * Not zero, because a Food carries one set of per-100 g values while the PDF
     * occasionally states the same food slightly inconsistently across days - a
     * slice of Billie Green salami works out to 281.25 kcal/100 g, the 100 g
     * line says 282. The per-100 g value is fitted across all occurrences, which
     * leaves fractions of a kilocalorie on individual lines.
     */
    private const float SUM_TOLERANCE_KCAL = 3.0;
    private const float SUM_TOLERANCE_GRAMS = 1.5;

    /**
     * Words that place a food at breakfast or as a snack. Anything else is
     * counted as dinner.
     *
     * The PDF has no meal column at all, so this is inference, not data - it
     * exists only so the diary reads naturally instead of filing a bread roll
     * under the same heading as a pizza. Getting one wrong costs nothing but a
     * label.
     */
    private const array BREAKFAST_WORDS = [
        'brötchen', 'barbari', 'rührei', 'butterkäse', 'streichcreme', 'marmelade',
        'gouda', 'amsterdam', 'salami', 'walnussbrot', 'laugenecke', 'nabat',
    ];

    private const array SNACK_WORDS = [
        'birne', 'banane', 'trauben', 'dattel', 'walnuss', 'proteinriegel', 'shake',
        'eiweiß', 'cheetos', 'brownie', 'zupfkuchen', 'käsekuchen', 'apfelschorle',
        'truefruits', 'quarkspeise', 'protein-pulver', 'mandelmilch', 'gurke',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FoodRepository $foods,
    ) {
    }

    /**
     * Check the transcription without touching the database.
     *
     * @return list<string> problems found; empty means the data is coherent
     */
    public function validate(): array
    {
        $problems = [];

        foreach (FoodDiaryData::DAYS as $date => $people) {
            foreach (['cosima', 'sepehr'] as $person) {
                $day = $people[$person];

                [$kcal, $fat, $protein] = [0.0, 0.0, 0.0];

                foreach ($day['items'] as $item) {
                    if (!isset(FoodDiaryData::UNIT_WEIGHTS[$item[0]])) {
                        $problems[] = \sprintf('%s %s: no unit weight defined for "%s".', $date, $person, $item[0]);

                        continue;
                    }

                    $kcal += (float) $item[2];
                    $fat += (float) $item[3];
                    $protein += (float) $item[4];
                }

                // The PDF prints its own total for the day; if our line-by-line
                // sum disagrees, a figure was mistyped.
                foreach ([[0, $kcal, 'kcal'], [1, $fat, 'fat'], [2, $protein, 'protein']] as [$index, $actual, $label]) {
                    $expected = (float) $day['sum'][$index];

                    if (abs($actual - $expected) > 0.051) {
                        $problems[] = \sprintf(
                            '%s %s: %s adds up to %.2f but the PDF says %.2f.',
                            $date, $person, $label, $actual, $expected,
                        );
                    }
                }
            }
        }

        return $problems;
    }

    /**
     * @param array<string, User> $users keyed by 'cosima' and 'sepehr'
     *
     * @return array{foods: int, entries: int, activities: int, replacedDays: int, deviations: list<string>}
     */
    public function import(array $users): array
    {
        $catalogue = $this->buildCatalogue();
        $dates = array_map(static fn (string $d): \DateTimeImmutable => new \DateTimeImmutable($d), array_keys(FoodDiaryData::DAYS));

        $replaced = 0;

        foreach ($users as $user) {
            $replaced += $this->clearDays($user, $dates);
        }

        $entries = 0;
        $activities = 0;
        $deviations = [];

        foreach (FoodDiaryData::DAYS as $date => $people) {
            $day = new \DateTimeImmutable($date);

            foreach ($users as $person => $user) {
                $record = $people[$person];
                $totals = Nutrients::zero();

                foreach ($record['items'] as $item) {
                    $entry = $this->createEntry($user, $day, $item, $catalogue);
                    $this->entityManager->persist($entry);
                    $totals = $totals->plus($entry->getNutrients());
                    ++$entries;
                }

                // What actually landed in the database, against what the PDF says.
                $deviation = $this->compareWithPdf($date, $person, $totals, $record['sum']);

                if (null !== $deviation) {
                    $deviations[] = $deviation;
                }

                if (null !== $record['burned']) {
                    [$label, $kcal] = $record['burned'];

                    $this->entityManager->persist(
                        new ActivityEntry($user, $day, $label, (float) $kcal),
                    );
                    ++$activities;
                }
            }
        }

        $this->entityManager->flush();

        return [
            'foods' => \count($catalogue),
            'entries' => $entries,
            'activities' => $activities,
            'replacedDays' => $replaced,
            'deviations' => $deviations,
        ];
    }

    /**
     * Create or update one Food per distinct name in the diary.
     *
     * The per-100 g values are fitted across every occurrence of that food -
     * total energy over total weight - rather than taken from whichever day
     * happened to come first, so no single line dominates.
     *
     * @return array<string, Food>
     */
    private function buildCatalogue(): array
    {
        /** @var array<string, array{kcal: float, fat: float, protein: float, grams: float}> $totals */
        $totals = [];

        foreach (FoodDiaryData::DAYS as $people) {
            foreach (['cosima', 'sepehr'] as $person) {
                foreach ($people[$person]['items'] as $item) {
                    $name = $item[0];
                    $grams = $this->gramsFor($item);

                    $totals[$name] ??= ['kcal' => 0.0, 'fat' => 0.0, 'protein' => 0.0, 'grams' => 0.0];
                    $totals[$name]['kcal'] += (float) $item[2];
                    $totals[$name]['fat'] += (float) $item[3];
                    $totals[$name]['protein'] += (float) $item[4];
                    $totals[$name]['grams'] += $grams;
                }
            }
        }

        $catalogue = [];

        foreach ($totals as $name => $sum) {
            $factor = 100.0 / $sum['grams'];

            $per100 = $this->withDerivedCarbs(
                $sum['kcal'] * $factor,
                $sum['fat'] * $factor,
                $sum['protein'] * $factor,
            );

            $food = $this->foods->findOneBy(['name' => $name]) ?? new Food($name, $per100, FoodSource::Seed);
            $food->setPer100($per100);
            $food->setSource(FoodSource::Seed);

            [$unit, $unitGrams] = FoodDiaryData::UNIT_WEIGHTS[$name];

            // Counted foods get a named portion so "2 Scheiben" can be logged as
            // such next time instead of having to be converted to grams by hand.
            if ('g' !== $unit && null === $food->findPortion($unit)) {
                $food->addPortion(new FoodPortion($food, $unit, $unitGrams));
            }

            $this->foods->save($food, false);
            $catalogue[$name] = $food;
        }

        $this->entityManager->flush();

        return $catalogue;
    }

    /**
     * @param array<int, mixed>   $item
     * @param array<string, Food> $catalogue
     */
    private function createEntry(User $user, \DateTimeImmutable $day, array $item, array $catalogue): DiaryEntry
    {
        $name = (string) $item[0];
        $food = $catalogue[$name];
        $grams = $this->gramsFor($item);

        [$unit] = FoodDiaryData::UNIT_WEIGHTS[$name];
        $isGrams = 'g' === $unit || 'g' === ($item[5] ?? null);

        return DiaryEntry::forFood(
            $user,
            $food,
            $day,
            $this->mealFor($name),
            $isGrams ? $grams : (float) $item[1],
            $isGrams ? MeasurementUnit::Gram : MeasurementUnit::Portion,
            $grams,
            $isGrams ? null : $unit,
        );
    }

    /**
     * How many grams one diary line represents.
     *
     * @param array<int, mixed> $item
     */
    private function gramsFor(array $item): float
    {
        [$unit, $unitGrams] = FoodDiaryData::UNIT_WEIGHTS[$item[0]];

        // A sixth element 'g' overrides the food's usual unit, for the days that
        // give a normally-counted food by weight instead.
        if ('g' === $unit || 'g' === ($item[5] ?? null)) {
            return (float) $item[1];
        }

        return (float) $item[1] * $unitGrams;
    }

    /**
     * Carbohydrate is not in the PDF. Everything else is, so the remainder of
     * the energy after protein and fat is the only value consistent with it.
     * Clamped at zero: rounding in the source occasionally makes fat and protein
     * account for slightly more than the stated calories.
     */
    private function withDerivedCarbs(float $kcal, float $fat, float $protein): Nutrients
    {
        $remaining = $kcal - ($protein * self::KCAL_PROTEIN) - ($fat * self::KCAL_FAT);

        return new Nutrients($kcal, $protein, max(0.0, $remaining / self::KCAL_CARBS), $fat);
    }

    private function mealFor(string $name): MealType
    {
        $haystack = mb_strtolower($name);

        foreach (self::SNACK_WORDS as $word) {
            if (str_contains($haystack, $word)) {
                return MealType::Snack;
            }
        }

        foreach (self::BREAKFAST_WORDS as $word) {
            if (str_contains($haystack, $word)) {
                return MealType::Breakfast;
            }
        }

        return MealType::Dinner;
    }

    /**
     * @param list<float|int> $expected
     */
    private function compareWithPdf(string $date, string $person, Nutrients $actual, array $expected): ?string
    {
        $checks = [
            ['kcal', $actual->getKcal(), (float) $expected[0], self::SUM_TOLERANCE_KCAL],
            ['fat', $actual->getFatG(), (float) $expected[1], self::SUM_TOLERANCE_GRAMS],
            ['protein', $actual->getProteinG(), (float) $expected[2], self::SUM_TOLERANCE_GRAMS],
        ];

        foreach ($checks as [$label, $got, $want, $tolerance]) {
            if (abs($got - $want) > $tolerance) {
                return \sprintf(
                    '%s %s: %s is %.1f, PDF says %.1f (off by %.1f).',
                    $date, $person, $label, $got, $want, $got - $want,
                );
            }
        }

        return null;
    }

    /**
     * Remove whatever this user already has on the days about to be imported, so
     * a second run replaces rather than doubles.
     *
     * @param list<\DateTimeImmutable> $dates
     */
    private function clearDays(User $user, array $dates): int
    {
        // Bound as Y-m-d strings: an IN() list of DateTimeImmutable objects has
        // no single parameter type Doctrine can infer, and it fails trying to
        // cast them.
        $days = array_map(static fn (\DateTimeImmutable $d): string => $d->format('Y-m-d'), $dates);

        $deleted = 0;

        foreach ([DiaryEntry::class => 'loggedOn', ActivityEntry::class => 'performedOn'] as $class => $field) {
            $deleted += (int) $this->entityManager->createQueryBuilder()
                ->delete($class, 'e')
                ->where('e.user = :user')
                ->andWhere(\sprintf('e.%s IN (:dates)', $field))
                ->setParameter('user', $user)
                ->setParameter('dates', $days, ArrayParameterType::STRING)
                ->getQuery()
                ->execute();
        }

        return $deleted;
    }
}
