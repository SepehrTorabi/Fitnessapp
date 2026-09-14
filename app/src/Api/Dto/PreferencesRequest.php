<?php

declare(strict_types=1);

namespace App\Api\Dto;

use App\Enum\AppLocale;
use App\Enum\Theme;
/**
 * Both fields are optional so the settings screen can change one thing at a
 * time: sending only {"theme":"dark"} must not reset the language.
 */
final readonly class PreferencesRequest
{
    public function __construct(
        public ?AppLocale $locale = null,
        public ?Theme $theme = null,
    ) {
    }
}
