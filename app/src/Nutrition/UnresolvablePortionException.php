<?php

declare(strict_types=1);

namespace App\Nutrition;

/**
 * Thrown when an amount cannot honestly be turned into grams, e.g. "2 slices"
 * of a food that has never had a slice weight recorded.
 */
final class UnresolvablePortionException extends \RuntimeException
{
}
