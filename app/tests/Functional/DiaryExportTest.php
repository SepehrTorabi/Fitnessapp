<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Export\DiaryPdfExporter;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\HttpFoundation\Response;

#[CoversNothing]
final class DiaryExportTest extends ApiTestCase
{
    public function testTheExportRequiresAuthentication(): void
    {
        $this->jsonRequest('GET', '/api/me/export/diary.pdf');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testItReturnsAPdfAsAnAttachment(): void
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

        $this->client->request('GET', '/api/me/export/diary.pdf');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');

        $disposition = $this->client->getResponse()->headers->get('Content-Disposition');
        self::assertIsString($disposition);
        self::assertStringContainsString('attachment', $disposition);
        self::assertStringContainsString('.pdf', $disposition);

        $body = (string) $this->client->getResponse()->getContent();
        self::assertStringStartsWith('%PDF-', $body, 'The body should be a PDF document.');
        self::assertGreaterThan(1000, \strlen($body));
    }

    /**
     * A user with nothing logged still gets a valid document rather than an
     * error - the button is always there, and a blank diary is a legitimate
     * thing to print.
     */
    public function testAUserWithNothingLoggedStillGetsAValidPdf(): void
    {
        $this->login($this->createUser('empty@example.test'));

        $this->client->request('GET', '/api/me/export/diary.pdf');

        self::assertResponseIsSuccessful();
        self::assertStringStartsWith('%PDF-', (string) $this->client->getResponse()->getContent());
    }

    // --- What goes into the report ----------------------------------------

    /**
     * The point of the feature: days the user never logged must not appear as a
     * row of zeroes, because that would claim they ate nothing.
     */
    public function testOnlyDaysWithSomethingLoggedAppear(): void
    {
        $user = $this->createUserWithProfile('gaps@example.test');
        $food = $this->createFood(kcal: 200.0);
        $this->login($user);

        foreach (['2026-09-01', '2026-09-05'] as $date) {
            $this->jsonRequest('POST', '/api/diary/entries', [
                'foodId' => $food->getId(),
                'mealType' => 'lunch',
                'quantity' => 100,
                'unit' => 'g',
                'loggedOn' => $date,
            ]);
            self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        }

        $report = $this->exporter()->buildReport($this->refreshUser('gaps@example.test'));

        // Two days, not the five between them.
        self::assertSame(2, $report['dayCount']);
        self::assertSame(['2026-09-05', '2026-09-01'], array_map(
            static fn (array $d): string => $d['date']->format('Y-m-d'),
            $report['days'],
        ));
    }

    public function testDaysAreOrderedNewestFirst(): void
    {
        $user = $this->createUserWithProfile('order@example.test');
        $food = $this->createFood();
        $this->login($user);

        foreach (['2026-08-20', '2026-09-02', '2026-08-25'] as $date) {
            $this->jsonRequest('POST', '/api/diary/entries', [
                'foodId' => $food->getId(),
                'mealType' => 'lunch',
                'quantity' => 100,
                'unit' => 'g',
                'loggedOn' => $date,
            ]);
        }

        $report = $this->exporter()->buildReport($this->refreshUser('order@example.test'));

        self::assertSame(['2026-09-02', '2026-08-25', '2026-08-20'], array_map(
            static fn (array $d): string => $d['date']->format('Y-m-d'),
            $report['days'],
        ));
        self::assertSame('2026-08-20', $report['from']?->format('Y-m-d'));
        self::assertSame('2026-09-02', $report['to']?->format('Y-m-d'));
    }

    /**
     * A day with only an activity on it is still a day worth printing.
     */
    public function testADayWithOnlyAnActivityIsIncluded(): void
    {
        $this->login($this->createUserWithProfile('activeonly@example.test'));

        $this->jsonRequest('POST', '/api/diary/activities', [
            'description' => 'Training',
            'caloriesBurned' => 800,
            'performedOn' => '2026-09-03',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $report = $this->exporter()->buildReport($this->refreshUser('activeonly@example.test'));

        self::assertSame(1, $report['dayCount']);
        self::assertEqualsWithDelta(800.0, $report['days'][0]['burned'], 0.01);
        self::assertSame('training', $report['days'][0]['badge']);
    }

    /**
     * "Trainingstag" for a deliberate workout, "Aktiv" for anything else that
     * burned calories, nothing at all for a day that burned none.
     */
    public function testTheBadgeDistinguishesTrainingFromMerelyActive(): void
    {
        $user = $this->createUserWithProfile('badges@example.test');
        $food = $this->createFood();
        $this->login($user);

        $this->jsonRequest('POST', '/api/diary/activities', [
            'description' => '8.000 Schritte',
            'caloriesBurned' => 300,
            'performedOn' => '2026-09-09',
        ]);

        $this->jsonRequest('POST', '/api/diary/entries', [
            'foodId' => $food->getId(),
            'mealType' => 'lunch',
            'quantity' => 100,
            'unit' => 'g',
            'loggedOn' => '2026-09-08',
        ]);

        $report = $this->exporter()->buildReport($this->refreshUser('badges@example.test'));

        $badges = [];

        foreach ($report['days'] as $day) {
            $badges[$day['date']->format('Y-m-d')] = $day['badge'];
        }

        self::assertSame('active', $badges['2026-09-09']);
        self::assertNull($badges['2026-09-08'], 'A day with no activity carries no badge.');
    }

    /**
     * Intake averages over the days that have food; the burn average over the
     * days that have activity. Mixing the two denominators would make a rest day
     * look like a light training day.
     */
    public function testAveragesUseTheRightDenominators(): void
    {
        $user = $this->createUserWithProfile('avg@example.test');
        $food = $this->createFood(kcal: 500.0);
        $this->login($user);

        // Two days of food, one of which also has an activity.
        foreach (['2026-09-01', '2026-09-02'] as $date) {
            $this->jsonRequest('POST', '/api/diary/entries', [
                'foodId' => $food->getId(),
                'mealType' => 'lunch',
                'quantity' => 200,
                'unit' => 'g',
                'loggedOn' => $date,
            ]);
        }

        $this->jsonRequest('POST', '/api/diary/activities', [
            'description' => 'Training',
            'caloriesBurned' => 600,
            'performedOn' => '2026-09-02',
        ]);

        $report = $this->exporter()->buildReport($this->refreshUser('avg@example.test'));

        // 1000 kcal on each of two days.
        self::assertEqualsWithDelta(1000.0, $report['averages']['kcal'] ?? 0.0, 0.01);

        // 600 burned on the one day that had any - not 300 spread over both.
        self::assertEqualsWithDelta(600.0, $report['averages']['burned'] ?? 0.0, 0.01);

        // Net is the whole period's intake minus its burn, over the food days.
        self::assertEqualsWithDelta(700.0, $report['averages']['net'] ?? 0.0, 0.01);
    }

    public function testOneUsersExportNeverContainsAnothersDays(): void
    {
        $owner = $this->createUserWithProfile('mine@example.test');
        $food = $this->createFood();
        $this->login($owner);

        $this->jsonRequest('POST', '/api/diary/entries', [
            'foodId' => $food->getId(),
            'mealType' => 'lunch',
            'quantity' => 100,
            'unit' => 'g',
            'loggedOn' => '2026-09-07',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->createUserWithProfile('theirs@example.test');

        $report = $this->exporter()->buildReport($this->refreshUser('theirs@example.test'));

        self::assertSame(0, $report['dayCount']);
    }

    public function testTheFilenameCarriesTheNameAndThePeriod(): void
    {
        $user = $this->createUserWithProfile('named@example.test', );
        $food = $this->createFood();
        $this->login($user);

        $this->jsonRequest('POST', '/api/diary/entries', [
            'foodId' => $food->getId(),
            'mealType' => 'lunch',
            'quantity' => 100,
            'unit' => 'g',
            'loggedOn' => '2026-09-07',
        ]);

        $filename = $this->exporter()->filenameFor($this->refreshUser('named@example.test'));

        self::assertStringContainsString('2026-09-07', $filename);
        self::assertStringEndsWith('.pdf', $filename);
    }

    private function exporter(): DiaryPdfExporter
    {
        return static::getContainer()->get(DiaryPdfExporter::class);
    }

    /**
     * Symfony resets its services between requests in the test client, which
     * clears the EntityManager - so a User held from before the requests is
     * detached by now and has to be fetched again.
     */
    private function refreshUser(string $email): \App\Entity\User
    {
        $user = static::getContainer()->get(\App\Repository\UserRepository::class)->findOneByEmail($email);
        self::assertNotNull($user);

        return $user;
    }
}
