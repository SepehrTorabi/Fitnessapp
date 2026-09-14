<?php

declare(strict_types=1);

namespace App\Export;

use App\Entity\ActivityEntry;
use App\Entity\DiaryEntry;
use App\Entity\User;
use App\Enum\MeasurementUnit;
use App\Repository\ActivityEntryRepository;
use App\Repository\DiaryEntryRepository;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Twig\Environment;

/**
 * Renders a user's diary as a PDF in the layout of the printed Essenstagebuch.
 *
 * Only days that actually have something on them are included - a gap in the
 * diary is a day the user did not log, not a day they ate nothing, and printing
 * it as a row of zeroes would say the wrong thing.
 */
final class DiaryPdfExporter
{
    /**
     * Rows at or above this many kilocalories are emphasised, which is what the
     * printed original does - it makes the two or three items that carried the
     * day findable without reading every line.
     */
    public const float HIGHLIGHT_KCAL = 400.0;

    /**
     * Counting words that add nothing next to the food's own name, so they are
     * dropped from the printed line.
     */
    private const array IMPLIED_UNITS = ['stück', 'portion', 'piece', 'serving', 'ei'];

    /** @var array<string, string> */
    private const array PLURALS = [
        'scheibe' => 'Scheiben',
        'packung' => 'Packungen',
        'flasche' => 'Flaschen',
        'portion' => 'Portionen',
        'ei' => 'Eier',
        'slice' => 'slices',
        'piece' => 'pieces',
        'serving' => 'servings',
    ];

    public function __construct(
        private readonly DiaryEntryRepository $diaryEntries,
        private readonly ActivityEntryRepository $activityEntries,
        private readonly Environment $twig,
        private readonly string $tempDir,
    ) {
    }

    /**
     * @throws MpdfException
     */
    public function export(User $user, string $locale): string
    {
        $html = $this->twig->render('pdf/food_diary.html.twig', [
            'user' => $user,
            'report' => $this->buildReport($user, $locale),
            'highlightKcal' => self::HIGHLIGHT_KCAL,
            'locale' => $locale,
        ]);

        // mPDF writes font subsets and temporary images while it renders, so it
        // needs a writable directory of its own; var/ is the one place that is
        // guaranteed writable in every environment.
        if (!is_dir($this->tempDir) && !mkdir($this->tempDir, 0o775, true) && !is_dir($this->tempDir)) {
            throw new \RuntimeException(\sprintf('Could not create the PDF temp directory "%s".', $this->tempDir));
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $this->tempDir,
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 14,
            'margin_bottom' => 16,
        ]);

        $mpdf->SetTitle('Essenstagebuch - '.$user->getDisplayName());
        $mpdf->SetAuthor($user->getDisplayName());
        $mpdf->SetCreator('Fitnessapp');

        $mpdf->WriteHTML($html);

        return (string) $mpdf->Output('', 'S');
    }

    /**
     * A sensible download name: the person, and the period covered.
     */
    public function filenameFor(User $user): string
    {
        $report = $this->buildReport($user);

        $slug = preg_replace('/[^A-Za-z0-9]+/', '_', $user->getDisplayName()) ?? 'export';
        $slug = trim($slug, '_');

        if ([] === $report['days']) {
            return \sprintf('Essenstagebuch_%s.pdf', $slug);
        }

        return \sprintf(
            'Essenstagebuch_%s_%s_bis_%s.pdf',
            $slug,
            $report['from']->format('Y-m-d'),
            $report['to']->format('Y-m-d'),
        );
    }

    /**
     * Group everything the user has logged into days, and work out the averages
     * the cover page shows.
     *
     * @return array{
     *     days: list<array<string, mixed>>,
     *     from: \DateTimeImmutable|null,
     *     to: \DateTimeImmutable|null,
     *     dayCount: int,
     *     averages: array<string, float|null>
     * }
     */
    public function buildReport(User $user, string $locale = 'de'): array
    {
        /** @var array<string, array{entries: list<DiaryEntry>, activities: list<ActivityEntry>}> $byDay */
        $byDay = [];

        foreach ($this->diaryEntries->findAllForUser($user) as $entry) {
            $key = $entry->getLoggedOn()->format('Y-m-d');
            $byDay[$key] ??= ['entries' => [], 'activities' => []];
            $byDay[$key]['entries'][] = $entry;
        }

        foreach ($this->activityEntries->findAllForUser($user) as $activity) {
            $key = $activity->getPerformedOn()->format('Y-m-d');
            $byDay[$key] ??= ['entries' => [], 'activities' => []];
            $byDay[$key]['activities'][] = $activity;
        }

        // Newest first, like the printed original.
        krsort($byDay);

        $days = [];
        $totalKcal = $totalFat = $totalProtein = $totalBurned = 0.0;
        $daysWithFood = 0;
        $daysWithActivity = 0;

        foreach ($byDay as $key => $day) {
            $kcal = $fat = $protein = 0.0;

            foreach ($day['entries'] as $entry) {
                $nutrients = $entry->getNutrients();
                $kcal += $nutrients->getKcal();
                $fat += $nutrients->getFatG();
                $protein += $nutrients->getProteinG();
            }

            $burned = 0.0;

            foreach ($day['activities'] as $activity) {
                $burned += $activity->getCaloriesBurned();
            }

            if ([] !== $day['entries']) {
                ++$daysWithFood;
                $totalKcal += $kcal;
                $totalFat += $fat;
                $totalProtein += $protein;
            }

            if ([] !== $day['activities']) {
                ++$daysWithActivity;
                $totalBurned += $burned;
            }

            $lines = [];

            foreach ($day['entries'] as $entry) {
                $nutrients = $entry->getNutrients();

                $lines[] = [
                    'label' => $this->describe($entry, $locale),
                    'kcal' => $nutrients->getKcal(),
                    'fat' => $nutrients->getFatG(),
                    'protein' => $nutrients->getProteinG(),
                    'major' => $nutrients->getKcal() >= self::HIGHLIGHT_KCAL,
                ];
            }

            $days[] = [
                'date' => new \DateTimeImmutable($key),
                'lines' => $lines,
                'entries' => $day['entries'],
                'activities' => $day['activities'],
                'kcal' => $kcal,
                'fat' => $fat,
                'protein' => $protein,
                'burned' => $burned,
                'net' => $kcal - $burned,
                'badge' => $this->badgeFor($day['activities']),
            ];
        }

        $dates = array_map(static fn (array $d): \DateTimeImmutable => $d['date'], $days);

        return [
            'days' => $days,
            // $days is newest first, so the range runs from the last to the first.
            'from' => [] === $dates ? null : end($dates),
            'to' => [] === $dates ? null : $dates[0],
            'dayCount' => \count($days),
            'averages' => [
                'kcal' => $daysWithFood > 0 ? $totalKcal / $daysWithFood : null,
                'fat' => $daysWithFood > 0 ? $totalFat / $daysWithFood : null,
                'protein' => $daysWithFood > 0 ? $totalProtein / $daysWithFood : null,
                // Averaged over the days that had any activity, not over every
                // day - otherwise a rest day drags the figure down as if the
                // training had been lighter.
                'burned' => $daysWithActivity > 0 ? $totalBurned / $daysWithActivity : null,
                'net' => $daysWithFood > 0 ? ($totalKcal - $totalBurned) / $daysWithFood : null,
            ],
        ];
    }

    /**
     * How one logged item reads on the page: "250 g Eiernudeln", "6 Scheiben
     * Vegane Salami", "1 Pizza".
     *
     * The unit is left out when the food's own name already carries it - "1
     * Stück Körnerbrötchen" and "2,5 Ei Eier als Rührei" say the same word
     * twice. That is why the generic counting words are suppressed while the
     * informative ones are kept.
     */
    private function describe(DiaryEntry $entry, string $locale): string
    {
        $quantity = $this->formatQuantity($entry->getQuantity(), $locale);

        if (MeasurementUnit::Gram === $entry->getUnit()) {
            return \sprintf('%s g %s', $quantity, $entry->getLabel());
        }

        $unit = $entry->getPortionLabel() ?? $entry->getUnit()->value;

        if (\in_array(mb_strtolower($unit), self::IMPLIED_UNITS, true)) {
            return \sprintf('%s %s', $quantity, $entry->getLabel());
        }

        return \sprintf('%s %s %s', $quantity, $this->pluralise($unit, $entry->getQuantity()), $entry->getLabel());
    }

    /**
     * Whole numbers lose their decimal: "1 Brötchen", not "1,0 Brötchen".
     */
    private function formatQuantity(float $quantity, string $locale): string
    {
        $decimals = abs($quantity - round($quantity)) < 0.05 ? 0 : 1;

        return number_format($quantity, $decimals, 'de' === $locale ? ',' : '.', '');
    }

    /**
     * German plurals for the handful of unit words the app uses. Anything not
     * listed is left alone, which is right for the invariant ones (EL, TL,
     * Stück) and harmless for a label a user invented.
     */
    private function pluralise(string $unit, float $quantity): string
    {
        if (abs($quantity - 1.0) < 0.001) {
            return $unit;
        }

        return self::PLURALS[mb_strtolower($unit)] ?? $unit;
    }

    /**
     * Which badge a day carries, following the printed original: a deliberate
     * workout is a "Trainingstag", anything else that burned calories is just
     * "Aktiv", and a day with neither gets nothing.
     *
     * @param list<ActivityEntry> $activities
     */
    private function badgeFor(array $activities): ?string
    {
        if ([] === $activities) {
            return null;
        }

        foreach ($activities as $activity) {
            if (str_contains(mb_strtolower($activity->getDescription()), 'training')) {
                return 'training';
            }
        }

        return 'active';
    }
}
