<?php

declare(strict_types=1);

namespace Horde\Core\Middleware;

use Horde;
use Horde\Http\ResponseFactory;
use Horde\Http\StreamFactory;
use Horde_ErrorHandler;
use Horde_Registry;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * ErrorFilter middleware
 *
 * Purpose:
 *
 * Prevent ugly stack traces from showing up to users or APIs.
 * Give meaningful feedback and logging.
 * Can handle errors early in setup
 * Can give more meaningful feedback on a fully setup environment
 *
 * Intended to run close to top of stack
 *
 * Requires Attributes:
 *
 * Sets Attributes:
 *
 *
 */
class ErrorFilter implements MiddlewareInterface
{
    protected Horde_Registry $registry;
    protected ResponseFactory $responseFactory;
    protected StreamFactory $streamFactory;

    public function __construct(
        Horde_Registry $registry,
        ResponseFactory $responseFactory,
        StreamFactory $streamFactory
    ) {
        $this->registry = $registry;
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $throwable) {
            Horde::log($throwable, 'EMERG');
            return $this->getErrorResponse($request, $throwable);
        }
    }

    protected function getErrorResponse(ServerRequestInterface $request, Throwable $throwable): ResponseInterface
    {
        $isAdmin = $this->registry->isAdmin();
        $acceptsJson = in_array('application/json', array_map(fn($val) => strtolower($val), $request->getHeader('Accept')));
        if ($acceptsJson){
            return $this->getJsonResponse($throwable, $isAdmin);
        } else {
            return $this->getHtmlResponse($throwable, $isAdmin);
        }
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
