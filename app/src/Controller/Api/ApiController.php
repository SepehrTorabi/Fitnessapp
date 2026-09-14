<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Shared plumbing for the API controllers: date handling and one error shape.
 */
abstract class ApiController extends AbstractController
{
    /**
     * Parse a YYYY-MM-DD string into a date at midnight, defaulting to today.
     *
     * The time part is stripped on purpose. Diary entries are grouped by
     * calendar day, and a value carrying the current time would never equal the
     * midnight dates already in the database.
     *
     * @throws BadRequestHttpException on a malformed date
     */
    protected function parseDate(?string $value): \DateTimeImmutable
    {
        if (null === $value || '' === $value) {
            return new \DateTimeImmutable('today');
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if (false === $date) {
            throw new BadRequestHttpException(\sprintf('"%s" is not a valid date. Use the format YYYY-MM-DD.', $value));
        }

        return $date;
    }

    /**
     * One error envelope for the whole API, so the SPA has a single thing to
     * unpack no matter which endpoint failed.
     */
    protected function error(string $code, string $message, int $status = Response::HTTP_BAD_REQUEST): JsonResponse
    {
        return $this->json(['error' => $code, 'message' => $message], $status);
    }
}
