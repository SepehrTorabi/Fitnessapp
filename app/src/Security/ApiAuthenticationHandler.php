<?php

declare(strict_types=1);

namespace App\Security;

use App\Api\Presenter\UserPresenter;
use App\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Keeps the firewall speaking JSON.
 *
 * Out of the box the security component answers with redirects and HTML login
 * pages, which is useless to a SPA. The three roles - login succeeded, login
 * failed, no credentials at all - are handled together here because they have
 * to agree on one error shape.
 */
final class ApiAuthenticationHandler implements
    AuthenticationSuccessHandlerInterface,
    AuthenticationFailureHandlerInterface,
    AuthenticationEntryPointInterface
{
    public function __construct(private readonly UserPresenter $userPresenter)
    {
    }

    /**
     * Credentials accepted: hand back the profile the SPA needs to render.
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $user = $token->getUser();

        return new JsonResponse([
            'user' => $user instanceof User ? $this->userPresenter->present($user) : null,
        ]);
    }

    /**
     * Credentials rejected.
     *
     * A wrong password gets a deliberately vague message - saying whether the
     * address exists would let anyone enumerate registered users. An unconfirmed
     * account is different: that message is actionable, the account holder is
     * the one seeing it, and hiding it would leave the user stuck at a login
     * that simply never works.
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $isSafeToReveal = $exception instanceof AccountStatusException
            || $exception instanceof CustomUserMessageAuthenticationException;

        return new JsonResponse([
            'error' => 'authentication_failed',
            'message' => $isSafeToReveal
                ? $exception->getMessageKey()
                : 'Invalid e-mail address or password.',
        ], Response::HTTP_UNAUTHORIZED);
    }

    /**
     * No credentials at all on a protected endpoint.
     */
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse([
            'error' => 'authentication_required',
            'message' => 'You must be signed in to use this endpoint.',
        ], Response::HTTP_UNAUTHORIZED);
    }
}
