<?php

declare(strict_types=1);

namespace App\Nutrition;

/**
 * Everything the dashboard needs to say about one day.
 */
final readonly class DaySummary
{
    public function __construct(
        public \DateTimeImmutable $date,
        public Nutrients $consumed,
        public float $caloriesBurned,
        public ?CalorieTarget $target,
    ) {
    }

    /**
     * The day's calorie budget: the target plus whatever was burned through
     * activity. Training earns extra room to eat.
     */
    public function getBudgetKcal(): ?float
    {
        return null === $this->target ? null : $this->target->targetKcal + $this->caloriesBurned;
    }

    /**
     * Calories left for the day. Negative once the budget is exceeded.
     */
    public function getRemainingKcal(): ?float
    {
        $budget = $this->getBudgetKcal();

        return null === $budget ? null : $budget - $this->consumed->getKcal();
    }

    /**
     * Whether the day is within budget - what decides the green or red bar on
     * the dashboard. Null while there is no target to compare against.
     */
    public function isWithinBudget(): ?bool
    {
        $remaining = $this->getRemainingKcal();

        return null === $remaining ? null : $remaining >= 0.0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $budget = $this->getBudgetKcal();
        $remaining = $this->getRemainingKcal();

        return [
            'date' => $this->date->format('Y-m-d'),
            'consumed' => $this->consumed->rounded(1)->toArray(),
            'caloriesBurned' => round($this->caloriesBurned),
            'target' => $this->target?->toArray(),
            'budgetKcal' => null === $budget ? null : round($budget),
            'remainingKcal' => null === $remaining ? null : round($remaining),
            'withinBudget' => $this->isWithinBudget(),
        ];
    }
}
