<?php

declare(strict_types=1);

namespace App\Nutrition;

use App\Entity\User;
use App\Repository\ActivityEntryRepository;
use App\Repository\DiaryEntryRepository;

/**
 * Reads the diary back as day totals and as the multi-day series behind the
 * dashboard chart.
 */
final class DiaryService
{
    public function __construct(
        private readonly DiaryEntryRepository $diaryEntries,
        private readonly ActivityEntryRepository $activityEntries,
        private readonly DailyTargetResolver $targetResolver,
    ) {
    }

    public function summarizeDay(User $user, \DateTimeImmutable $date): DaySummary
    {
        $consumed = Nutrients::zero();

        foreach ($this->diaryEntries->findForUserOn($user, $date) as $entry) {
            $consumed = $consumed->plus($entry->getNutrients());
        }

        $burned = 0.0;

        foreach ($this->activityEntries->findForUserOn($user, $date) as $activity) {
            $burned += $activity->getCaloriesBurned();
        }

        return new DaySummary($date, $consumed, $burned, $this->targetResolver->resolve($user, $date));
    }

    /**
     * The last N days ending on $endDate, oldest first, with no gaps.
     *
     * Days with nothing logged are included as zeroes rather than skipped, so
     * the chart shows seven bars for seven days instead of silently compressing
     * a week into the three days that happen to have entries.
     *
     * @return list<DaySummary>
     */
    public function summarizeRange(User $user, \DateTimeImmutable $endDate, int $days = 7): array
    {
        $days = max(1, min(365, $days));
        $startDate = $endDate->modify(\sprintf('-%d days', $days - 1));

        // Two grouped queries for the whole range, rather than 2N queries in a
        // loop: at 7 days the difference is invisible, at 365 it is not.
        $intake = $this->diaryEntries->sumPerDay($user, $startDate, $endDate);
        $burned = $this->activityEntries->sumPerDay($user, $startDate, $endDate);

        $summaries = [];

        for ($offset = 0; $offset < $days; ++$offset) {
            $date = $startDate->modify(\sprintf('+%d days', $offset));
            $key = $date->format('Y-m-d');

            $totals = $intake[$key] ?? null;

            $consumed = null === $totals
                ? Nutrients::zero()
                : new Nutrients($totals['kcal'], $totals['proteinG'], $totals['carbsG'], $totals['fatG']);

            $summaries[] = new DaySummary(
                $date,
                $consumed,
                $burned[$key] ?? 0.0,
                $this->targetResolver->resolve($user, $date),
            );
        }

        return $summaries;
    }
}
