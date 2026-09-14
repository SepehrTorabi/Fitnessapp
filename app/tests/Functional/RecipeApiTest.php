<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\HttpFoundation\Response;

#[CoversNothing]
final class RecipeApiTest extends ApiTestCase
{
    public function testARecipeDerivesItsNutritionFromItsIngredients(): void
    {
        $user = $this->createUser();
        // Both foods are 250 kcal / 10 g protein / 40 g carbs / 5 g fat per 100 g.
        $oats = $this->createFood('Oats');
        $milk = $this->createFood('Milk');
        $this->login($user);

        $this->jsonRequest('POST', '/api/recipes', [
            'name' => 'Porridge',
            'servings' => 2,
            'ingredients' => [
                ['foodId' => $oats->getId(), 'grams' => 100],
                ['foodId' => $milk->getId(), 'grams' => 300],
            ],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $recipe = $this->responseData()['recipe'];

        // 400 g of a 250 kcal/100 g food is 1000 kcal for the whole pot.
        self::assertEqualsWithDelta(400.0, $recipe['totalGrams'], 0.01);
        self::assertEqualsWithDelta(1000.0, $recipe['totalNutrients']['kcal'], 0.01);

        // Two servings, so half each.
        self::assertEqualsWithDelta(500.0, $recipe['perServing']['kcal'], 0.01);
        self::assertEqualsWithDelta(200.0, $recipe['gramsPerServing'], 0.01);
    }

    public function testARecipeNeedsAtLeastOneIngredient(): void
    {
        $this->login($this->createUser());

        $this->jsonRequest('POST', '/api/recipes', [
            'name' => 'Empty pot',
            'servings' => 1,
            'ingredients' => [],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testAnIngredientThatDoesNotExistIsRejected(): void
    {
        $this->login($this->createUser());

        $this->jsonRequest('POST', '/api/recipes', [
            'name' => 'Ghost stew',
            'servings' => 1,
            'ingredients' => [['foodId' => 999999, 'grams' => 100]],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('food_not_found', $this->responseData()['error']);
    }

    public function testLoggingAServingOfARecipeRecordsItsNutrition(): void
    {
        $user = $this->createUser();
        $oats = $this->createFood('Oats', kcal: 400.0);
        $this->login($user);

        $this->jsonRequest('POST', '/api/recipes', [
            'name' => 'Big bowl',
            'servings' => 4,
            'ingredients' => [['foodId' => $oats->getId(), 'grams' => 400]],
        ]);
        $recipeId = $this->responseData()['recipe']['id'];

        $this->jsonRequest('POST', '/api/diary/entries', [
            'recipeId' => $recipeId,
            'mealType' => 'breakfast',
            'quantity' => 1,
            'unit' => 'portion',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // 400 g at 400 kcal/100 g is 1600 kcal, over four servings: 400 each.
        $entry = $this->responseData()['entry'];
        self::assertEqualsWithDelta(400.0, $entry['nutrients']['kcal'], 0.01);
        self::assertEqualsWithDelta(100.0, $entry['grams'], 0.01);
        self::assertSame('Big bowl', $entry['label']);
    }

    public function testHalfAServingIsHalfTheNutrition(): void
    {
        $user = $this->createUser();
        $oats = $this->createFood('Oats', kcal: 400.0);
        $this->login($user);

        $this->jsonRequest('POST', '/api/recipes', [
            'name' => 'Shareable bowl',
            'servings' => 2,
            'ingredients' => [['foodId' => $oats->getId(), 'grams' => 200]],
        ]);
        $recipeId = $this->responseData()['recipe']['id'];

        $this->jsonRequest('POST', '/api/diary/entries', [
            'recipeId' => $recipeId,
            'mealType' => 'snack',
            'quantity' => 0.5,
            'unit' => 'portion',
        ]);

        // Whole pot 800 kcal, one serving 400, half a serving 200.
        self::assertEqualsWithDelta(200.0, $this->responseData()['entry']['nutrients']['kcal'], 0.01);
    }

    /**
     * A private recipe belongs to its author; a public one is readable by all.
     */
    public function testAPrivateRecipeIsInvisibleToOtherUsers(): void
    {
        $owner = $this->createUser('recipe-owner@example.test');
        $food = $this->createFood();
        $this->login($owner);

        $this->jsonRequest('POST', '/api/recipes', [
            'name' => 'Family secret',
            'servings' => 1,
            'public' => false,
            'ingredients' => [['foodId' => $food->getId(), 'grams' => 100]],
        ]);
        $recipeId = $this->responseData()['recipe']['id'];

        $this->login($this->createUser('recipe-stranger@example.test'));

        $this->jsonRequest('GET', '/api/recipes');
        self::assertCount(0, $this->responseData()['recipes']);

        $this->jsonRequest('GET', '/api/recipes/'.$recipeId);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testAPublicRecipeIsReadableByEveryone(): void
    {
        $owner = $this->createUser('sharer@example.test');
        $food = $this->createFood();
        $this->login($owner);

        $this->jsonRequest('POST', '/api/recipes', [
            'name' => 'Shared bowl',
            'servings' => 1,
            'public' => true,
            'ingredients' => [['foodId' => $food->getId(), 'grams' => 100]],
        ]);
        $recipeId = $this->responseData()['recipe']['id'];

        $this->login($this->createUser('reader@example.test'));
        $this->jsonRequest('GET', '/api/recipes/'.$recipeId);

        self::assertResponseIsSuccessful();
        self::assertSame('Shared bowl', $this->responseData()['recipe']['name']);
    }

    /**
     * Visibility is not permission: a public recipe can be read by anyone but
     * still only edited by its author.
     */
    public function testAPublicRecipeCanStillOnlyBeEditedByItsAuthor(): void
    {
        $owner = $this->createUser('editor-owner@example.test');
        $food = $this->createFood();
        $this->login($owner);

        $this->jsonRequest('POST', '/api/recipes', [
            'name' => 'Public but mine',
            'servings' => 1,
            'public' => true,
            'ingredients' => [['foodId' => $food->getId(), 'grams' => 100]],
        ]);
        $recipeId = $this->responseData()['recipe']['id'];

        $this->login($this->createUser('vandal@example.test'));

        $this->jsonRequest('PUT', '/api/recipes/'.$recipeId, [
            'name' => 'Vandalised',
            'servings' => 1,
            'ingredients' => [['foodId' => $food->getId(), 'grams' => 100]],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->jsonRequest('DELETE', '/api/recipes/'.$recipeId);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testUpdatingReplacesTheIngredientList(): void
    {
        $user = $this->createUser();
        $a = $this->createFood('Food A', kcal: 100.0);
        $b = $this->createFood('Food B', kcal: 300.0);
        $this->login($user);

        $this->jsonRequest('POST', '/api/recipes', [
            'name' => 'Changing dish',
            'servings' => 1,
            'ingredients' => [['foodId' => $a->getId(), 'grams' => 100]],
        ]);
        $recipeId = $this->responseData()['recipe']['id'];

        $this->jsonRequest('PUT', '/api/recipes/'.$recipeId, [
            'name' => 'Changing dish',
            'servings' => 1,
            'ingredients' => [['foodId' => $b->getId(), 'grams' => 100]],
        ]);

        self::assertResponseIsSuccessful();

        $recipe = $this->responseData()['recipe'];
        self::assertCount(1, $recipe['ingredients']);
        self::assertEqualsWithDelta(300.0, $recipe['totalNutrients']['kcal'], 0.01);
    }

    public function testARecipeCanBeDeletedByItsAuthor(): void
    {
        $user = $this->createUser();
        $food = $this->createFood();
        $this->login($user);

        $this->jsonRequest('POST', '/api/recipes', [
            'name' => 'Doomed dish',
            'servings' => 1,
            'ingredients' => [['foodId' => $food->getId(), 'grams' => 100]],
        ]);
        $recipeId = $this->responseData()['recipe']['id'];

        $this->jsonRequest('DELETE', '/api/recipes/'.$recipeId);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->jsonRequest('GET', '/api/recipes/'.$recipeId);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testRecipesRequireAuthentication(): void
    {
        $this->jsonRequest('GET', '/api/recipes');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
