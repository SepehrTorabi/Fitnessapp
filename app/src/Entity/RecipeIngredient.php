<?php

declare(strict_types=1);

namespace App\Entity;

use App\Nutrition\Nutrients;
use App\Repository\RecipeIngredientRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One food and its amount inside a recipe. The amount is normalised to grams
 * when the ingredient is added, so the recipe maths never has to deal with
 * spoons and slices.
 */
#[ORM\Entity(repositoryClass: RecipeIngredientRepository::class)]
#[ORM\Table(name: 'recipe_ingredient')]
class RecipeIngredient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'ingredients', targetEntity: Recipe::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Recipe $recipe;

    #[ORM\ManyToOne(targetEntity: Food::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Food $food;

    #[ORM\Column(type: Types::FLOAT)]
    #[Assert\Positive]
    private float $grams;

    public function __construct(Recipe $recipe, Food $food, float $grams)
    {
        $this->recipe = $recipe;
        $this->food = $food;
        $this->grams = $grams;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRecipe(): Recipe
    {
        return $this->recipe;
    }

    public function setRecipe(Recipe $recipe): void
    {
        $this->recipe = $recipe;
    }

    public function getFood(): Food
    {
        return $this->food;
    }

    public function setFood(Food $food): void
    {
        $this->food = $food;
    }

    public function getGrams(): float
    {
        return $this->grams;
    }

    public function setGrams(float $grams): void
    {
        $this->grams = $grams;
    }

    public function getNutrients(): Nutrients
    {
        return $this->food->nutrientsForGrams($this->grams);
    }
}
