<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ActivityLevel;
use App\Enum\Goal;
use App\Enum\Sex;
use App\Repository\UserProfileRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The slow-moving half of a user's body data: the values that feed the calorie
 * calculation but hardly ever change. Weight, muscle mass and fat mass are a
 * time series and live in {@see BodyMeasurement} instead.
 */
#[ORM\Entity(repositoryClass: UserProfileRepository::class)]
#[ORM\Table(name: 'user_profile')]
class UserProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'profile', targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    /**
     * Stored as a date rather than as a number of years, so the age stays
     * correct without anyone having to update it.
     */
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    #[Assert\LessThan('today', message: 'The date of birth must be in the past.')]
    private \DateTimeImmutable $birthDate;

    #[ORM\Column(type: Types::STRING, length: 16, enumType: Sex::class)]
    private Sex $sex;

    #[ORM\Column(type: Types::FLOAT)]
    #[Assert\Range(min: 80, max: 250, notInRangeMessage: 'Height must be between {{ min }} and {{ max }} cm.')]
    private float $heightCm;

    #[ORM\Column(type: Types::STRING, length: 32, enumType: ActivityLevel::class)]
    private ActivityLevel $activityLevel = ActivityLevel::Sedentary;

    #[ORM\Column(type: Types::STRING, length: 32, enumType: Goal::class)]
    private Goal $goal = Goal::MaintainWeight;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        User $user,
        \DateTimeImmutable $birthDate,
        Sex $sex,
        float $heightCm,
        ActivityLevel $activityLevel = ActivityLevel::Sedentary,
        Goal $goal = Goal::MaintainWeight,
    ) {
        $this->user = $user;
        $this->birthDate = $birthDate;
        $this->sex = $sex;
        $this->heightCm = $heightCm;
        $this->activityLevel = $activityLevel;
        $this->goal = $goal;
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getBirthDate(): \DateTimeImmutable
    {
        return $this->birthDate;
    }

    public function setBirthDate(\DateTimeImmutable $birthDate): void
    {
        $this->birthDate = $birthDate;
        $this->touch();
    }

    /**
     * Age in whole years, on the given day (defaults to today).
     */
    public function getAge(?\DateTimeImmutable $on = null): int
    {
        return $this->birthDate->diff($on ?? new \DateTimeImmutable())->y;
    }

    public function getSex(): Sex
    {
        return $this->sex;
    }

    public function setSex(Sex $sex): void
    {
        $this->sex = $sex;
        $this->touch();
    }

    public function getHeightCm(): float
    {
        return $this->heightCm;
    }

    public function setHeightCm(float $heightCm): void
    {
        $this->heightCm = $heightCm;
        $this->touch();
    }

    public function getActivityLevel(): ActivityLevel
    {
        return $this->activityLevel;
    }

    public function setActivityLevel(ActivityLevel $activityLevel): void
    {
        $this->activityLevel = $activityLevel;
        $this->touch();
    }

    public function getGoal(): Goal
    {
        return $this->goal;
    }

    public function setGoal(Goal $goal): void
    {
        $this->goal = $goal;
        $this->touch();
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
