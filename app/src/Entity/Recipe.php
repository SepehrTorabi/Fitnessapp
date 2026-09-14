<?php

declare(strict_types=1);

namespace App\Entity;

use App\Nutrition\Nutrients;
use App\Repository\RecipeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A dish assembled from catalogue foods.
 *
 * A recipe carries no nutrition values of its own - they are always derived from
 * its ingredients, so correcting one ingredient corrects every recipe using it.
 */
#[ORM\Entity(repositoryClass: RecipeRepository::class)]
#[ORM\Table(name: 'recipe')]
#[ORM\Index(name: 'idx_recipe_name', columns: ['name'])]
class Recipe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * How many people the whole recipe feeds. One serving is what gets logged.
     */
    #[ORM\Column]
    #[Assert\Positive]
    private int $servings = 1;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column]
    private bool $public = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, RecipeIngredient> */
    #[ORM\OneToMany(mappedBy: 'recipe', targetEntity: RecipeIngredient::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $ingredients;

    public function __construct(string $name, int $servings = 1, ?User $createdBy = null)
    {
        $this->name = $name;
        $this->servings = $servings;
        $this->createdBy = $createdBy;
        $this->createdAt = new \DateTimeImmutable();
        $this->ingredients = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getServings(): int
    {
        return $this->servings;
    }

    public function setServings(int $servings): void
    {
        $this->servings = max(1, $servings);
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): void
    {
        $this->public = $public;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, RecipeIngredient>
     */
    public function getIngredients(): Collection
    {
        return $this->ingredients;
    }

    public function addIngredient(RecipeIngredient $ingredient): void
    {
        if (!$this->ingredients->contains($ingredient)) {
            $this->ingredients->add($ingredient);
            $ingredient->setRecipe($this);
        }
    }

    public function removeIngredient(RecipeIngredient $ingredient): void
    {
        $this->ingredients->removeElement($ingredient);
    }

    public function getTotalGrams(): float
    {
        $total = 0.0;

        foreach ($this->ingredients as $ingredient) {
            $total += $ingredient->getGrams();
        }

        return $total;
    }

    /**
     * Nutrition of the whole recipe, summed over its ingredients.
     */
    public function getTotalNutrients(): Nutrients
    {
        $total = Nutrients::zero();

        foreach ($this->ingredients as $ingredient) {
            $total = $total->plus($ingredient->getNutrients());
        }

        return $total;
    }

    /**
     * Nutrition of a single serving.
     */
    public function getNutrientsPerServing(): Nutrients
    {
        return $this->getTotalNutrients()->scaled(1.0 / max(1, $this->servings));
    }

    public function getGramsPerServing(): float
    {
        return $this->getTotalGrams() / max(1, $this->servings);
    }
}
