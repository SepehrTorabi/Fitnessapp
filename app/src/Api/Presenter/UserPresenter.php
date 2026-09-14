<?php

declare(strict_types=1);

namespace App\Api\Presenter;

use App\Entity\BodyMeasurement;
use App\Entity\User;
use App\Nutrition\DailyTargetResolver;
use App\Repository\BodyMeasurementRepository;

/**
 * Builds the JSON shape of a user.
 *
 * Presenters exist so that the shape of a response lives in one place instead of
 * being assembled ad hoc in each controller - which is how two endpoints end up
 * calling the same field `displayName` and `display_name`.
 */
final class UserPresenter
{
    public function __construct(
        private readonly BodyMeasurementRepository $measurements,
        private readonly DailyTargetResolver $targetResolver,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function present(User $user): array
    {
        $profile = $user->getProfile();
        $latest = $this->measurements->findLatestForUser($user);

        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'displayName' => $user->getDisplayName(),
            'verified' => $user->isVerified(),
            'createdAt' => $user->getCreatedAt()->format(\DATE_ATOM),
            // Null until the user fills in the onboarding form. The SPA uses
            // this to decide whether to send them to the profile page first.
            'profile' => null === $profile ? null : [
                'birthDate' => $profile->getBirthDate()->format('Y-m-d'),
                'age' => $profile->getAge(),
                'sex' => $profile->getSex()->value,
                'heightCm' => $profile->getHeightCm(),
                'activityLevel' => $profile->getActivityLevel()->value,
                'goal' => $profile->getGoal()->value,
            ],
            'latestMeasurement' => null === $latest ? null : $this->presentMeasurement($latest),
            'dailyTarget' => $this->targetResolver->resolve($user)?->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentMeasurement(BodyMeasurement $measurement): array
    {
        return [
            'id' => $measurement->getId(),
            'measuredOn' => $measurement->getMeasuredOn()->format('Y-m-d'),
            'weightKg' => $measurement->getWeightKg(),
            'muscleMassKg' => $measurement->getMuscleMassKg(),
            'fatMassKg' => $measurement->getFatMassKg(),
            'leanBodyMassKg' => $measurement->getLeanBodyMassKg(),
        ];
    }
}
