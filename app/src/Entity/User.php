<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AppLocale;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'An account with this e-mail address already exists.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private string $email;

    /** @var list<string> */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private string $password;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    private string $displayName;

    /**
     * Set by the e-mail confirmation flow. Until it is true the account exists
     * but cannot log in - see {@see \App\Security\AccountStatusChecker}.
     */
    #[ORM\Column]
    private bool $verified = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: UserProfile::class, cascade: ['persist', 'remove'])]
    private ?UserProfile $profile = null;

    /**
     * Interface settings - language, colour scheme. Null until the user first
     * changes something; the API answers with the defaults in the meantime.
     */
    #[ORM\OneToOne(mappedBy: 'user', targetEntity: UserPreferences::class, cascade: ['persist', 'remove'])]
    private ?UserPreferences $preferences = null;

    /** @var Collection<int, BodyMeasurement> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: BodyMeasurement::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['measuredOn' => 'DESC'])]
    private Collection $bodyMeasurements;

    public function __construct(string $email, string $displayName)
    {
        $this->email = $email;
        $this->displayName = $displayName;
        $this->createdAt = new \DateTimeImmutable();
        $this->bodyMeasurements = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): void
    {
        $this->roles = $roles;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): void
    {
        $this->displayName = $displayName;
    }

    public function isVerified(): bool
    {
        return $this->verified;
    }

    public function markVerified(): void
    {
        $this->verified = true;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getProfile(): ?UserProfile
    {
        return $this->profile;
    }

    public function setProfile(UserProfile $profile): void
    {
        $this->profile = $profile;

        if ($profile->getUser() !== $this) {
            $profile->setUser($this);
        }
    }

    public function getPreferences(): ?UserPreferences
    {
        return $this->preferences;
    }

    public function setPreferences(UserPreferences $preferences): void
    {
        $this->preferences = $preferences;

        if ($preferences->getUser() !== $this) {
            $preferences->setUser($this);
        }
    }

    /**
     * The language to write to this user in - their choice, or English until
     * they make one. Used by the transactional mails.
     */
    public function getLocale(): AppLocale
    {
        return $this->preferences?->getLocale() ?? AppLocale::default();
    }

    /**
     * @return Collection<int, BodyMeasurement>
     */
    public function getBodyMeasurements(): Collection
    {
        return $this->bodyMeasurements;
    }

    public function addBodyMeasurement(BodyMeasurement $measurement): void
    {
        if (!$this->bodyMeasurements->contains($measurement)) {
            $this->bodyMeasurements->add($measurement);
            $measurement->setUser($this);
        }
    }

    /**
     * Erase any temporary sensitive data. The password hash itself must stay:
     * it is the persisted credential, not a transient plaintext copy.
     */
    public function eraseCredentials(): void
    {
    }
}
