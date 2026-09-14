<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\HttpFoundation\Response;

#[CoversNothing]
final class DiaryApiTest extends ApiTestCase
{
    public function testTheDiaryRequiresAuthentication(): void
    {
        $this->jsonRequest('GET', '/api/diary');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertSame('authentication_required', $this->responseData()['error']);
    }

    public function testLoggingAFoodInGramsStoresTheScaledNutrients(): void
    {
        $user = $this->createUserWithProfile();
        $food = $this->createFood(kcal: 250.0);
        $this->login($user);

        $this->jsonRequest('POST', '/api/diary/entries', [
            'foodId' => $food->getId(),
            'mealType' => 'lunch',
            'quantity' => 200,
            'unit' => 'g',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $entry = $this->responseData()['entry'];
        self::assertEqualsWithDelta(200.0, $entry['grams'], 0.01);
        self::assertEqualsWithDelta(500.0, $entry['nutrients']['kcal'], 0.01);
    }

    public function testLoggingByASliceUsesTheFoodsPortionDefinition(): void
    {
        $user = $this->createUserWithProfile();
        $food = $this->createFood(kcal: 250.0); // one slice is 50 g
        $this->login($user);

        $this->jsonRequest('POST', '/api/diary/entries', [
            'foodId' => $food->getId(),
            'mealType' => 'breakfast',
            'quantity' => 2,
            'unit' => 'slice',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $entry = $this->responseData()['entry'];
        self::assertEqualsWithDelta(100.0, $entry['grams'], 0.01);
        self::assertEqualsWithDelta(250.0, $entry['nutrients']['kcal'], 0.01);
    }

    public function testLoggingByVolumeUsesTheFoodsDensity(): void
    {
        $user = $this->createUserWithProfile();
        $food = $this->createFood(kcal: 900.0, density: 0.92);
        $this->login($user);

        $this->jsonRequest('POST', '/api/diary/entries', [
            'foodId' => $food->getId(),
            'mealType' => 'dinner',
            'quantity' => 1,
            'unit' => 'tbsp',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // 15 ml * 0.92 g/ml = 13.8 g
        self::assertEqualsWithDelta(13.8, $this->responseData()['entry']['grams'], 0.01);
    }

    public function testAUnitTheFoodHasNoDefinitionForIsRejected(): void
    {
        $user = $this->createUserWithProfile();
        $food = $this->createFood();
        $this->login($user);

        $this->jsonRequest('POST', '/api/diary/entries', [
            'foodId' => $food->getId(),
            'mealType' => 'lunch',
            'quantity' => 1,
            'unit' => 'handful',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('unresolvable_portion', $this->responseData()['error']);
    }

    public function testAnEntryMustTargetEitherAFoodOrARecipe(): void
    {
        $user = $this->createUserWithProfile();
        $this->login($user);

        $this->jsonRequest('POST', '/api/diary/entries', [
            'mealType' => 'lunch',
            'quantity' => 1,
            'unit' => 'g',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertSame('invalid_target', $this->responseData()['error']);
    }

    public function testAnEntryCannotTargetBothAFoodAndARecipe(): void
    {
        $user = $this->createUserWithProfile();
        $food = $this->createFood();
        $this->login($user);

        $this->jsonRequest('POST', '/api/diary/entries', [
            'foodId' => $food->getId(),
            'recipeId' => 1,
            'mealType' => 'lunch',
            'quantity' => 1,
            'unit' => 'g',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testANegativeAmountIsRejected(): void
    {
        $user = $this->createUserWithProfile();
        $food = $this->createFood();
        $this->login($user);

        $this->jsonRequest('POST', '/api/diary/entries', [
            'foodId' => $food->getId(),
            'mealType' => 'lunch',
            'quantity' => -100,
            'unit' => 'g',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testTheDayViewSumsEveryEntry(): void
    {
        $user = $this->createUserWithProfile();
        $food = $this->createFood(kcal: 100.0);
        $this->login($user);

        foreach ([100, 250] as $grams) {
            $this->jsonRequest('POST', '/api/diary/entries', [
                'foodId' => $food->getId(),
                'mealType' => 'snack',
                'quantity' => $grams,
                'unit' => 'g',
            ]);
            self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        }

        $this->jsonRequest('GET', '/api/diary');
        self::assertResponseIsSuccessful();

        $data = $this->responseData();
        self::assertCount(2, $data['entries']);
        self::assertEqualsWithDelta(350.0, $data['summary']['consumed']['kcal'], 0.01);
    }

    /**
     * The snapshot is the point of storing nutrients on the entry: correcting a
     * food later must not silently rewrite days the user already logged.
     */
    public function testChangingAFoodLaterDoesNotAlterAlreadyLoggedEntries(): void
    {
        $user = $this->createUserWithProfile();
        $food = $this->createFood(kcal: 250.0);
        $this->login($user);

        $this->jsonRequest('POST', '/api/diary/entries', [
            'foodId' => $food->getId(),
            'mealType' => 'lunch',
            'quantity' => 100,
            'unit' => 'g',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $food->setPer100(new \App\Nutrition\Nutrients(999.0, 1.0, 1.0, 1.0));
        $this->entityManager->flush();

        $this->jsonRequest('GET', '/api/diary');

        self::assertEqualsWithDelta(250.0, $this->responseData()['summary']['consumed']['kcal'], 0.01);
    }

    public function testActivityCaloriesRaiseTheDaysBudget(): void
    {
        $user = $this->createUserWithProfile();
        $this->login($user);

        $this->jsonRequest('GET', '/api/diary');
        $budgetBefore = $this->responseData()['summary']['budgetKcal'];

        $this->jsonRequest('POST', '/api/diary/activities', [
            'description' => 'Running 5 km',
            'caloriesBurned' => 400,
            'durationMinutes' => 28,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->jsonRequest('GET', '/api/diary');
        $summary = $this->responseData()['summary'];

        self::assertEqualsWithDelta(400.0, $summary['caloriesBurned'], 0.01);
        self::assertEqualsWithDelta($budgetBefore + 400, $summary['budgetKcal'], 0.01);
    }

    public function testAnEntryCanBeDeleted(): void
    {
        $user = $this->createUserWithProfile();
        $food = $this->createFood();
        $this->login($user);

        $this->jsonRequest('POST', '/api/diary/entries', [
            'foodId' => $food->getId(),
            'mealType' => 'lunch',
            'quantity' => 100,
            'unit' => 'g',
        ]);
        $entryId = $this->responseData()['entry']['id'];

        $this->jsonRequest('DELETE', '/api/diary/entries/'.$entryId);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->jsonRequest('GET', '/api/diary');
        self::assertCount(0, $this->responseData()['entries']);
    }

    /**
     * One user must never be able to reach into another's diary, and the answer
     * must be "does not exist" rather than "not yours" - which would confirm
     * that the id is real.
     */
    public function testAUserCannotDeleteSomebodyElsesEntry(): void
    {
        $owner = $this->createUserWithProfile('owner@example.test');
        $food = $this->createFood();
        $this->login($owner);

        $this->jsonRequest('POST', '/api/diary/entries', [
            'foodId' => $food->getId(),
            'mealType' => 'lunch',
            'quantity' => 100,
            'unit' => 'g',
        ]);
        $entryId = $this->responseData()['entry']['id'];

        $intruder = $this->createUser('intruder@example.test');
        $this->login($intruder);

        $this->jsonRequest('DELETE', '/api/diary/entries/'.$entryId);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('entry_not_found', $this->responseData()['error']);
    }

    public function testOneUserDoesNotSeeAnothersEntries(): void
    {
        $owner = $this->createUserWithProfile('owner2@example.test');
        $food = $this->createFood();
        $this->login($owner);

        $this->jsonRequest('POST', '/api/diary/entries', [
            'foodId' => $food->getId(),
            'mealType' => 'lunch',
            'quantity' => 100,
            'unit' => 'g',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $other = $this->createUserWithProfile('other2@example.test');
        $this->login($other);

        $this->jsonRequest('GET', '/api/diary');

        self::assertCount(0, $this->responseData()['entries']);
    }

    public function testEntriesAreScopedToTheRequestedDay(): void
    {
        $user = $this->createUserWithProfile();
        $food = $this->createFood();
        $this->login($user);

        $this->jsonRequest('POST', '/api/diary/entries', [
            'foodId' => $food->getId(),
            'mealType' => 'lunch',
            'quantity' => 100,
            'unit' => 'g',
            'loggedOn' => '2026-01-15',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->jsonRequest('GET', '/api/diary?date=2026-01-15');
        self::assertCount(1, $this->responseData()['entries']);

        $this->jsonRequest('GET', '/api/diary?date=2026-01-16');
        self::assertCount(0, $this->responseData()['entries']);
    }

    public function testAMalformedDateIsRejected(): void
    {
        $user = $this->createUserWithProfile();
        $this->login($user);

        $this->jsonRequest('GET', '/api/diary?date=not-a-date');

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }
}
