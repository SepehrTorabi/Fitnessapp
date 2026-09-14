<?php

declare(strict_types=1);

namespace App\Nutrition;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * An immutable bundle of nutrition values.
 *
 * The same shape is needed in a lot of places - the values of a food per 100 g,
 * the frozen values of a logged portion, the sum of a day, the target for a day -
 * so it is a Doctrine embeddable: one class, stored inline as columns of
 * whichever table embeds it, no join.
 *
 * Energy is in kilocalories, every macro in grams. Protein, carbohydrate and fat
 * are always known. Fibre, sugar and salt are frequently missing from real-world
 * sources, so null means "not known" and is kept distinct from 0.0.
 */
#[ORM\Embeddable]
class Nutrients
{
    #[ORM\Column(type: Types::FLOAT)]
    private float $kcal;

    #[ORM\Column(type: Types::FLOAT)]
    private float $proteinG;

    #[ORM\Column(type: Types::FLOAT)]
    private float $carbsG;

    #[ORM\Column(type: Types::FLOAT)]
    private float $fatG;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $fiberG;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $sugarG;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $saltG;

    public function __construct(
        float $kcal = 0.0,
        float $proteinG = 0.0,
        float $carbsG = 0.0,
        float $fatG = 0.0,
        ?float $fiberG = null,
        ?float $sugarG = null,
        ?float $saltG = null,
    ) {
        $this->kcal = $kcal;
        $this->proteinG = $proteinG;
        $this->carbsG = $carbsG;
        $this->fatG = $fatG;
        $this->fiberG = $fiberG;
        $this->sugarG = $sugarG;
        $this->saltG = $saltG;
    }

    public static function zero(): self
    {
        return new self();
    }

    public function getKcal(): float
    {
        return $this->kcal;
    }

    public function getProteinG(): float
    {
        return $this->proteinG;
    }

    public function getCarbsG(): float
    {
        return $this->carbsG;
    }

    public function getFatG(): float
    {
        return $this->fatG;
    }

    public function getFiberG(): ?float
    {
        return $this->fiberG;
    }

    public function getSugarG(): ?float
    {
        return $this->sugarG;
    }

    public function getSaltG(): ?float
    {
        return $this->saltG;
    }

    /**
     * Scale every value by a factor, e.g. 1.5 to go from "per 100 g" to 150 g.
     */
    public function scaled(float $factor): self
    {
        return new self(
            $this->kcal * $factor,
            $this->proteinG * $factor,
            $this->carbsG * $factor,
            $this->fatG * $factor,
            null === $this->fiberG ? null : $this->fiberG * $factor,
            null === $this->sugarG ? null : $this->sugarG * $factor,
            null === $this->saltG ? null : $this->saltG * $factor,
        );
    }

    public function plus(self $other): self
    {
        return new self(
            $this->kcal + $other->kcal,
            $this->proteinG + $other->proteinG,
            $this->carbsG + $other->carbsG,
            $this->fatG + $other->fatG,
            self::addOptional($this->fiberG, $other->fiberG),
            self::addOptional($this->sugarG, $other->sugarG),
            self::addOptional($this->saltG, $other->saltG),
        );
    }

    public function rounded(int $precision = 1): self
    {
        return new self(
            round($this->kcal, $precision),
            round($this->proteinG, $precision),
            round($this->carbsG, $precision),
            round($this->fatG, $precision),
            null === $this->fiberG ? null : round($this->fiberG, $precision),
            null === $this->sugarG ? null : round($this->sugarG, $precision),
            null === $this->saltG ? null : round($this->saltG, $precision),
        );
    }

    /**
     * @return array{kcal: float, proteinG: float, carbsG: float, fatG: float, fiberG: float|null, sugarG: float|null, saltG: float|null}
     */
    public function toArray(): array
    {
        return [
            'kcal' => $this->kcal,
            'proteinG' => $this->proteinG,
            'carbsG' => $this->carbsG,
            'fatG' => $this->fatG,
            'fiberG' => $this->fiberG,
            'sugarG' => $this->sugarG,
            'saltG' => $this->saltG,
        ];
    }

    /**
     * Summing an unknown value with a known one keeps the known one: a day's
     * fibre total is "at least this much" rather than collapsing to null the
     * moment one food is missing the figure. Two unknowns stay unknown.
     */
    private static function addOptional(?float $a, ?float $b): ?float
    {
        if (null === $a && null === $b) {
            return null;
        }

        return ($a ?? 0.0) + ($b ?? 0.0);
    }
}
