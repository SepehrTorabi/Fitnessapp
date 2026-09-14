<?php

declare(strict_types=1);

namespace App\Api\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class VerifyEmailRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 16, max: 128)]
        public string $token = '',
    ) {
    }
}
