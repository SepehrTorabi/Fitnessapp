<?php

declare(strict_types=1);

namespace App\Api\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request DTOs are plain readonly objects carrying their own constraints.
 *
 * Controllers receive them through #[MapRequestPayload], which deserialises the
 * JSON body, validates it and answers 422 with the violations before the
 * controller method ever runs - so the controller only ever sees valid input and
 * needs no manual checking.
 */
final readonly class RegistrationRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Please enter your e-mail address.')]
        #[Assert\Email(message: 'This is not a valid e-mail address.')]
        #[Assert\Length(max: 180)]
        public string $email = '',

        #[Assert\NotBlank(message: 'Please choose a password.')]
        #[Assert\Length(
            min: 10,
            max: 4096,
            minMessage: 'Your password must be at least {{ limit }} characters long.',
        )]
        #[Assert\NotCompromisedPassword(
            message: 'This password has appeared in a public data breach. Please choose a different one.',
            skipOnError: true,
        )]
        public string $password = '',

        #[Assert\NotBlank(message: 'Please enter a display name.')]
        #[Assert\Length(min: 2, max: 80)]
        public string $displayName = '',
    ) {
    }
}
