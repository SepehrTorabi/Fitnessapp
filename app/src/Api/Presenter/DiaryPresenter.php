<?php

declare(strict_types=1);

namespace App\Api\Presenter;

use App\Entity\ActivityEntry;
use App\Entity\DiaryEntry;

final class DiaryPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function presentEntry(DiaryEntry $entry): array
    {
        return [
            'id' => $entry->getId(),
            'loggedOn' => $entry->getLoggedOn()->format('Y-m-d'),
            'mealType' => $entry->getMealType()->value,
            'label' => $entry->getLabel(),
            'foodId' => $entry->getFood()?->getId(),
            'recipeId' => $entry->getRecipe()?->getId(),
            'quantity' => $entry->getQuantity(),
            'unit' => $entry->getUnit()->value,
            'portionLabel' => $entry->getPortionLabel(),
            'grams' => round($entry->getGrams(), 1),
            'nutrients' => $entry->getNutrients()->rounded(1)->toArray(),
            'createdAt' => $entry->getCreatedAt()->format(\DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentActivity(ActivityEntry $activity): array
    {
        return [
            'id' => $activity->getId(),
            'performedOn' => $activity->getPerformedOn()->format('Y-m-d'),
            'description' => $activity->getDescription(),
            'durationMinutes' => $activity->getDurationMinutes(),
            'caloriesBurned' => $activity->getCaloriesBurned(),
            'createdAt' => $activity->getCreatedAt()->format(\DATE_ATOM),
        ];
    }
}
