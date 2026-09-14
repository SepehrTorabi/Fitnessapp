<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\FoodPortionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A named amount of one specific food: "1 slice = 32 g", "1 medium = 180 g".
 *
 * This is what makes the imprecise units usable. "One slice" is meaningless in
 * general but perfectly well defined per food, so the conversion is stored next
 * to the food rather than guessed at logging time.
 */
#[ORM\Entity(repositoryClass: FoodPortionRepository::class)]
#[ORM\Table(name: 'food_portion')]
#[ORM\UniqueConstraint(name: 'uniq_portion_food_label', columns: ['food_id', 'label'])]
class FoodPortion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'portions', targetEntity: Food::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Food $food;

    #[ORM\Column(length: 64)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    private string $label;

    #[ORM\Column(type: Types::FLOAT)]
    #[Assert\Positive]
    private float $grams;

    public function __construct(Food $food, string $label, float $grams)
    {
        $this->food = $food;
        $this->label = $label;
        $this->grams = $grams;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFood(): Food
    {
        return $this->food;
    }

    public function setFood(Food $food): void
    {
        $this->food = $food;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getGrams(): float
    {
        return $this->grams;
    }

    public function setGrams(float $grams): void
    {
        $this->grams = $grams;
    }
}
