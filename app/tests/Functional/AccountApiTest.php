<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\HttpFoundation\Response;

#[CoversNothing]
final class AccountApiTest extends ApiTestCase
{
    public function testSettingTheProfileProducesACalorieTarget(): void
    {
        $user = $this->createUser();
        $this->login($user);

        $this->jsonRequest('PUT', '/api/me/profile', [
            'birthDate' => '1995-04-12',
            'sex' => 'male',
            'heightCm' => 182,
            'activityLevel' => 'moderately_active',
            'goal' => 'lose_weight',
        ]);
        self::assertResponseIsSuccessful();

        // Still no target: a profile without a weight cannot produce one.
        self::assertNull($this->responseData()['user']['dailyTarget']);

        $this->jsonRequest('POST', '/api/me/measurements', ['weightKg' => 84.5]);
        self::assertResponseIsSuccessful();

        $target = $this->responseData()['user']['dailyTarget'];
        self::assertNotNull($target);
        self::assertSame('mifflin-st-jeor', $target['formula']);
    }

    /**
     * Body fat switches the calculation to the lean-mass formula.
     */
    public function testAFatMassMeasurementSwitchesToKatchMcArdle(): void
    {
        $this->login($this->createUser());

        $this->jsonRequest('PUT', '/api/me/profile', [
            'birthDate' => '1995-04-12',
            'sex' => 'male',
            'heightCm' => 182,
            'activityLevel' => 'moderately_active',
            'goal' => 'lose_weight',
        ]);

        $this->jsonRequest('POST', '/api/me/measurements', [
            'weightKg' => 84.5,
            'fatMassKg' => 16.9,
        ]);

        $data = $this->responseData();
        self::assertEqualsWithDelta(67.6, $data['measurement']['leanBodyMassKg'], 0.01);
        self::assertSame('katch-mcardle', $data['user']['dailyTarget']['formula']);

        // 370 + 21.6 * 67.6 = 1830.16
        self::assertEqualsWithDelta(1830.0, $data['user']['dailyTarget']['bmr'], 1.0);
    }

    /**
     * Stepping on the scale twice in one day is one data point, not two.
     */
    public function testASecondMeasurementOnTheSameDayReplacesTheFirst(): void
    {
        $this->login($this->createUser());

        $this->jsonRequest('POST', '/api/me/measurements', ['weightKg' => 84.5]);
        $firstId = $this->responseData()['measurement']['id'];

        $this->jsonRequest('POST', '/api/me/measurements', ['weightKg' => 83.9]);
        $data = $this->responseData();

        self::assertSame($firstId, $data['measurement']['id']);
        self::assertEqualsWithDelta(83.9, $data['measurement']['weightKg'], 0.01);

        $this->jsonRequest('GET', '/api/me/measurements');
        self::assertCount(1, $this->responseData()['measurements']);
    }

    public function testMeasurementsOnDifferentDaysAreKeptSeparately(): void
    {
        $this->login($this->createUser());

        $this->jsonRequest('POST', '/api/me/measurements', ['weightKg' => 85.0, 'measuredOn' => '2026-09-01']);
        $this->jsonRequest('POST', '/api/me/measurements', ['weightKg' => 84.0, 'measuredOn' => '2026-09-08']);

        $this->jsonRequest('GET', '/api/me/measurements');

        self::assertCount(2, $this->responseData()['measurements']);
    }

    public function testTheProfileCanBeUpdated(): void
    {
        $this->login($this->createUser());

        $this->jsonRequest('PUT', '/api/me/profile', [
            'birthDate' => '1995-04-12',
            'sex' => 'male',
            'heightCm' => 182,
            'activityLevel' => 'sedentary',
            'goal' => 'maintain_weight',
        ]);
        self::assertResponseIsSuccessful();

        $this->jsonRequest('PUT', '/api/me/profile', [
            'birthDate' => '1995-04-12',
            'sex' => 'male',
            'heightCm' => 182,
            'activityLevel' => 'very_active',
            'goal' => 'gain_muscle',
        ]);

        $profile = $this->responseData()['user']['profile'];
        self::assertSame('very_active', $profile['activityLevel']);
        self::assertSame('gain_muscle', $profile['goal']);
    }

    public function testAnImplausibleHeightIsRejected(): void
    {
        $this->login($this->createUser());

        $this->jsonRequest('PUT', '/api/me/profile', [
            'birthDate' => '1995-04-12',
            'sex' => 'male',
            'heightCm' => 25,
            'activityLevel' => 'sedentary',
            'goal' => 'maintain_weight',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testAnUnknownActivityLevelIsRejected(): void
    {
        $this->login($this->createUser());

        $this->jsonRequest('PUT', '/api/me/profile', [
            'birthDate' => '1995-04-12',
            'sex' => 'male',
            'heightCm' => 182,
            'activityLevel' => 'hyperactive',
            'goal' => 'maintain_weight',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testAgeIsDerivedFromTheDateOfBirth(): void
    {
        $this->login($this->createUser());

        $thirtyYearsAgo = (new \DateTimeImmutable('today'))->modify('-30 years');

        $this->jsonRequest('PUT', '/api/me/profile', [
            'birthDate' => $thirtyYearsAgo->format('Y-m-d'),
            'sex' => 'female',
            'heightCm' => 165,
            'activityLevel' => 'sedentary',
            'goal' => 'maintain_weight',
        ]);

        self::assertSame(30, $this->responseData()['user']['profile']['age']);
    }

    public function testTheProfileEndpointRequiresAuthentication(): void
    {
        $this->jsonRequest('GET', '/api/me');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testOneUserCannotSeeAnothersMeasurements(): void
    {
        $this->login($this->createUser('m-owner@example.test'));
        $this->jsonRequest('POST', '/api/me/measurements', ['weightKg' => 84.5]);
        self::assertResponseIsSuccessful();

        $this->login($this->createUser('m-other@example.test'));
        $this->jsonRequest('GET', '/api/me/measurements');

        self::assertCount(0, $this->responseData()['measurements']);
    }
}
