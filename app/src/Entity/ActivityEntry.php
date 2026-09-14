<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ActivityEntryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Energy burned through deliberate activity on a given day.
 *
 * These calories are added to the day's budget: the dashboard compares intake
 * against (target + burned), which is how "I trained today so I may eat more"
 * shows up in the numbers.
 */
#[ORM\Entity(repositoryClass: ActivityEntryRepository::class)]
#[ORM\Table(name: 'activity_entry')]
#[ORM\Index(name: 'idx_activity_user_day', columns: ['user_id', 'performed_on'])]
class ActivityEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $performedOn;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    private string $description;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?int $durationMinutes = null;

    #[ORM\Column(type: Types::FLOAT)]
    #[Assert\PositiveOrZero]
    private float $caloriesBurned;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        User $user,
        \DateTimeImmutable $performedOn,
        string $description,
        float $caloriesBurned,
        ?int $durationMinutes = null,
    ) {
        $this->user = $user;
        $this->performedOn = $performedOn;
        $this->description = $description;
        $this->caloriesBurned = $caloriesBurned;
        $this->durationMinutes = $durationMinutes;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getPerformedOn(): \DateTimeImmutable
    {
        return $this->performedOn;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getDurationMinutes(): ?int
    {
        return $this->durationMinutes;
    }

    public function setDurationMinutes(?int $durationMinutes): void
    {
        $this->durationMinutes = $durationMinutes;
    }

    public function getCaloriesBurned(): float
    {
        return $this->caloriesBurned;
    }

    public function setCaloriesBurned(float $caloriesBurned): void
    {
        $this->caloriesBurned = $caloriesBurned;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
