<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Email;

#[CoversNothing]
final class PreferencesApiTest extends ApiTestCase
{
    /**
     * A user who has never opened the settings screen still has an effective
     * language and theme. The client should not have to know the defaults.
     */
    public function testANewUserGetsTheDefaultsRatherThanNull(): void
    {
        $this->login($this->createUser());
        $this->jsonRequest('GET', '/api/me');

        $preferences = $this->responseData()['user']['preferences'];

        self::assertSame('en', $preferences['locale']);
        self::assertSame('system', $preferences['theme']);
    }

    public function testTheLanguageCanBeChangedAndSurvivesTheNextSignIn(): void
    {
        $user = $this->createUser('prefs@example.test');
        $this->login($user);

        $this->jsonRequest('PUT', '/api/me/preferences', ['locale' => 'de']);
        self::assertResponseIsSuccessful();
        self::assertSame('de', $this->responseData()['user']['preferences']['locale']);

        // The whole point of storing this server-side rather than in the
        // browser: sign out, sign back in, still German.
        $this->jsonRequest('POST', '/api/auth/logout');
        $this->login($user);
        $this->jsonRequest('GET', '/api/me');

        self::assertSame('de', $this->responseData()['user']['preferences']['locale']);
    }

    public function testTheThemeCanBeChanged(): void
    {
        $this->login($this->createUser());

        $this->jsonRequest('PUT', '/api/me/preferences', ['theme' => 'dark']);

        self::assertResponseIsSuccessful();
        self::assertSame('dark', $this->responseData()['user']['preferences']['theme']);
    }

    /**
     * The settings screen changes one thing at a time, so a request carrying
     * only a theme must not quietly reset the language to its default.
     */
    public function testChangingOneSettingLeavesTheOtherAlone(): void
    {
        $this->login($this->createUser());

        $this->jsonRequest('PUT', '/api/me/preferences', ['locale' => 'de']);
        self::assertResponseIsSuccessful();

        $this->jsonRequest('PUT', '/api/me/preferences', ['theme' => 'dark']);
        self::assertResponseIsSuccessful();

        $preferences = $this->responseData()['user']['preferences'];
        self::assertSame('de', $preferences['locale']);
        self::assertSame('dark', $preferences['theme']);
    }

    public function testBothCanBeChangedAtOnce(): void
    {
        $this->login($this->createUser());

        $this->jsonRequest('PUT', '/api/me/preferences', ['locale' => 'de', 'theme' => 'light']);

        $preferences = $this->responseData()['user']['preferences'];
        self::assertSame('de', $preferences['locale']);
        self::assertSame('light', $preferences['theme']);
    }

    public function testAnUnsupportedLanguageIsRejected(): void
    {
        $this->login($this->createUser());

        $this->jsonRequest('PUT', '/api/me/preferences', ['locale' => 'fr']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testAnUnknownThemeIsRejected(): void
    {
        $this->login($this->createUser());

        $this->jsonRequest('PUT', '/api/me/preferences', ['theme' => 'neon']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testPreferencesRequireAuthentication(): void
    {
        $this->jsonRequest('PUT', '/api/me/preferences', ['theme' => 'dark']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * One user's settings must not reach another's account.
     */
    public function testPreferencesArePerUser(): void
    {
        $this->login($this->createUser('one@example.test'));
        $this->jsonRequest('PUT', '/api/me/preferences', ['locale' => 'de', 'theme' => 'dark']);
        self::assertResponseIsSuccessful();

        $this->login($this->createUser('two@example.test'));
        $this->jsonRequest('GET', '/api/me');

        $preferences = $this->responseData()['user']['preferences'];
        self::assertSame('en', $preferences['locale']);
        self::assertSame('system', $preferences['theme']);
    }

    // --- The language of the confirmation mail -----------------------------

    /**
     * Registration is the first thing a user ever reads from us, and it happens
     * before they could possibly have chosen a language. The browser's header
     * is the only signal available, so it has to be used.
     */
    public function testTheConfirmationMailFollowsTheBrowserLanguage(): void
    {
        $this->client->request(
            'POST',
            '/api/auth/register',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT_LANGUAGE' => 'de-DE,de;q=0.9,en;q=0.8',
            ],
            content: json_encode([
                'email' => 'german@example.test',
                'password' => 'a-long-enough-test-passphrase',
                'displayName' => 'Deutschsprachig',
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame('de', $this->responseData()['user']['preferences']['locale']);

        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('Bestätige dein Fitnessapp-Konto', $email->getSubject());
        self::assertStringContainsString('Willkommen, Deutschsprachig', (string) $email->getHtmlBody());
    }

    public function testAnExplicitLanguageBeatsTheBrowserHeader(): void
    {
        // The sign-up form was shown in German even though the browser asks for
        // English - what the user actually saw wins.
        $this->client->request(
            'POST',
            '/api/auth/register',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9',
            ],
            content: json_encode([
                'email' => 'explicit@example.test',
                'password' => 'a-long-enough-test-passphrase',
                'displayName' => 'Explicit',
                'locale' => 'de',
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame('de', $this->responseData()['user']['preferences']['locale']);
    }

    public function testAnUnsupportedBrowserLanguageFallsBackToEnglish(): void
    {
        $this->client->request(
            'POST',
            '/api/auth/register',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT_LANGUAGE' => 'fr-FR,fr;q=0.9',
            ],
            content: json_encode([
                'email' => 'french@example.test',
                'password' => 'a-long-enough-test-passphrase',
                'displayName' => 'Francophone',
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame('en', $this->responseData()['user']['preferences']['locale']);

        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('Confirm your Fitnessapp account', $email->getSubject());
    }

    /**
     * Changing the language later has to change the language of the mails too,
     * or a German user keeps getting English resend links.
     */
    public function testResentMailsFollowTheChosenLanguage(): void
    {
        // Verified, so it can sign in and change the setting; the verifier
        // will happily issue a fresh link either way.
        $user = $this->createUser('switcher@example.test');
        $this->login($user);
        $this->jsonRequest('PUT', '/api/me/preferences', ['locale' => 'de']);
        self::assertResponseIsSuccessful();

        // Symfony resets its services between requests in the test client,
        // which clears the EntityManager - the $user from before the requests
        // is detached by now and passing it to the verifier would make Doctrine
        // treat it as a brand new entity. Fetch it again through the container.
        $managed = static::getContainer()->get(UserRepository::class)
            ->findOneByEmail('switcher@example.test');
        self::assertNotNull($managed);

        static::getContainer()->get(EmailVerifier::class)->sendVerificationEmail($managed);

        $messages = self::getMailerMessages();
        $email = end($messages);
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('Bestätige dein Fitnessapp-Konto', $email->getSubject());
    }
}
