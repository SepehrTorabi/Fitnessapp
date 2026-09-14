<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\Dto\CreateFoodRequest;
use App\Api\Dto\FoodPortionRequest;
use App\Api\Presenter\FoodPresenter;
use App\Entity\Food;
use App\Entity\FoodPortion;
use App\Entity\User;
use App\Enum\FoodSource;
use App\Food\FoodCatalog;
use App\Repository\FoodRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * The food catalogue: searching it, scanning into it, and adding to it.
 */
final class FoodController extends ApiController
{
    public function __construct(
        private readonly FoodCatalog $catalog,
        private readonly FoodRepository $foods,
        private readonly FoodPresenter $foodPresenter,
    ) {
    }

    /**
     * Search our own catalogue, topped up with external hits when it comes back
     * thin.
     *
     * The two groups stay separate in the response: local foods have an id and
     * can be logged straight away, external ones must be imported first. The UI
     * shows them under different headings for that reason.
     */
    public function search(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $query = trim((string) $request->query->get('q', ''));

        if (mb_strlen($query) < 2) {
            return $this->error('query_too_short', 'Enter at least two characters to search.');
        }

        $limit = max(1, min(50, $request->query->getInt('limit', 25)));
        $results = $this->catalog->search($query, $user, $limit);

        return $this->json([
            'query' => $query,
            'local' => $this->foodPresenter->presentMany($results['local']),
            'external' => array_map(
                static fn ($result): array => $result->toArray(),
                $results['external'],
            ),
        ]);
    }

    /**
     * Resolve a scanned barcode, importing the product on first sight.
     */
    public function byBarcode(string $barcode): JsonResponse
    {
        $food = $this->catalog->findByBarcode($barcode);

        if (null === $food) {
            return $this->error(
                'barcode_not_found',
                'No product found for this barcode. You can add it as a new food.',
                Response::HTTP_NOT_FOUND,
            );
        }

        return $this->json(['food' => $this->foodPresenter->present($food)]);
    }

    public function show(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $food = $this->foods->find($id);

        // A food belongs to its creator or to nobody. Anything else is not
        // this user's to read.
        if (null === $food || (null !== $food->getCreatedBy() && $food->getCreatedBy() !== $user)) {
            return $this->error('food_not_found', 'This food does not exist.', Response::HTTP_NOT_FOUND);
        }

        return $this->json(['food' => $this->foodPresenter->present($food)]);
    }

    /**
     * Promote an external search hit into the catalogue.
     *
     * Takes only the identifier, never the nutrition values: re-fetching them
     * from the provider keeps a client from writing arbitrary numbers into a
     * record labelled as coming from Open Food Facts.
     */
    public function import(string $externalId): JsonResponse
    {
        $food = $this->catalog->importByExternalId($externalId);

        if (null === $food) {
            return $this->error(
                'import_failed',
                'This product could not be imported. It may have been removed from the source database.',
                Response::HTTP_NOT_FOUND,
            );
        }

        return $this->json(['food' => $this->foodPresenter->present($food)], Response::HTTP_CREATED);
    }

    /**
     * Define a new food by hand.
     */
    public function create(
        #[CurrentUser] User $user,
        #[MapRequestPayload] CreateFoodRequest $payload,
    ): JsonResponse {
        if (null !== $payload->barcode && null !== $this->foods->findOneByBarcode($payload->barcode)) {
            return $this->error(
                'barcode_taken',
                'A food with this barcode is already in the database.',
                Response::HTTP_CONFLICT,
            );
        }

        $nutrients = $payload->per100;

        $food = new Food(
            trim($payload->name),
            $nutrients->toNutrients(),
            FoodSource::UserDefined,
            null === $payload->brand ? null : trim($payload->brand),
            $payload->barcode,
        );
        $food->setDensityGPerMl($payload->densityGPerMl);
        $food->setCreatedBy($user);

        foreach ($payload->portions as $portion) {
            if ($portion instanceof FoodPortionRequest) {
                $food->addPortion(new FoodPortion($food, trim($portion->label), $portion->grams));
            }
        }

        $this->foods->save($food);

        return $this->json([
            'food' => $this->foodPresenter->present($food),
            // Typed calories that do not match the typed macros almost always
            // mean a per-serving value slipped into a per-100 g field. Worth
            // flagging, not worth rejecting: rounding and sugar alcohols make
            // small disagreements normal.
            'warnings' => $this->energyWarnings($nutrients->kcal ?? 0.0, $nutrients->impliedKcal()),
        ], Response::HTTP_CREATED);
    }

    /**
     * @return list<string>
     */
    private function energyWarnings(float $statedKcal, float $impliedKcal): array
    {
        if ($statedKcal <= 0.0) {
            return [];
        }

        $deviation = abs($statedKcal - $impliedKcal) / $statedKcal;

        if ($deviation <= 0.25) {
            return [];
        }

        return [\sprintf(
            'The macros you entered add up to about %d kcal per 100 g, but you entered %d kcal. Please double-check the values.',
            (int) round($impliedKcal),
            (int) round($statedKcal),
        )];
    }
}
