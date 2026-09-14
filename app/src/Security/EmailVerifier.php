<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\EmailVerificationToken;
use App\Entity\User;
use App\Repository\EmailVerificationTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Issues and redeems the account-confirmation links.
 *
 * The link points at the frontend, not at this API: the user lands on a page of
 * the SPA, which posts the token back to us. A link that resolved directly to a
 * JSON endpoint would show the user a raw API response in their browser.
 *
 * Only a SHA-256 hash of each token is stored. The plaintext lives in exactly
 * one place - the e-mail sent to the address being confirmed.
 */
final class EmailVerifier
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EmailVerificationTokenRepository $tokens,
        private readonly MailerInterface $mailer,
        private readonly string $frontendVerifyUrl,
        private readonly string $senderAddress,
        private readonly string $senderName,
        private readonly int $tokenLifetimeSeconds,
    ) {
    }

    /**
     * Retire any previous link, issue a fresh one and mail it.
     */
    public function sendVerificationEmail(User $user): void
    {
        // Resending must invalidate the earlier mail, or every confirmation link
        // ever sent stays usable for its whole lifetime.
        $this->tokens->consumeAllFor($user);

        $plainToken = bin2hex(random_bytes(32));

        $token = new EmailVerificationToken(
            $user,
            hash('sha256', $plainToken),
            new \DateTimeImmutable(\sprintf('+%d seconds', $this->tokenLifetimeSeconds)),
        );

        $this->entityManager->persist($token);
        $this->entityManager->flush();

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderAddress, $this->senderName))
            ->to(new Address($user->getEmail(), $user->getDisplayName()))
            ->subject('Confirm your Fitnessapp account')
            ->htmlTemplate('email/verify_account.html.twig')
            ->locale('en')
            ->context([
                'displayName' => $user->getDisplayName(),
                'verifyUrl' => $this->buildVerifyUrl($plainToken),
                'expiresInHours' => (int) round($this->tokenLifetimeSeconds / 3600),
            ]);

        $this->mailer->send($email);
    }

    /**
     * Redeem a token and activate the account behind it.
     *
     * @throws InvalidVerificationTokenException
     */
    public function verify(string $plainToken): User
    {
        $token = $this->tokens->findOneByHash(hash('sha256', $plainToken));

        if (null === $token || !$token->isUsable()) {
            throw new InvalidVerificationTokenException();
        }

        $token->consume();
        $user = $token->getUser();
        $user->markVerified();

        $this->entityManager->flush();

        return $user;
    }

    private function buildVerifyUrl(string $plainToken): string
    {
        $separator = str_contains($this->frontendVerifyUrl, '?') ? '&' : '?';

        return $this->frontendVerifyUrl.$separator.http_build_query(['token' => $plainToken]);
    }
}
