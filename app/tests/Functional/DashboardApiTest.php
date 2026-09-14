<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\HttpFoundation\Response;

#[CoversNothing]
final class DashboardApiTest extends ApiTestCase
{
    public function testTheDashboardRequiresAuthentication(): void
    {
        $this->jsonRequest('GET', '/api/dashboard');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testTheHistoryCoversTheRequestedNumberOfDays(): void
    {
        $this->login($this->createUserWithProfile());

        $this->jsonRequest('GET', '/api/dashboard?days=7');

        self::assertResponseIsSuccessful();
        self::assertCount(7, $this->responseData()['history']);
    }

    /**
     * Days with nothing logged still have to appear, or a week with three
     * entries would draw as a three-day chart.
     */
    public function testDaysWithNothingLoggedAppearAsZeroes(): void
    {
        $user = $this->createUserWithProfile();
        $food = $this->createFood(kcal: 100.0);
        $this->login($user);

        $this->jsonRequest('POST', '/api/diary/entries', [
            'foodId' => $food->getId(),
            'mealType' => 'lunch',
            'quantity' => 500,
            'unit' => 'g',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->jsonRequest('GET', '/api/dashboard?days=7');
        $history = $this->responseData()['history'];

        self::assertCount(7, $history);

        $consumed = array_column(array_column($history, 'consumed'), 'kcal');
        self::assertSame([0, 0, 0, 0, 0, 0], \array_slice($consumed, 0, 6));
        self::assertEqualsWithDelta(500.0, end($consumed), 0.01);
    }

    public function testTheHistoryIsOrderedOldestFirstAndEndsToday(): void
    {
        $this->login($this->createUserWithProfile());

        $this->jsonRequest('GET', '/api/dashboard?days=5');
        $dates = array_column($this->responseData()['history'], 'date');

        $sorted = $dates;
        sort($sorted);

        self::assertSame($sorted, $dates, 'The chart reads left to right, oldest first.');
        self::assertSame((new \DateTimeImmutable('today'))->format('Y-m-d'), end($dates));
    }

    /**
     * The green/red marking on the chart. Under budget is green, over is red.
     */
    public function testADayUnderTheTargetIsMarkedWithinBudget(): void
    {
        $user = $this->createUserWithProfile();
        $food = $this->createFood(kcal: 100.0);
        $this->login($user);

        $this->jsonRequest('POST', '/api/diary/entries', [
            'foodId' => $food->getId(),
            'mealType' => 'lunch',
            'quantity' => 200,
            'unit' => 'g',
        ]);

        $this->jsonRequest('GET', '/api/dashboard');
        $today = $this->responseData()['today'];

        self::assertTrue($today['withinBudget']);
        self::assertGreaterThan(0, $today['remainingKcal']);
    }

    public function testADayOverTheTargetIsMarkedOverBudget(): void
    {
        $user = $this->createUserWithProfile();
        $food = $this->createFood(kcal: 500.0);
        $this->login($user);

        // 2 kg of a 500 kcal/100 g food is 10 000 kcal - comfortably over.
        $this->jsonRequest('POST', '/api/diary/entries', [
            'foodId' => $food->getId(),
            'mealType' => 'dinner',
            'quantity' => 2000,
            'unit' => 'g',
        ]);

        $this->jsonRequest('GET', '/api/dashboard');
        $today = $this->responseData()['today'];

        self::assertFalse($today['withinBudget']);
        self::assertLessThan(0, $today['remainingKcal']);
    }

    /**
     * Without body data there is no target. The dashboard has to say so rather
     * than invent a plausible-looking default the user would never question.
     */
    public function testAUserWithoutAProfileGetsNoTargetRatherThanAGuess(): void
    {
        $this->login($this->createUser('noprofile@example.test'));

        $this->jsonRequest('GET', '/api/dashboard');
        $today = $this->responseData()['today'];

        self::assertNull($today['target']);
        self::assertNull($today['budgetKcal']);
        self::assertNull($today['withinBudget']);
    }

    public function testTheTargetAppearsOnceTheProfileIsFilledIn(): void
    {
        $this->login($this->createUserWithProfile());

        $this->jsonRequest('GET', '/api/dashboard');
        $today = $this->responseData()['today'];

        self::assertNotNull($today['target']);
        self::assertGreaterThan(1000, $today['target']['targetKcal']);
    }

    /**
     * Averaging over days with no entries would drag the number towards zero
     * and tell the user they eat far less than they do.
     */
    public function testAveragesCountOnlyTheDaysThatHaveEntries(): void
    {
        $user = $this->createUserWithProfile();
        $food = $this->createFood(kcal: 100.0);
        $this->login($user);

        $this->jsonRequest('POST', '/api/diary/entries', [
            'foodId' => $food->getId(),
            'mealType' => 'lunch',
            'quantity' => 1000,
            'unit' => 'g',
        ]);

        $this->jsonRequest('GET', '/api/dashboard?days=7');
        $averages = $this->responseData()['averages'];

        self::assertSame(1, $averages['daysLogged']);
        self::assertEqualsWithDelta(1000.0, $averages['kcal'], 0.01);
    }

    public function testAveragesAreNullWhenNothingHasEverBeenLogged(): void
    {
        $this->login($this->createUserWithProfile());

        $this->jsonRequest('GET', '/api/dashboard');
        $averages = $this->responseData()['averages'];

        self::assertSame(0, $averages['daysLogged']);
        self::assertNull($averages['kcal']);
    }

    /**
     * Imported history is the case this exists for: months of diary, then a
     * single weigh-in today. Every one of those days predates the weigh-in, and
     * without a fallback the whole chart comes back grey.
     */
    public function testDaysThatPredateEveryWeighInStillGetATarget(): void
    {
        $user = $this->createUserWithProfile('imported@example.test');
        $food = $this->createFood(kcal: 100.0);
        $this->login($user);

        // createUserWithProfile weighs in today; this day is well before that.
        $this->jsonRequest('POST', '/api/diary/entries', [
            'foodId' => $food->getId(),
            'mealType' => 'lunch',
            'quantity' => 500,
            'unit' => 'g',
            'loggedOn' => (new \DateTimeImmutable('today'))->modify('-20 days')->format('Y-m-d'),
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->jsonRequest('GET', '/api/dashboard?days=30');

        $history = $this->responseData()['history'];
        $day = null;

        foreach ($history as $candidate) {
            if ($candidate['consumed']['kcal'] > 0) {
                $day = $candidate;
            }
        }

        self::assertNotNull($day, 'The logged day should be in the history.');
        self::assertNotNull($day['target'], 'A day before the first weigh-in should still get a target.');
        self::assertTrue($day['withinBudget'], '500 kcal is inside any plausible budget.');
    }

    /**
     * The fallback must not override a weigh-in that genuinely applies: a day
     * with an earlier measurement uses that one, not the first on record.
     */
    public function testADayUsesTheWeighInThatAppliedRatherThanTheEarliest(): void
    {
        $user = $this->createUserWithProfile('weighins@example.test');
        $this->login($user);

        // Two weigh-ins: a light one long ago, a much heavier one last week.
        $this->jsonRequest('POST', '/api/me/measurements', [
            'weightKg' => 60.0,
            'measuredOn' => (new \DateTimeImmutable('today'))->modify('-60 days')->format('Y-m-d'),
        ]);
        self::assertResponseIsSuccessful();

        $this->jsonRequest('POST', '/api/me/measurements', [
            'weightKg' => 110.0,
            'measuredOn' => (new \DateTimeImmutable('today'))->modify('-7 days')->format('Y-m-d'),
        ]);
        self::assertResponseIsSuccessful();

        $this->jsonRequest('GET', '/api/dashboard?days=3');
        $recent = $this->responseData()['history'][0]['target'];

        self::assertNotNull($recent);

        // A heavier body burns more, so the recent target has to be the larger
        // of the two - proving the 110 kg weigh-in won, not the 60 kg one.
        self::assertGreaterThan(2000, $recent['targetKcal']);
    }

    public function testOneUsersDashboardNeverIncludesAnothersEntries(): void
    {
        $owner = $this->createUserWithProfile('dash-owner@example.test');
        $food = $this->createFood(kcal: 400.0);
        $this->login($owner);

        $this->jsonRequest('POST', '/api/diary/entries', [
            'foodId' => $food->getId(),
            'mealType' => 'lunch',
            'quantity' => 500,
            'unit' => 'g',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->login($this->createUserWithProfile('dash-other@example.test'));
        $this->jsonRequest('GET', '/api/dashboard');

        self::assertEqualsWithDelta(0.0, $this->responseData()['today']['consumed']['kcal'], 0.01);
    }
}
