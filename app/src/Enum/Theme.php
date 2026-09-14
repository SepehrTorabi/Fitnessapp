<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Which colour scheme the interface should use.
 *
 * Three states, not two. "System" is a real choice and the right default: it
 * follows the operating system, so the app turns dark in the evening along with
 * everything else, and a user who never opens the settings still gets what they
 * expect. Light and dark are explicit overrides of that.
 */
enum Theme: string
{
    case System = 'system';
    case Light = 'light';
    case Dark = 'dark';

    public static function default(): self
    {
        return self::System;
    }
}
