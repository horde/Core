<?php
declare(strict_types=1);

namespace Horde\Core\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use \Horde_Registry;
use \Horde_Application;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Horde\Http\RequestFactory;
use Horde\Http\UriFactory;
use Horde\Http\StreamFactory;
use Horde\Http\ResponseFactory;

/**
 * HordeCoreMiddleware
 *
 * Sets up a Horde Application Framework environment
 * Initially one long process, should progressively be split into multiple middlewares
 *
 */
class HordeCore implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // run AppInit, implicitly load core
        // Using ::class would defeat the purpose here
        if (!class_exists('Horde_Application'))
        {
            throw new \Horde_Exception('Autoloading issue');
        }
        if (!class_exists('Horde_Registry'))
        {
            throw new \Horde_Exception('Autoloading issue');
        } 
        // This does way too much
        $hordeEnv = Horde_Registry::appInit('horde', ['authentication' => 'none']);
        // Bad! the injector should be part of the early init's response.
        $injector = $GLOBALS['injector'];
        // If there are no existing implementations, set them
        if (!$injector->has(UriFactoryInterface::class)) {
            $injector->setInstance(UriFactoryInterface::class, new UriFactory());
        }
        if (!$injector->has(StreamFactoryInterface::class)) {
            $injector->setInstance(StreamFactoryInterface::class, new StreamFactory());
        }
        if (!$injector->has(ServerRequestFactoryInterface::class)) {
            $injector->setInstance(ServerRequestFactoryInterface::class, new RequestFactory());
        }
        if (!$injector->has(ResponseFactoryInterface::class)) {
            $injector->setInstance(ResponseFactoryInterface::class, new ResponseFactory());
        }


        // Detect correct app
        $registry = $injector->getInstance('Horde_Registry');
        $request = $request->withAttribute('registry', $registry);
        $handler->addMiddleware(new AppFinder($registry));
        // Find route inside detected app
        $handler->addMiddleware(new AppRouter($registry, $injector->get('Horde_Routes_Mapper'), $injector));
        return $handler->handle($request);
    }
}