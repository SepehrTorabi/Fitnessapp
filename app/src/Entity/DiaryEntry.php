<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\MealType;
use App\Enum\MeasurementUnit;
use App\Nutrition\Nutrients;
use App\Repository\DiaryEntryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One thing eaten, on one day, by one user.
 *
 * The nutrition values are a *snapshot*, copied in at the moment of logging
 * rather than recomputed from the food on every read. That is deliberate: if
 * somebody later corrects the calories of a food, or the manufacturer changes
 * the recipe, an already-logged day must not silently change underneath the
 * user. The same reasoning applies to the label - it survives the food being
 * deleted.
 *
 * The entry points at either a food or a recipe, never both and never neither.
 * Use the two named constructors rather than `new`.
 */
#[ORM\Entity(repositoryClass: DiaryEntryRepository::class)]
#[ORM\Table(name: 'diary_entry')]
#[ORM\Index(name: 'idx_diary_user_day', columns: ['user_id', 'logged_on'])]
class DiaryEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $loggedOn;

    #[ORM\Column(type: Types::STRING, length: 16, enumType: MealType::class)]
    private MealType $mealType;

    #[ORM\ManyToOne(targetEntity: Food::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Food $food = null;

    #[ORM\ManyToOne(targetEntity: Recipe::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Recipe $recipe = null;

    /**
     * What the user typed: "2" of something.
     */
    #[ORM\Column(type: Types::FLOAT)]
    #[Assert\Positive]
    private float $quantity;

    /**
     * The unit the user picked for that quantity.
     */
    #[ORM\Column(type: Types::STRING, length: 16, enumType: MeasurementUnit::class)]
    private MeasurementUnit $unit;

    /**
     * Which per-food portion was used, when the unit needed one. Kept for
     * display ("2 slices") and so the entry can be edited later.
     */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $portionLabel = null;

    /**
     * The quantity resolved to grams. Everything downstream reads this.
     */
    #[ORM\Column(type: Types::FLOAT)]
    #[Assert\PositiveOrZero]
    private float $grams;

    #[ORM\Embedded(class: Nutrients::class, columnPrefix: 'n_')]
    private Nutrients $nutrients;

    /**
     * Name as it was at logging time, so history stays readable.
     */
    #[ORM\Column(length: 220)]
    private string $label;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    private function __construct(
        User $user,
        \DateTimeImmutable $loggedOn,
        MealType $mealType,
        float $quantity,
        MeasurementUnit $unit,
        float $grams,
        Nutrients $nutrients,
        string $label,
    ) {
        $this->user = $user;
        $this->loggedOn = $loggedOn;
        $this->mealType = $mealType;
        $this->quantity = $quantity;
        $this->unit = $unit;
        $this->grams = $grams;
        $this->nutrients = $nutrients;
        $this->label = $label;
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function forFood(
        User $user,
        Food $food,
        \DateTimeImmutable $loggedOn,
        MealType $mealType,
        float $quantity,
        MeasurementUnit $unit,
        float $grams,
        ?string $portionLabel = null,
    ): self {
        $entry = new self(
            $user,
            $loggedOn,
            $mealType,
            $quantity,
            $unit,
            $grams,
            $food->nutrientsForGrams($grams),
            $food->getLabel(),
        );
        $entry->food = $food;
        $entry->portionLabel = $portionLabel;

        return $entry;
    }

    /**
     * @param float $servings how many servings of the recipe were eaten
     */
    public static function forRecipe(
        User $user,
        Recipe $recipe,
        \DateTimeImmutable $loggedOn,
        MealType $mealType,
        float $servings,
    ): self {
        $entry = new self(
            $user,
            $loggedOn,
            $mealType,
            $servings,
            MeasurementUnit::Portion,
            $recipe->getGramsPerServing() * $servings,
            $recipe->getNutrientsPerServing()->scaled($servings),
            $recipe->getName(),
        );
        $entry->recipe = $recipe;
        $entry->portionLabel = 'serving';

        return $entry;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getLoggedOn(): \DateTimeImmutable
    {
        return $this->loggedOn;
    }

    public function getMealType(): MealType
    {
        return $this->mealType;
    }

    public function getFood(): ?Food
    {
        return $this->food;
    }

    public function getRecipe(): ?Recipe
    {
        return $this->recipe;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function getUnit(): MeasurementUnit
    {
        return $this->unit;
    }

    public function getPortionLabel(): ?string
    {
        return $this->portionLabel;
    }

    public function getGrams(): float
    {
        return $this->grams;
    }

    public function getNutrients(): Nutrients
    {
        return $this->nutrients;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
