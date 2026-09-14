<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BodyMeasurementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One weigh-in. Keeping these as a series rather than as columns on the profile
 * means the weight curve is available for free, and the calorie target of a past
 * day can still be recomputed from the body data that was true on that day.
 *
 * At most one measurement per user per day; a second one overwrites the first.
 */
#[ORM\Entity(repositoryClass: BodyMeasurementRepository::class)]
#[ORM\Table(name: 'body_measurement')]
#[ORM\UniqueConstraint(name: 'uniq_measurement_user_day', columns: ['user_id', 'measured_on'])]
#[ORM\Index(name: 'idx_measurement_user_day', columns: ['user_id', 'measured_on'])]
class BodyMeasurement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'bodyMeasurements', targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $measuredOn;

    #[ORM\Column(type: Types::FLOAT)]
    #[Assert\Range(min: 20, max: 500, notInRangeMessage: 'Weight must be between {{ min }} and {{ max }} kg.')]
    private float $weightKg;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Assert\Range(min: 0, max: 200)]
    private ?float $muscleMassKg = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Assert\Range(min: 0, max: 300)]
    private ?float $fatMassKg = null;

    public function __construct(
        User $user,
        \DateTimeImmutable $measuredOn,
        float $weightKg,
        ?float $muscleMassKg = null,
        ?float $fatMassKg = null,
    ) {
        $this->user = $user;
        $this->measuredOn = $measuredOn;
        $this->weightKg = $weightKg;
        $this->muscleMassKg = $muscleMassKg;
        $this->fatMassKg = $fatMassKg;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    public function getMeasuredOn(): \DateTimeImmutable
    {
        return $this->measuredOn;
    }

    public function getWeightKg(): float
    {
        return $this->weightKg;
    }

    public function setWeightKg(float $weightKg): void
    {
        $this->weightKg = $weightKg;
    }

    public function getMuscleMassKg(): ?float
    {
        return $this->muscleMassKg;
    }

    public function setMuscleMassKg(?float $muscleMassKg): void
    {
        $this->muscleMassKg = $muscleMassKg;
    }

    public function getFatMassKg(): ?float
    {
        return $this->fatMassKg;
    }

    public function setFatMassKg(?float $fatMassKg): void
    {
        $this->fatMassKg = $fatMassKg;
    }

    /**
     * Fat-free mass in kg, when the body-fat figure is known. This is what the
     * Katch-McArdle formula needs, and it is a better basis for the calorie
     * target than total weight because it ignores fat, which is metabolically
     * much less active.
     */
    public function getLeanBodyMassKg(): ?float
    {
        if (null === $this->fatMassKg) {
            return null;
        }

        return max(0.0, $this->weightKg - $this->fatMassKg);
    }
}
