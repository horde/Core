<?php
declare(strict_types=1);

namespace Horde\Core\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use \Horde_Registry;
use \Horde_Application;
use Horde\Controller\Request\Psr7Wrapper;
use \Horde_Controller as Controller;
use \Horde_Controller_Response as H5Response;
use Psr\Http\Message\ResponseFactoryInterface;
use Horde\Controller\Response\Psr7Adapter;
use Horde\Http\Stream;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * H5Controller middleware
 *
 * Purpose: 
 * 
 * Wraps and runs a traditional Horde_Controller for BC
 * 
 * Intended to run as bottom of stack
 * 
 * Requires Attributes:
 * 
 * Sets Attributes:
 * 
 * 
 */
class H5Controller implements MiddlewareInterface
{
    private Controller $controller;
    private ResponseFactoryInterface $responseFactory;
    private StreamFactoryInterface $streamFactory;

    public function __construct(
        Controller $controller, 
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory
    )
    {
        $this->controller = $controller;
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $h5request = new Psr7Wrapper($request);
        $h5response = new H5Response();
        $this->controller->processRequest($h5request, $h5response);
        $adapter = new Psr7Adapter($this->responseFactory, $this->streamFactory);
        return $adapter->createPsr7Response($h5response);
    }
}