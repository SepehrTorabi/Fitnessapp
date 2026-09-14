<?php

declare(strict_types=1);

namespace App\Api\Presenter;

use App\Entity\Recipe;

final class RecipePresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(Recipe $recipe): array
    {
        return [
            'id' => $recipe->getId(),
            'name' => $recipe->getName(),
            'description' => $recipe->getDescription(),
            'servings' => $recipe->getServings(),
            'public' => $recipe->isPublic(),
            'totalGrams' => round($recipe->getTotalGrams(), 1),
            'gramsPerServing' => round($recipe->getGramsPerServing(), 1),
            'totalNutrients' => $recipe->getTotalNutrients()->rounded(1)->toArray(),
            'perServing' => $recipe->getNutrientsPerServing()->rounded(1)->toArray(),
            'ingredients' => array_map(
                static fn ($ingredient): array => [
                    'id' => $ingredient->getId(),
                    'foodId' => $ingredient->getFood()->getId(),
                    'label' => $ingredient->getFood()->getLabel(),
                    'grams' => $ingredient->getGrams(),
                    'nutrients' => $ingredient->getNutrients()->rounded(1)->toArray(),
                ],
                $recipe->getIngredients()->toArray(),
            ),
            'createdAt' => $recipe->getCreatedAt()->format(\DATE_ATOM),
        ];
    }

    /**
     * @param iterable<Recipe> $recipes
     *
     * @return list<array<string, mixed>>
     */
    public function presentMany(iterable $recipes): array
    {
        $out = [];

        foreach ($recipes as $recipe) {
            $out[] = $this->present($recipe);
        }

        return $out;
    }
}
