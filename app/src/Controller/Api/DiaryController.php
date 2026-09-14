<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\Dto\ActivityRequest;
use App\Api\Dto\DiaryEntryRequest;
use App\Api\Presenter\DiaryPresenter;
use App\Entity\ActivityEntry;
use App\Entity\DiaryEntry;
use App\Entity\User;
use App\Enum\MeasurementUnit;
use App\Nutrition\DiaryService;
use App\Nutrition\PortionResolver;
use App\Nutrition\UnresolvablePortionException;
use App\Repository\ActivityEntryRepository;
use App\Repository\DiaryEntryRepository;
use App\Repository\FoodRepository;
use App\Repository\RecipeRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Logging what was eaten and what was burned, and reading a day back.
 */
final class DiaryController extends ApiController
{
    public function __construct(
        private readonly DiaryEntryRepository $diaryEntries,
        private readonly ActivityEntryRepository $activityEntries,
        private readonly FoodRepository $foods,
        private readonly RecipeRepository $recipes,
        private readonly PortionResolver $portionResolver,
        private readonly DiaryService $diaryService,
        private readonly DiaryPresenter $presenter,
    ) {
    }

    /**
     * One day: its entries, its activities and its totals.
     */
    public function day(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $date = $this->parseDate($request->query->getString('date') ?: null);

        return $this->json([
            'summary' => $this->diaryService->summarizeDay($user, $date)->toArray(),
            'entries' => array_map(
                fn (DiaryEntry $entry): array => $this->presenter->presentEntry($entry),
                $this->diaryEntries->findForUserOn($user, $date),
            ),
            'activities' => array_map(
                fn (ActivityEntry $activity): array => $this->presenter->presentActivity($activity),
                $this->activityEntries->findForUserOn($user, $date),
            ),
        ]);
    }

    /**
     * Log a food or a serving of a recipe.
     */
    public function createEntry(
        #[CurrentUser] User $user,
        #[MapRequestPayload] DiaryEntryRequest $payload,
    ): JsonResponse {
        // Exactly one target. This is a rule about the pair of fields, so it
        // cannot live on either property as a constraint.
        if ((null === $payload->foodId) === (null === $payload->recipeId)) {
            return $this->error(
                'invalid_target',
                'Send either a foodId or a recipeId, not both and not neither.',
            );
        }

        $date = $this->parseDate($payload->loggedOn);

        if (null !== $payload->recipeId) {
            $recipe = $this->recipes->findAccessibleOneById($payload->recipeId, $user);

            if (null === $recipe) {
                return $this->error('recipe_not_found', 'This recipe does not exist.', Response::HTTP_NOT_FOUND);
            }

            $entry = DiaryEntry::forRecipe($user, $recipe, $date, $payload->mealType, $payload->quantity);
            $this->diaryEntries->save($entry);

            return $this->json(['entry' => $this->presenter->presentEntry($entry)], Response::HTTP_CREATED);
        }

        $food = $this->foods->find($payload->foodId);

        if (null === $food || (null !== $food->getCreatedBy() && $food->getCreatedBy() !== $user)) {
            return $this->error('food_not_found', 'This food does not exist.', Response::HTTP_NOT_FOUND);
        }

        try {
            $grams = $this->portionResolver->toGrams(
                $food,
                $payload->quantity,
                $payload->unit,
                $payload->portionLabel,
            );
        } catch (UnresolvablePortionException $e) {
            // The amount is well-formed but cannot be converted for this food -
            // a client mistake about *this* food, hence 422 rather than 400.
            return $this->error('unresolvable_portion', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $entry = DiaryEntry::forFood(
            $user,
            $food,
            $date,
            $payload->mealType,
            $payload->quantity,
            $payload->unit ?? MeasurementUnit::Gram,
            $grams,
            $payload->portionLabel,
        );

        $this->diaryEntries->save($entry);

        return $this->json(['entry' => $this->presenter->presentEntry($entry)], Response::HTTP_CREATED);
    }

    public function deleteEntry(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $entry = $this->diaryEntries->findOneForUser($id, $user);

        if (null === $entry) {
            return $this->error('entry_not_found', 'This entry does not exist.', Response::HTTP_NOT_FOUND);
        }

        $this->diaryEntries->remove($entry);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Log calories burned through activity.
     */
    public function createActivity(
        #[CurrentUser] User $user,
        #[MapRequestPayload] ActivityRequest $payload,
    ): JsonResponse {
        $activity = new ActivityEntry(
            $user,
            $this->parseDate($payload->performedOn),
            trim($payload->description),
            $payload->caloriesBurned,
            $payload->durationMinutes,
        );

        $this->activityEntries->save($activity);

        return $this->json(
            ['activity' => $this->presenter->presentActivity($activity)],
            Response::HTTP_CREATED,
        );
    }

    public function deleteActivity(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $activity = $this->activityEntries->findOneForUser($id, $user);

        if (null === $activity) {
            return $this->error('activity_not_found', 'This activity does not exist.', Response::HTTP_NOT_FOUND);
        }

        $this->activityEntries->remove($activity);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
