<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\FoodSource;
use App\Nutrition\Nutrients;
use App\Repository\FoodRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One item in the food catalogue, with its nutrition values expressed per 100 g.
 *
 * Everything the app knows about food ends up here, whatever it came from: the
 * seed data, a barcode scan resolved through Open Food Facts, or a recipe the
 * user typed in themselves. Storing imported foods rather than looking them up
 * every time is what makes the second scan of the same product instant.
 */
#[ORM\Entity(repositoryClass: FoodRepository::class)]
#[ORM\Table(name: 'food')]
#[ORM\UniqueConstraint(name: 'uniq_food_barcode', columns: ['barcode'])]
#[ORM\Index(name: 'idx_food_name', columns: ['name'])]
class Food
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    private string $name;

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $brand = null;

    /**
     * EAN-8/EAN-13/UPC as printed on the package. Null for anything that does
     * not come in a package, such as "apple, raw".
     */
    #[ORM\Column(length: 32, nullable: true)]
    private ?string $barcode = null;

    /**
     * Nutrition per 100 g, or per 100 ml for foods measured by volume.
     */
    #[ORM\Embedded(class: Nutrients::class, columnPrefix: 'per100_')]
    private Nutrients $per100;

    /**
     * Grams per millilitre, used to turn volume entries (ml, spoon, cup) into
     * grams. Water is 1.0; oil is about 0.92; honey is about 1.42.
     */
    #[ORM\Column(type: Types::FLOAT)]
    #[Assert\Range(min: 0.1, max: 5.0)]
    private float $densityGPerMl = 1.0;

    #[ORM\Column(type: Types::STRING, length: 32, enumType: FoodSource::class)]
    private FoodSource $source = FoodSource::UserDefined;

    /**
     * Identifier at the source system, so an import can recognise a record it
     * has already seen. For Open Food Facts this is the product barcode.
     */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $externalId = null;

    /**
     * Null for seeded and imported foods, which belong to nobody.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, FoodPortion> */
    #[ORM\OneToMany(mappedBy: 'food', targetEntity: FoodPortion::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $portions;

    public function __construct(
        string $name,
        Nutrients $per100,
        FoodSource $source = FoodSource::UserDefined,
        ?string $brand = null,
        ?string $barcode = null,
    ) {
        $this->name = $name;
        $this->per100 = $per100;
        $this->source = $source;
        $this->brand = $brand;
        $this->barcode = $barcode;
        $this->createdAt = new \DateTimeImmutable();
        $this->portions = new ArrayCollection();
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

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function setBrand(?string $brand): void
    {
        $this->brand = $brand;
    }

    /**
     * Name including the brand, for display in search results.
     */
    public function getLabel(): string
    {
        return null === $this->brand || '' === $this->brand
            ? $this->name
            : \sprintf('%s (%s)', $this->name, $this->brand);
    }

    public function getBarcode(): ?string
    {
        return $this->barcode;
    }

    public function setBarcode(?string $barcode): void
    {
        $this->barcode = $barcode;
    }

    public function getPer100(): Nutrients
    {
        return $this->per100;
    }

    public function setPer100(Nutrients $per100): void
    {
        $this->per100 = $per100;
    }

    /**
     * The nutrition values of a given amount of this food.
     */
    public function nutrientsForGrams(float $grams): Nutrients
    {
        return $this->per100->scaled($grams / 100.0);
    }

    public function getDensityGPerMl(): float
    {
        return $this->densityGPerMl;
    }

    public function setDensityGPerMl(float $densityGPerMl): void
    {
        $this->densityGPerMl = $densityGPerMl;
    }

    public function getSource(): FoodSource
    {
        return $this->source;
    }

    public function setSource(FoodSource $source): void
    {
        $this->source = $source;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): void
    {
        $this->externalId = $externalId;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, FoodPortion>
     */
    public function getPortions(): Collection
    {
        return $this->portions;
    }

    public function addPortion(FoodPortion $portion): void
    {
        if (!$this->portions->contains($portion)) {
            $this->portions->add($portion);
            $portion->setFood($this);
        }
    }

    public function removePortion(FoodPortion $portion): void
    {
        $this->portions->removeElement($portion);
    }

    /**
     * The portion matching a label such as "slice" or "medium", case-insensitively.
     */
    public function findPortion(string $label): ?FoodPortion
    {
        $needle = mb_strtolower(trim($label));

        foreach ($this->portions as $portion) {
            if (mb_strtolower($portion->getLabel()) === $needle) {
                return $portion;
            }
        }

        return null;
    }
}
