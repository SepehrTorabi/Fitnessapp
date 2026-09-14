<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Refuses a login for an account whose e-mail address has not been confirmed.
 *
 * This runs as part of authentication rather than as a check inside the login
 * controller, so it applies to every present and future authenticator - the
 * session login today, JWT later - without anyone having to remember it.
 */
final class AccountStatusChecker implements UserCheckerInterface
{
    /**
     * Runs before the credentials are examined.
     */
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isVerified()) {
            throw new CustomUserMessageAccountStatusException(
                'Please confirm your e-mail address before signing in. Check your inbox for the confirmation link.'
            );
        }
    }

    /**
     * Runs after the credentials have been accepted.
     */
    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
    }
}
