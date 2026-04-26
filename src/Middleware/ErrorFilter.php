<?php

declare(strict_types=1);

namespace Horde\Core\Middleware;

use Horde\Core\Horde;
use Horde\Http\ResponseFactory;
use Horde\Http\StreamFactory;
use Horde_ErrorHandler;
use Horde_Log;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * ErrorFilter middleware
 *
 * Prevents ugly stack traces from reaching users or APIs.
 * Shows detailed error info only to admin users.
 * Reads HORDE_AUTHENTICATED_USER from request attributes and checks
 * against the admin list from horde config.
 *
 * Intended to run close to top of stack.
 */
class ErrorFilter implements MiddlewareInterface
{
    /** @param string[] $admins Admin usernames from conf[auth][admins] */
    public function __construct(
        private readonly array $admins,
        private readonly ResponseFactory $responseFactory,
        private readonly StreamFactory $streamFactory,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $throwable) {
            Horde::log($throwable, Horde_Log::EMERG);
            return $this->getErrorResponse($request, $throwable);
        }
    }

    protected function getErrorResponse(ServerRequestInterface $request, Throwable $throwable): ResponseInterface
    {
        $user = $request->getAttribute('HORDE_AUTHENTICATED_USER');
        $isAdmin = $user !== null && in_array($user, $this->admins, true);

        $acceptsJson = in_array('application/json', array_map(fn($val) => strtolower($val), $request->getHeader('Accept')));
        if ($acceptsJson) {
            return $this->getJsonResponse($throwable, $isAdmin);
        }
        return $this->getHtmlResponse($throwable, $isAdmin);
    }

    protected function getJsonResponse(Throwable $throwable, bool $isAdmin = false): ResponseInterface
    {
        $json = json_encode([
            'message' => $throwable->getMessage(),
            'code' => $throwable->getCode(),
            'trace' => $isAdmin ? $throwable->getTrace() : [],
        ]);
        $stream = $this->streamFactory->createStream($json);
        return $this->responseFactory->createResponse(500, 'Internal Server Error')
            ->withBody($stream)
            ->withHeader('Content-Type', 'application/json');
    }

    protected function getHtmlResponse(Throwable $throwable, bool $isAdmin = false): ResponseInterface
    {
        $stream = $this->streamFactory->createStream(Horde_ErrorHandler::getHtmlForError($throwable, $isAdmin));
        return $this->responseFactory->createResponse(500, 'Internal Server Error')
            ->withBody($stream);
    }
}
