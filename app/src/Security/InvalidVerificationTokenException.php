<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Raised when a confirmation link is unknown, already used or past its lifetime.
 *
 * The three cases are deliberately one exception with one message: telling an
 * anonymous caller which token strings exist but have expired would turn the
 * endpoint into an oracle for guessing them.
 */
final class InvalidVerificationTokenException extends \RuntimeException
{
    public function __construct(string $message = 'This confirmation link is invalid or has expired.')
    {
        parent::__construct($message);
    }
}
