<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\Dto\RegistrationRequest;
use App\Api\Dto\ResendVerificationRequest;
use App\Api\Dto\VerifyEmailRequest;
use App\Api\Presenter\UserPresenter;
use App\Entity\User;
use App\Entity\UserPreferences;
use App\Enum\AppLocale;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use App\Security\InvalidVerificationTokenException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Registration and e-mail confirmation.
 *
 * Signing in and out is not here: those two routes are handled by the firewall
 * itself (json_login and logout in security.yaml), with the JSON responses
 * produced by {@see \App\Security\ApiAuthenticationHandler}.
 */
final class AuthController extends ApiController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EmailVerifier $emailVerifier,
        private readonly UserPresenter $userPresenter,
        private readonly RateLimiterFactoryInterface $registrationLimiter,
        private readonly RateLimiterFactoryInterface $verificationResendLimiter,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Never executed.
     *
     * The json_login authenticator on the firewall intercepts this path before
     * routing reaches a controller. The route still has to exist, because
     * check_path in security.yaml is matched against the route table.
     */
    public function login(): never
    {
        throw new \LogicException('This endpoint is handled by the security firewall (json_login).');
    }

    /**
     * Never executed either - the firewall's logout listener handles this path,
     * and LogoutSubscriber turns the result into JSON.
     */
    public function logout(): never
    {
        throw new \LogicException('This endpoint is handled by the security firewall (logout).');
    }

    /**
     * Create an account and send the confirmation mail.
     *
     * The account exists immediately but cannot sign in until the address is
     * confirmed - see {@see \App\Security\AccountStatusChecker}.
     */
    public function register(
        #[MapRequestPayload] RegistrationRequest $payload,
        Request $request,
    ): JsonResponse {
        $limit = $this->registrationLimiter->create($request->getClientIp())->consume();

        if (!$limit->isAccepted()) {
            return $this->error(
                'too_many_requests',
                'Too many accounts created from this address. Please try again later.',
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        $email = mb_strtolower(trim($payload->email));

        // Telling an anonymous caller that an address is taken does leak which
        // addresses are registered. The alternative - always answering "check
        // your inbox" - leaves a user who simply forgot they signed up with no
        // way to find out. For a personal fitness diary the clear message is
        // worth more than hiding the membership list; revisit if that changes.
        if (null !== $this->users->findOneByEmail($email)) {
            return $this->error(
                'email_taken',
                'An account with this e-mail address already exists. Try signing in instead.',
                Response::HTTP_CONFLICT,
            );
        }

        $user = new User($email, trim($payload->displayName));
        $user->setPassword($this->passwordHasher->hashPassword($user, $payload->password));

        // Settle the language now rather than after the first sign-in: the
        // confirmation mail goes out in the next few lines, and it is the first
        // thing the user ever reads from us. What the sign-up form was shown in
        // beats the browser header, which beats English.
        $preferences = new UserPreferences(
            $user,
            $payload->locale
                ?? AppLocale::fromAcceptLanguage($request->headers->get('Accept-Language'))
                ?? AppLocale::default(),
        );
        $user->setPreferences($preferences);

        $this->entityManager->persist($user);
        $this->entityManager->persist($preferences);
        $this->entityManager->flush();

        $this->emailVerifier->sendVerificationEmail($user);

        return $this->json([
            'message' => 'Account created. Check your inbox for the confirmation link.',
            'user' => $this->userPresenter->present($user),
        ], Response::HTTP_CREATED);
    }

    /**
     * Redeem a confirmation link and activate the account.
     */
    public function verifyEmail(#[MapRequestPayload] VerifyEmailRequest $payload): JsonResponse
    {
        try {
            $user = $this->emailVerifier->verify($payload->token);
        } catch (InvalidVerificationTokenException $e) {
            return $this->error('invalid_token', $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'message' => 'Your e-mail address is confirmed. You can sign in now.',
            'user' => $this->userPresenter->present($user),
        ]);
    }

    /**
     * Send the confirmation mail again.
     *
     * Always answers the same way, whether or not the address belongs to an
     * account. This endpoint takes an e-mail address and no credentials, so a
     * distinguishable response would turn it into a membership check.
     */
    public function resendVerification(#[MapRequestPayload] ResendVerificationRequest $payload): JsonResponse
    {
        $email = mb_strtolower(trim($payload->email));
        $genericResponse = $this->json([
            'message' => 'If that address belongs to an unconfirmed account, a new confirmation link is on its way.',
        ]);

        $limit = $this->verificationResendLimiter->create($email)->consume();

        if (!$limit->isAccepted()) {
            return $this->error(
                'too_many_requests',
                'A confirmation link was sent recently. Please wait before asking for another.',
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        $user = $this->users->findOneByEmail($email);

        if (null === $user || $user->isVerified()) {
            return $genericResponse;
        }

        try {
            $this->emailVerifier->sendVerificationEmail($user);
        } catch (\Throwable $e) {
            // Still answer normally: a failure here must not become a signal
            // that the address exists.
            $this->logger->error('Could not resend the confirmation mail: {message}', [
                'message' => $e->getMessage(),
            ]);
        }

        return $genericResponse;
    }
}
