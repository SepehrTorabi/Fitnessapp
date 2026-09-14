<?php

declare(strict_types=1);

namespace App\Nutrition;

use App\Entity\User;
use App\Repository\BodyMeasurementRepository;

/**
 * Works out a user's calorie target for a given day from their stored body data.
 *
 * Returns null rather than a default when the data is not there: a made-up
 * "2 000 kcal" would look exactly like a real target on the dashboard, and the
 * user would never learn that they still have to fill in their profile.
 */
final class DailyTargetResolver
{
    public function __construct(
        private readonly BodyMeasurementRepository $measurements,
        private readonly CalorieCalculator $calculator,
    ) {
    }

    public function resolve(User $user, ?\DateTimeImmutable $on = null): ?CalorieTarget
    {
        $profile = $user->getProfile();

        if (null === $profile) {
            return null;
        }

        $on ??= new \DateTimeImmutable('today');

        // The weigh-in that was current on that day, so re-opening last Tuesday
        // shows the target that actually applied then - falling back to the
        // earliest one for days that predate every weigh-in, which is what
        // imported history looks like.
        $measurement = $this->measurements->findApplicableForUser($user, $on);

        if (null === $measurement) {
            return null;
        }

        return $this->calculator->calculate(
            weightKg: $measurement->getWeightKg(),
            heightCm: $profile->getHeightCm(),
            ageYears: $profile->getAge($on),
            sex: $profile->getSex(),
            activityLevel: $profile->getActivityLevel(),
            goal: $profile->getGoal(),
            leanBodyMassKg: $measurement->getLeanBodyMassKg(),
        );
    }
}
