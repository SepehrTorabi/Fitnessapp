<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AppLocale;
use App\Enum\Theme;
use App\Repository\UserPreferencesRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * How one user wants the application to look and behave.
 *
 * Separate from {@see UserProfile}, which is body data feeding the calorie
 * calculation. Nothing here changes a single number - it changes presentation.
 * Keeping the two apart means a settings screen can never accidentally alter
 * someone's calorie target.
 *
 * These live in the database rather than only in the browser so that they
 * follow the user to a new device or a different browser. The frontend still
 * mirrors them into localStorage, but only to avoid a flash of the wrong theme
 * before the first API call answers.
 */
#[ORM\Entity(repositoryClass: UserPreferencesRepository::class)]
#[ORM\Table(name: 'user_preferences')]
class UserPreferences
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'preferences', targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: Types::STRING, length: 8, enumType: AppLocale::class)]
    private AppLocale $locale = AppLocale::English;

    #[ORM\Column(type: Types::STRING, length: 16, enumType: Theme::class)]
    private Theme $theme = Theme::System;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        User $user,
        AppLocale $locale = AppLocale::English,
        Theme $theme = Theme::System,
    ) {
        $this->user = $user;
        $this->locale = $locale;
        $this->theme = $theme;
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

    public function getLocale(): AppLocale
    {
        return $this->locale;
    }

    public function setLocale(AppLocale $locale): void
    {
        $this->locale = $locale;
        $this->touch();
    }

    public function getTheme(): Theme
    {
        return $this->theme;
    }

    public function setTheme(Theme $theme): void
    {
        $this->theme = $theme;
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
