<?php

declare(strict_types=1);

namespace Horde\Core\Middleware;

use Exception;
use Horde;
use Horde\Http\ResponseFactory;
use Horde\Http\StreamFactory;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Horde_Registry;

/**
 * AppFinder middleware
 *
 * Purpose:
 *
 * Scan through the Registry to find the correct app for the route
 * Setup attributes to enable the app-specific router middleware
 *
 * Requires Attributes:
 *
 * Sets Attributes:
 * - app
 * - prefix
 *
 *
 */
class AppFinder implements MiddlewareInterface
{
    private Horde_Registry $registry;
    private ResponseFactory $responseFactory;
    private StreamFactory $streamFactory;

    public function __construct(
        Horde_Registry $registry,
        ResponseFactory $responseFactory,
        StreamFactory $streamFactory
    ) {
        $this->registry = $registry;
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
    }
    /**
     * Rebuild a path string to a common form
     *
     * Remove any . and .. levels and parts made irrelevant by them
     *
     * @param string $path The input path
     *
     * @return string The normalized path
     */
    protected function _normalize(string $path): string
    {
        $partsIn = explode('/', $path);
        $partsOut = [];
        foreach ($partsIn as $part) {
            // useless slashes
            if (empty($part)) {
                continue;
            }
            // useless level
            if ($part == '.') {
                continue;
            }
            // one level up
            if ($part == '..') {
                array_pop($partsOut);
                continue;
            }
            $partsOut[] = $part;
        }
        return '/' . implode('/', $partsOut);
    }

    /**
     * Identify the correct app to handle the request
     *
     * This needs to cover a lot of cases
     * - app lives below horde (pear default)
     * - app lives besides or independent of horde (composer default)
     * - app is horde
     * - app lives in document root (https://webmail.foo.org is imp)
     * - app lives below horde but document root is another app
     *    eg https://webmail.foo.org where / is imp, /horde is horde, /horde/turba is turba
     *
     * @param ServerRequestInterface $request    Request object
     * @param Horde_Registry  $registry    The Horde Registry
     */
    protected function identifyApp(ServerRequestInterface $request, Horde_Registry $registry)
    {
        $matches = [];
        $scheme = $request->getUri()->getScheme();
        $host = $request->getUri()->getHost();
        $path = $request->getUri()->getPath();
        // listApps() would return empty on unauthenticated access
        foreach ($registry->listApps(null, true, null) as $app => $config) {
            $default = [
                'scheme' => $scheme,
                'host' => $host,
                'path' => '',
                'app' => $app,
            ];
            $webroots = [];
            $webroots[] = $registry->get('webroot', $app);

            $webrootAliases = $config['webroot_aliases'] ?? null;
            if (is_array($webrootAliases)) {
                $webroots = array_merge($webroots, $webrootAliases);
            }

            foreach ($webroots as $webroot) {
                $applicationUrl = array_merge($default, parse_url($webroot));
                $appPath = $applicationUrl['path'] = $this->_normalize($applicationUrl['path']);
                // sort out cases with wrong host or scheme
                if ($scheme != $applicationUrl['scheme']) {
                    continue;
                }
                if ($host != $applicationUrl['host']) {
                    continue;
                }

                // does the path match at all?
                if ($this->matchesAppPath($appPath, $path)) {
                    $matches[] = $applicationUrl;
                }
            }
        }
        // No matches, return early
        if (count($matches) == 0) {
            return $matches;
        }
        // Longest match path *should* always be the right app
        usort(
            $matches,
            function ($a, $b) {
                return strlen($a['path']) <=> strlen($b['path']);
            }
        );
        return array_pop($matches);
    }

    protected function matchesAppPath(string $appPath, string $requestPath): bool
    {
        // add a slash to appPath and requestPath, if not already done
        // this is needed because otherwise a path like 'app_v2' would match the app 'app', which we do not want
        $appPath = rtrim($appPath, '/') . '/';
        $requestPath = rtrim($requestPath, '/') . '/';

        return substr($requestPath, 0, strlen($appPath)) === $appPath;
    }


    /**
     * Configure the
     *
     * If no found app fits, return the NotFound response.
     *
     * Initialize the found app.
     * Load the route definition file for the found app.
     *
     * Initialize the routes mapper with the app's base path and match
     *
     * Finally check authentication if required
     *
     * @param ServerRequestInterface $request The incoming request
     * @param RequestHandlerInterface $handler The request handler to configure
     *
     * @return ResponseInterface The response from
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $registry = $request->getAttribute('registry');

        $found = $this->identifyApp($request, $registry);

        // If we still found no app, give up
        if (empty($found)) {
            $path = $request->getUri()->getPath();
            $msg = sprintf('No App found for path: %s', $path);
            Horde::log($msg, 'INFO');
            return $this->responseFactory->createResponse(
                404,
                'Not Found'
            )->withBody($this->streamFactory->createStream($msg));
        }
        $prefix = $found['path'];

        // Route mapper doesn't like / as prefix
        if ($prefix == '/') {
            $prefix = '';
        }
        $request = $request->withAttribute('routerPrefix', $prefix);
        $request = $request->withAttribute('app', $found['app']);
        return $handler->handle($request);
    }
}
