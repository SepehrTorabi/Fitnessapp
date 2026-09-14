<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\Dto\MeasurementRequest;
use App\Api\Dto\PreferencesRequest;
use App\Api\Dto\ProfileRequest;
use App\Api\Presenter\UserPresenter;
use App\Entity\BodyMeasurement;
use App\Entity\User;
use App\Entity\UserPreferences;
use App\Entity\UserProfile;
use App\Repository\BodyMeasurementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * The signed-in user's own account: who they are, their body data and the
 * calorie target that follows from it.
 */
final class AccountController extends ApiController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BodyMeasurementRepository $measurements,
        private readonly UserPresenter $userPresenter,
    ) {
    }

    /**
     * Everything the SPA needs on boot: identity, profile and today's target.
     */
    public function me(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json(['user' => $this->userPresenter->present($user)]);
    }

    /**
     * Create or replace the profile. One endpoint for both, because a profile
     * is a single record per user that either exists or does not.
     */
    public function updateProfile(
        #[CurrentUser] User $user,
        #[MapRequestPayload] ProfileRequest $payload,
    ): JsonResponse {
        $birthDate = $this->parseDate($payload->birthDate);
        $profile = $user->getProfile();

        if (null === $profile) {
            $profile = new UserProfile(
                $user,
                $birthDate,
                $payload->sex,
                $payload->heightCm,
                $payload->activityLevel,
                $payload->goal,
            );
            $user->setProfile($profile);
            $this->entityManager->persist($profile);
        } else {
            $profile->setBirthDate($birthDate);
            $profile->setSex($payload->sex);
            $profile->setHeightCm($payload->heightCm);
            $profile->setActivityLevel($payload->activityLevel);
            $profile->setGoal($payload->goal);
        }

        $this->entityManager->flush();

        return $this->json(['user' => $this->userPresenter->present($user)]);
    }

    /**
     * Record a weigh-in.
     *
     * A second entry for a day that already has one updates it rather than
     * adding a duplicate - stepping on the scale twice is not two data points.
     */
    public function addMeasurement(
        #[CurrentUser] User $user,
        #[MapRequestPayload] MeasurementRequest $payload,
    ): JsonResponse {
        $date = $this->parseDate($payload->measuredOn);
        $measurement = $this->measurements->findOneForUserOn($user, $date);

        if (null === $measurement) {
            $measurement = new BodyMeasurement(
                $user,
                $date,
                $payload->weightKg,
                $payload->muscleMassKg,
                $payload->fatMassKg,
            );
            $user->addBodyMeasurement($measurement);
            $this->entityManager->persist($measurement);
        } else {
            $measurement->setWeightKg($payload->weightKg);
            $measurement->setMuscleMassKg($payload->muscleMassKg);
            $measurement->setFatMassKg($payload->fatMassKg);
        }

        $this->entityManager->flush();

        return $this->json([
            'measurement' => $this->userPresenter->presentMeasurement($measurement),
            'user' => $this->userPresenter->present($user),
        ]);
    }

    /**
     * Change the language, the colour scheme, or both.
     *
     * Fields left out are left alone, so the settings screen can send one
     * change at a time without having to resend the whole object.
     */
    public function updatePreferences(
        #[CurrentUser] User $user,
        #[MapRequestPayload] PreferencesRequest $payload,
    ): JsonResponse {
        $preferences = $user->getPreferences();

        if (null === $preferences) {
            $preferences = new UserPreferences($user);
            $user->setPreferences($preferences);
            $this->entityManager->persist($preferences);
        }

        if (null !== $payload->locale) {
            $preferences->setLocale($payload->locale);
        }

        if (null !== $payload->theme) {
            $preferences->setTheme($payload->theme);
        }

        $this->entityManager->flush();

        return $this->json(['user' => $this->userPresenter->present($user)]);
    }

    /**
     * The weight history, for the progress chart.
     */
    public function listMeasurements(#[CurrentUser] User $user): JsonResponse
    {
        $to = new \DateTimeImmutable('today');
        $from = $to->modify('-365 days');

        return $this->json([
            'measurements' => array_map(
                fn (BodyMeasurement $m): array => $this->userPresenter->presentMeasurement($m),
                $this->measurements->findForUserBetween($user, $from, $to),
            ),
        ]);
    }
}
