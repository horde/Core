<?php
declare(strict_types=1);

namespace Horde\Core\Middleware;

use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use \Horde_Registry;
use \Horde_Session;
use \Horde_Exception;


/**
 * DemandSessionToken middleware
 *
 * Checks if the current session token in the Horde-Session-Token header.
 * 
 */
class DemandSessionToken implements MiddlewareInterface
{
    protected ResponseFactoryInterface $responseFactory;
    protected StreamFactoryInterface $streamFactory;
    protected Horde_Session $session;

    public function __construct(
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        Horde_Session $session
    )
    {
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
        $this->session = $session;
    }

    /**
     * checks for a valid session token
     * DemandSessionToken
     * returns 403 response if token is invalid or not found
     * 
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * 
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // using getHeaderLine forces the request to have a single value for the header to be valid
        $token = $request->getHeaderLine('Horde-Session-Token');
        try {
            $this->session->checkToken($token);
        } catch (Horde_Exception $e) {
            return $this->responseFactory->createResponse(403, 'Horde-Session-Token header missing or incorrect.');
        }
        return $handler->handle($request);
    }
}
