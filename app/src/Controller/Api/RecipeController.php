<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\Dto\RecipeIngredientRequest;
use App\Api\Dto\RecipeRequest;
use App\Api\Presenter\RecipePresenter;
use App\Entity\Recipe;
use App\Entity\RecipeIngredient;
use App\Entity\User;
use App\Repository\FoodRepository;
use App\Repository\RecipeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * User-defined recipes.
 *
 * A recipe has no nutrition values of its own - they are always summed from the
 * ingredients, so there is nothing here to keep in step by hand.
 */
final class RecipeController extends ApiController
{
    public function __construct(
        private readonly RecipeRepository $recipes,
        private readonly FoodRepository $foods,
        private readonly EntityManagerInterface $entityManager,
        private readonly RecipePresenter $presenter,
    ) {
    }

    public function list(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $query = trim((string) $request->query->get('q', ''));

        return $this->json([
            'recipes' => $this->presenter->presentMany(
                $this->recipes->findAccessible($user, '' === $query ? null : $query),
            ),
        ]);
    }

    public function show(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $recipe = $this->recipes->findAccessibleOneById($id, $user);

        if (null === $recipe) {
            return $this->error('recipe_not_found', 'This recipe does not exist.', Response::HTTP_NOT_FOUND);
        }

        return $this->json(['recipe' => $this->presenter->present($recipe)]);
    }

    public function create(
        #[CurrentUser] User $user,
        #[MapRequestPayload] RecipeRequest $payload,
    ): JsonResponse {
        $recipe = new Recipe(trim($payload->name), $payload->servings ?? 1, $user);
        $recipe->setDescription($payload->description);
        $recipe->setPublic($payload->public);

        $error = $this->applyIngredients($recipe, $payload->ingredients, $user);

        if (null !== $error) {
            return $error;
        }

        $this->recipes->save($recipe);

        return $this->json(['recipe' => $this->presenter->present($recipe)], Response::HTTP_CREATED);
    }

    public function update(
        int $id,
        #[CurrentUser] User $user,
        #[MapRequestPayload] RecipeRequest $payload,
    ): JsonResponse {
        $recipe = $this->recipes->find($id);

        // Editing needs ownership, not just visibility: a public recipe is
        // readable by everyone but still belongs to its author.
        if (null === $recipe || $recipe->getCreatedBy() !== $user) {
            return $this->error('recipe_not_found', 'This recipe does not exist.', Response::HTTP_NOT_FOUND);
        }

        $recipe->setName(trim($payload->name));
        $recipe->setDescription($payload->description);
        $recipe->setServings($payload->servings ?? 1);
        $recipe->setPublic($payload->public);

        foreach ($recipe->getIngredients()->toArray() as $existing) {
            $recipe->removeIngredient($existing);
        }

        $error = $this->applyIngredients($recipe, $payload->ingredients, $user);

        if (null !== $error) {
            return $error;
        }

        $this->entityManager->flush();

        return $this->json(['recipe' => $this->presenter->present($recipe)]);
    }

    public function delete(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $recipe = $this->recipes->find($id);

        if (null === $recipe || $recipe->getCreatedBy() !== $user) {
            return $this->error('recipe_not_found', 'This recipe does not exist.', Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($recipe);
        $this->entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Attach the ingredients, or return the response explaining why not.
     *
     * @param list<RecipeIngredientRequest> $ingredients
     */
    private function applyIngredients(Recipe $recipe, array $ingredients, User $user): ?JsonResponse
    {
        foreach ($ingredients as $ingredient) {
            $food = $this->foods->find($ingredient->foodId);

            if (null === $food || (null !== $food->getCreatedBy() && $food->getCreatedBy() !== $user)) {
                return $this->error(
                    'food_not_found',
                    \sprintf('Ingredient food #%d does not exist.', $ingredient->foodId),
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            $recipe->addIngredient(new RecipeIngredient($recipe, $food, $ingredient->grams));
        }

        return null;
    }
}
