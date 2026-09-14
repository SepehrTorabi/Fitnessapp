<?php

declare(strict_types=1);

namespace App\Api\Dto;

use App\Nutrition\Nutrients;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Nutrition values per 100 g, as typed in by a user defining a new food.
 */
final readonly class NutrientsRequest
{
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Range(min: 0, max: 1000, notInRangeMessage: 'Energy per 100 g must be between {{ min }} and {{ max }} kcal.')]
        public ?float $kcal = null,

        #[Assert\NotNull]
        #[Assert\Range(min: 0, max: 100)]
        public ?float $proteinG = null,

        #[Assert\NotNull]
        #[Assert\Range(min: 0, max: 100)]
        public ?float $carbsG = null,

        #[Assert\NotNull]
        #[Assert\Range(min: 0, max: 100)]
        public ?float $fatG = null,

        #[Assert\Range(min: 0, max: 100)]
        public ?float $fiberG = null,

        #[Assert\Range(min: 0, max: 100)]
        public ?float $sugarG = null,

        #[Assert\Range(min: 0, max: 100)]
        public ?float $saltG = null,
    ) {
    }

    public function toNutrients(): Nutrients
    {
        return new Nutrients(
            $this->kcal ?? 0.0,
            $this->proteinG ?? 0.0,
            $this->carbsG ?? 0.0,
            $this->fatG ?? 0.0,
            $this->fiberG,
            $this->sugarG,
            $this->saltG,
        );
    }

    /**
     * Energy implied by the macros, using the Atwater factors.
     *
     * Used to warn when the typed calories and the typed macros disagree - the
     * usual cause is a value entered per serving instead of per 100 g.
     */
    public function impliedKcal(): float
    {
        return (($this->proteinG ?? 0.0) * 4.0)
            + (($this->carbsG ?? 0.0) * 4.0)
            + (($this->fatG ?? 0.0) * 9.0);
    }
}
