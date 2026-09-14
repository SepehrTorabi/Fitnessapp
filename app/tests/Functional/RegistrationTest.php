<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\EmailVerificationToken;
use App\Repository\UserRepository;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\HttpFoundation\Response;

/**
 * The registration and confirmation flow, end to end over HTTP.
 */
#[CoversNothing]
final class RegistrationTest extends ApiTestCase
{
    public function testRegisteringCreatesAnUnverifiedAccountAndSendsAMail(): void
    {
        $this->jsonRequest('POST', '/api/auth/register', [
            'email' => 'newcomer@example.test',
            'password' => 'a-long-enough-test-passphrase',
            'displayName' => 'Newcomer',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertFalse($this->responseData()['user']['verified']);
        self::assertEmailCount(1);

        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertStringContainsString('Confirm your Fitnessapp account', $email->getSubject() ?? '');
    }

    public function testTheConfirmationLinkActivatesTheAccount(): void
    {
        $this->jsonRequest('POST', '/api/auth/register', [
            'email' => 'confirms@example.test',
            'password' => 'a-long-enough-test-passphrase',
            'displayName' => 'Confirms',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->jsonRequest('POST', '/api/auth/verify-email', ['token' => $this->tokenFromTheMail()]);

        self::assertResponseIsSuccessful();
        self::assertTrue($this->responseData()['user']['verified']);
    }

    public function testAnUnconfirmedAccountCannotSignIn(): void
    {
        $user = $this->createUser('unconfirmed@example.test', verified: false);

        $this->jsonRequest('POST', '/api/auth/login', [
            'email' => $user->getEmail(),
            'password' => self::PASSWORD,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertStringContainsString('confirm your e-mail', strtolower($this->responseData()['message']));
    }

    public function testAConfirmedAccountCanSignInAndReadItsOwnProfile(): void
    {
        $user = $this->createUser('confirmed@example.test');

        $this->login($user);
        $this->jsonRequest('GET', '/api/me');

        self::assertResponseIsSuccessful();
        self::assertSame('confirmed@example.test', $this->responseData()['user']['email']);
    }

    /**
     * A confirmation link is single-use. Leaving it valid would turn an old mail
     * in an archived inbox into a standing key to the account.
     */
    public function testAConfirmationTokenCannotBeUsedTwice(): void
    {
        $this->jsonRequest('POST', '/api/auth/register', [
            'email' => 'replay@example.test',
            'password' => 'a-long-enough-test-passphrase',
            'displayName' => 'Replay',
        ]);

        $token = $this->tokenFromTheMail();

        $this->jsonRequest('POST', '/api/auth/verify-email', ['token' => $token]);
        self::assertResponseIsSuccessful();

        $this->jsonRequest('POST', '/api/auth/verify-email', ['token' => $token]);
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertSame('invalid_token', $this->responseData()['error']);
    }

    public function testAnExpiredTokenIsRefused(): void
    {
        $user = $this->createUser('expired@example.test', verified: false);

        // A token that was valid yesterday.
        $plain = bin2hex(random_bytes(32));
        $this->entityManager->persist(new EmailVerificationToken(
            $user,
            hash('sha256', $plain),
            new \DateTimeImmutable('-1 hour'),
        ));
        $this->entityManager->flush();

        $this->jsonRequest('POST', '/api/auth/verify-email', ['token' => $plain]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertFalse($user->isVerified());
    }

    public function testAnUnknownTokenIsRefused(): void
    {
        $this->jsonRequest('POST', '/api/auth/verify-email', ['token' => str_repeat('f', 64)]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testRegisteringTwiceWithTheSameAddressIsRejected(): void
    {
        $this->createUser('taken@example.test');

        $this->jsonRequest('POST', '/api/auth/register', [
            'email' => 'taken@example.test',
            'password' => 'a-long-enough-test-passphrase',
            'displayName' => 'Impostor',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('email_taken', $this->responseData()['error']);
    }

    /**
     * Addresses are matched case-insensitively, or "Test@x" and "test@x" become
     * two accounts that look identical to their owner.
     */
    public function testTheAddressIsNormalisedToLowercase(): void
    {
        $this->jsonRequest('POST', '/api/auth/register', [
            'email' => 'MiXeD@Example.Test',
            'password' => 'a-long-enough-test-passphrase',
            'displayName' => 'Mixed',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame('mixed@example.test', $this->responseData()['user']['email']);

        $this->jsonRequest('POST', '/api/auth/register', [
            'email' => 'mixed@example.test',
            'password' => 'a-long-enough-test-passphrase',
            'displayName' => 'Mixed again',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    public function testAnInvalidPayloadIsRejectedWithTheViolations(): void
    {
        $this->jsonRequest('POST', '/api/auth/register', [
            'email' => 'not-an-address',
            'password' => 'short',
            'displayName' => '',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * The resend endpoint takes an address and no credentials, so its answer
     * must not reveal whether that address belongs to an account.
     */
    public function testResendingAnswersTheSameForKnownAndUnknownAddresses(): void
    {
        $this->createUser('exists@example.test', verified: false);

        $this->jsonRequest('POST', '/api/auth/resend-verification', ['email' => 'exists@example.test']);
        self::assertResponseIsSuccessful();
        $forKnown = $this->responseData();

        $this->jsonRequest('POST', '/api/auth/resend-verification', ['email' => 'nobody@example.test']);
        self::assertResponseIsSuccessful();

        self::assertSame($forKnown, $this->responseData());
    }

    /**
     * Issuing a new link has to retire the previous one, otherwise every mail
     * ever sent stays usable for its whole lifetime.
     */
    public function testResendingInvalidatesTheEarlierLink(): void
    {
        $user = $this->createUser('resend@example.test', verified: false);

        static::getContainer()->get(\App\Security\EmailVerifier::class)->sendVerificationEmail($user);
        $firstToken = $this->tokenFromTheMail();

        static::getContainer()->get(\App\Security\EmailVerifier::class)->sendVerificationEmail($user);
        $secondToken = $this->tokenFromTheMail();

        self::assertNotSame($firstToken, $secondToken);

        $this->jsonRequest('POST', '/api/auth/verify-email', ['token' => $firstToken]);
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $this->jsonRequest('POST', '/api/auth/verify-email', ['token' => $secondToken]);
        self::assertResponseIsSuccessful();
    }

    public function testTheStoredTokenIsHashedNotPlaintext(): void
    {
        $this->jsonRequest('POST', '/api/auth/register', [
            'email' => 'hashed@example.test',
            'password' => 'a-long-enough-test-passphrase',
            'displayName' => 'Hashed',
        ]);

        $plain = $this->tokenFromTheMail();
        $stored = $this->entityManager->getRepository(EmailVerificationToken::class)->findOneBy([
            'tokenHash' => hash('sha256', $plain),
        ]);

        self::assertNotNull($stored, 'The token should be findable by its hash.');
        self::assertNotSame($plain, $stored->getTokenHash());
    }

    public function testSigningOutEndsTheSession(): void
    {
        $user = $this->createUser('signsout@example.test');
        $this->login($user);

        $this->jsonRequest('POST', '/api/auth/logout');
        self::assertResponseIsSuccessful();

        $this->jsonRequest('GET', '/api/me');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testTheAccountIsFindableAfterwards(): void
    {
        $this->jsonRequest('POST', '/api/auth/register', [
            'email' => 'persisted@example.test',
            'password' => 'a-long-enough-test-passphrase',
            'displayName' => 'Persisted',
        ]);

        $user = static::getContainer()->get(UserRepository::class)->findOneByEmail('persisted@example.test');

        self::assertNotNull($user);
        self::assertSame('Persisted', $user->getDisplayName());
        self::assertNotSame('a-long-enough-test-passphrase', $user->getPassword(), 'The password must be hashed.');
    }

    /**
     * Pull the confirmation token out of the most recent mail, the same way a
     * user would by clicking the link.
     */
    private function tokenFromTheMail(): string
    {
        // getMailerMessage() hands back the *first* message collected during the
        // request; a flow that sends two needs the latest one.
        $messages = self::getMailerMessages();
        self::assertNotEmpty($messages, 'No mail was sent.');

        $email = end($messages);

        $body = $email->getHtmlBody();
        self::assertIsString($body);

        self::assertSame(1, preg_match('/token=([0-9a-f]{64})/', $body, $matches), 'No token found in the mail body.');

        return $matches[1];
    }
}
