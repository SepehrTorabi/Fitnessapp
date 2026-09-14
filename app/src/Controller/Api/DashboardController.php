<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\Presenter\DiaryPresenter;
use App\Entity\DiaryEntry;
use App\Entity\User;
use App\Nutrition\DaySummary;
use App\Nutrition\DiaryService;
use App\Repository\DiaryEntryRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * The dashboard: today's numbers, the recent activity list and the week's chart.
 */
final class DashboardController extends ApiController
{
    public function __construct(
        private readonly DiaryService $diaryService,
        private readonly DiaryEntryRepository $diaryEntries,
        private readonly DiaryPresenter $presenter,
    ) {
    }

    public function summary(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $date = $this->parseDate($request->query->getString('date') ?: null);
        $days = max(2, min(90, $request->query->getInt('days', 7)));

        $history = $this->diaryService->summarizeRange($user, $date, $days);

        return $this->json([
            'today' => $this->diaryService->summarizeDay($user, $date)->toArray(),
            'history' => array_map(
                static fn (DaySummary $summary): array => $summary->toArray(),
                $history,
            ),
            'averages' => $this->averagesOverLoggedDays($history),
            'recentEntries' => array_map(
                fn (DiaryEntry $entry): array => $this->presenter->presentEntry($entry),
                $this->diaryEntries->findForUserOn($user, $date),
            ),
        ]);
    }

    /**
     * Averages over the days that actually have entries.
     *
     * Days with nothing logged are excluded rather than counted as zero: a user
     * who logged three days out of seven ate an average of what they logged, not
     * three sevenths of it.
     *
     * @param list<DaySummary> $history
     *
     * @return array<string, float|int|null>
     */
    private function averagesOverLoggedDays(array $history): array
    {
        $logged = array_values(array_filter(
            $history,
            static fn (DaySummary $s): bool => $s->consumed->getKcal() > 0.0,
        ));

        $count = \count($logged);

        if (0 === $count) {
            return ['daysLogged' => 0, 'kcal' => null, 'proteinG' => null, 'carbsG' => null, 'fatG' => null];
        }

        $kcal = $protein = $carbs = $fat = 0.0;

        foreach ($logged as $summary) {
            $kcal += $summary->consumed->getKcal();
            $protein += $summary->consumed->getProteinG();
            $carbs += $summary->consumed->getCarbsG();
            $fat += $summary->consumed->getFatG();
        }

        return [
            'daysLogged' => $count,
            'kcal' => round($kcal / $count),
            'proteinG' => round($protein / $count, 1),
            'carbsG' => round($carbs / $count, 1),
            'fatG' => round($fat / $count, 1),
        ];
    }
}
