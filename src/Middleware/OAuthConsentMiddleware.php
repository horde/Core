<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Middleware;

use DateTimeImmutable;
use Horde\Core\Session\HordeSession;
use Horde\OAuth\ErrorResponse;
use Horde\OAuth\Exception\OAuthException;
use Horde\OAuth\Server\AuthorizationRequest;
use Horde\OAuth\Server\AuthorizationResult;
use Horde\OAuth\Server\Entity\Consent;
use Horde\OAuth\Server\Entity\Scope;
use Horde\OAuth\Server\Handler\AuthorizationEndpoint;
use Horde\OAuth\Server\Repository\ConsentRepository;
use Horde_Notification_Handler;
use Horde_PageOutput;
use Horde_View;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class OAuthConsentMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AuthorizationEndpoint $authorizationEndpoint,
        private readonly ConsentRepository $consentRepository,
        private readonly HordeSession $session,
        private readonly Horde_PageOutput $pageOutput,
        private readonly Horde_Notification_Handler $notification,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getMethod() === 'POST' && $this->session->hasScoped('horde', 'oauth_consent_request')) {
            return $this->handleConsentSubmission($request);
        }

        return $this->handleAuthorizationRequest($request, $handler);
    }

    private function handleAuthorizationRequest(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $userId = $request->getAttribute('HORDE_AUTHENTICATED_USER');
        if ($userId === null) {
            return $handler->handle($request);
        }

        try {
            $authRequest = $this->authorizationEndpoint->validateAuthorizationRequest($request);
        } catch (OAuthException $e) {
            return ErrorResponse::toResponse($e, $this->responseFactory, $this->streamFactory);
        }

        $consent = $this->consentRepository->findConsent($userId, $authRequest->client->clientId);
        $requestedScope = Scope::toSpaceSeparated($authRequest->scopes);

        if ($consent !== null && $consent->coversScope($requestedScope)) {
            $result = new AuthorizationResult($authRequest, true, $userId, $authRequest->scopes);
            return $this->authorizationEndpoint->completeAuthorizationRequest($result);
        }

        return $this->renderConsentForm($authRequest, $userId);
    }

    private function renderConsentForm(AuthorizationRequest $authRequest, string $userId): ResponseInterface
    {
        $csrfToken = bin2hex(random_bytes(16));
        $this->session->setScoped('horde', 'oauth_consent_request', serialize($authRequest));
        $this->session->setScoped('horde', 'oauth_consent_csrf', $csrfToken);

        $scopeNames = array_map(fn(Scope $s) => $s->identifier, $authRequest->scopes);

        $view = new Horde_View([
            'templatePath' => HORDE_TEMPLATES . '/oauth',
        ]);
        $view->clientName = $authRequest->client->clientName;
        $view->scopes = $scopeNames;
        $view->csrfToken = $csrfToken;

        $html = $this->renderChrome(
            _("Authorize Application"),
            fn() => print $view->render('consent')
        );

        $body = $this->streamFactory->createStream($html);
        return $this->responseFactory->createResponse(200)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withBody($body);
    }

    private function handleConsentSubmission(ServerRequestInterface $request): ResponseInterface
    {
        $serialized = $this->session->getScoped('horde', 'oauth_consent_request');
        $storedCsrf = $this->session->getScoped('horde', 'oauth_consent_csrf');

        $this->session->removeScoped('horde', 'oauth_consent_request');
        $this->session->removeScoped('horde', 'oauth_consent_csrf');

        if (!is_string($serialized)) {
            return $this->responseFactory->createResponse(400)
                ->withHeader('Content-Type', 'text/plain')
                ->withBody($this->streamFactory->createStream('Missing consent session data'));
        }

        $authRequest = unserialize($serialized, ['allowed_classes' => [
            AuthorizationRequest::class,
            Scope::class,
            \Horde\OAuth\Server\Entity\Client::class,
        ]]);

        if (!$authRequest instanceof AuthorizationRequest) {
            return $this->responseFactory->createResponse(400)
                ->withHeader('Content-Type', 'text/plain')
                ->withBody($this->streamFactory->createStream('Invalid consent session data'));
        }

        $body = $request->getParsedBody();
        $submittedCsrf = is_array($body) ? ($body['csrf_token'] ?? '') : '';

        if (!is_string($storedCsrf) || !hash_equals($storedCsrf, $submittedCsrf)) {
            return $this->responseFactory->createResponse(403)
                ->withHeader('Content-Type', 'text/plain')
                ->withBody($this->streamFactory->createStream('Invalid CSRF token'));
        }

        $userId = $request->getAttribute('HORDE_AUTHENTICATED_USER');
        $decision = is_array($body) ? ($body['decision'] ?? '') : '';

        if ($decision === 'approve') {
            $approvedScopes = $authRequest->scopes;
            $scopeString = Scope::toSpaceSeparated($approvedScopes);

            $consent = new Consent(
                $userId,
                $authRequest->client->clientId,
                $scopeString,
                new DateTimeImmutable(),
            );
            $this->consentRepository->persist($consent);

            $result = new AuthorizationResult($authRequest, true, $userId, $approvedScopes);
        } else {
            $result = new AuthorizationResult($authRequest, false, $userId);
        }

        return $this->authorizationEndpoint->completeAuthorizationRequest($result);
    }

    private function renderChrome(string $title, callable $renderBody): string
    {
        ob_start();
        $this->pageOutput->header(['title' => $title]);
        $this->notification->notify(['listeners' => 'status']);
        $renderBody();
        $this->pageOutput->footer();

        return ob_get_clean();
    }
}
