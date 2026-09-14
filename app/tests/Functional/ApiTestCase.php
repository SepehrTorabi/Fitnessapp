<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\BodyMeasurement;
use App\Entity\Food;
use App\Entity\FoodPortion;
use App\Entity\User;
use App\Entity\UserProfile;
use App\Enum\ActivityLevel;
use App\Enum\FoodSource;
use App\Enum\Goal;
use App\Enum\Sex;
use App\Nutrition\Nutrients;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Shared setup for the API tests.
 *
 * Every test gets a fresh client and runs inside a transaction that is rolled
 * back afterwards (DAMADoctrineTestBundle), so tests cannot see each other's
 * rows and the schema is built once for the whole suite.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected const string PASSWORD = 'a-long-enough-test-passphrase';

    protected KernelBrowser $client;
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // By default the browser reboots the kernel between requests, which
        // throws away the container. These tests routinely make several
        // requests in a row - log in, then act - and some replace a service
        // with a stub beforehand, so the container has to survive. Isolation
        // between tests still holds: each test builds its own client, and the
        // database is rolled back afterwards.
        $this->client->disableReboot();

        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * A user who has confirmed their address and can therefore sign in.
     */
    protected function createUser(
        string $email = 'test@example.test',
        bool $verified = true,
        string $displayName = 'Test User',
    ): User {
        $user = new User($email, $displayName);
        $user->setPassword(
            static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, self::PASSWORD),
        );

        if ($verified) {
            $user->markVerified();
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    /**
     * A user with the body data needed for a calorie target to exist.
     */
    protected function createUserWithProfile(string $email = 'profiled@example.test'): User
    {
        $user = $this->createUser($email);

        $profile = new UserProfile(
            $user,
            new \DateTimeImmutable('1990-01-01'),
            Sex::Male,
            180.0,
            ActivityLevel::ModeratelyActive,
            Goal::MaintainWeight,
        );
        $user->setProfile($profile);
        $this->entityManager->persist($profile);

        $measurement = new BodyMeasurement($user, new \DateTimeImmutable('today'), 80.0);
        $user->addBodyMeasurement($measurement);
        $this->entityManager->persist($measurement);

        $this->entityManager->flush();

        return $user;
    }

    /**
     * Sign in through the real login endpoint rather than through loginUser(),
     * so the firewall, the user checker and the session cookie are all part of
     * what the test exercises.
     */
    protected function login(User $user): void
    {
        $this->jsonRequest('POST', '/api/auth/login', [
            'email' => $user->getEmail(),
            'password' => self::PASSWORD,
        ]);

        self::assertResponseIsSuccessful('Logging the test user in should succeed.');
    }

    protected function createFood(
        string $name = 'Wholegrain bread',
        float $kcal = 250.0,
        ?User $owner = null,
        float $density = 1.0,
    ): Food {
        $food = new Food($name, new Nutrients($kcal, 10.0, 40.0, 5.0), FoodSource::Seed);
        $food->setDensityGPerMl($density);
        $food->setCreatedBy($owner);
        $food->addPortion(new FoodPortion($food, 'slice', 50.0));

        $this->entityManager->persist($food);
        $this->entityManager->flush();

        return $food;
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    protected function jsonRequest(string $method, string $uri, ?array $payload = null): void
    {
        $this->client->request(
            $method,
            $uri,
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: null === $payload ? null : json_encode($payload, \JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function responseData(): array
    {
        $content = $this->client->getResponse()->getContent();

        self::assertIsString($content);

        $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        self::assertIsArray($decoded, 'The endpoint should have answered with a JSON object.');

        return $decoded;
    }
}
