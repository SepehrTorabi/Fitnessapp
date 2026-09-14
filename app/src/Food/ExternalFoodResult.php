<?php

declare(strict_types=1);

namespace App\Food;

use App\Enum\FoodSource;
use App\Nutrition\Nutrients;

/**
 * A food found in an external database, before anything is written to our own.
 *
 * Search hits stay in this form until the user actually picks one; only then is
 * a {@see \App\Entity\Food} created. Persisting every search result would fill
 * the catalogue with products nobody ever ate.
 */
final readonly class ExternalFoodResult
{
    /**
     * @param array<string, float> $portions named portions in grams, e.g. ['serving' => 30.0]
     */
    public function __construct(
        public string $name,
        public Nutrients $per100,
        public FoodSource $source,
        public string $externalId,
        public ?string $brand = null,
        public ?string $barcode = null,
        public array $portions = [],
    ) {
    }

    public function getLabel(): string
    {
        return null === $this->brand || '' === $this->brand
            ? $this->name
            : \sprintf('%s (%s)', $this->name, $this->brand);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'brand' => $this->brand,
            'barcode' => $this->barcode,
            'label' => $this->getLabel(),
            'source' => $this->source->value,
            'externalId' => $this->externalId,
            'per100' => $this->per100->rounded(1)->toArray(),
            'portions' => $this->portions,
        ];
    }
}
