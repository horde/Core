<?php
declare(strict_types=1);

namespace Horde\Core\Middleware;

use Exception;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use \Horde_Registry;

/**
 * AppFinder middleware
 *
 * Purpose: 
 * 
 * Scan through the Registry to find the correct app for the route
 * Setup attributes to enable the app-specific router middleware
 * 
 * Requires Attributes:
 * - dic        A handle for the DI Container
 * - registry   A handle for the horde registry
 * 
 * Sets Attributes:
 * - app
 * - prefix
 * 
 * 
 */
class AppFinder implements MiddlewareInterface
{
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
     * @param RequestInterface $host    Request host part (www.foo.org)
     * @param Horde_Registry  $registry         The Horde Registry
     */
    protected function identifyApp(ServerRequestInterface $request, Horde_Registry $registry)
    {
        $matches = [];
        // listApps() would return empty on unauthenticated access
        foreach ($registry->listApps(null, false, null) as $app)
        {
            $scheme = $request->getUri()->getScheme();
            $host = $request->getUri()->getHost();
            $path = $request->getUri()->getPath();

            $default = [
               'scheme' => $scheme,
               'host' => $host,
               'path' => '',
               'app' => $app
            ];

            $applicationUrl = array_merge($default, parse_url($registry->get('webroot', $app)));
            $applicationUrl['path'] = $this->_normalize($applicationUrl['path']);
            // sort out cases with wrong host or scheme
            if ($scheme != $applicationUrl['scheme']) { continue; }
            if ($host != $applicationUrl['host']) { continue; }
            // does the path match at all?

            if (substr($path, 0, strlen($applicationUrl['path'])) == $applicationUrl['path']) {    
                $matches[] = $applicationUrl;
            }
        }
        // No matches, return early
        if (count($matches) == 0) {
            return $matches;
        }
        // Longest match path *should* always be the right app
        usort($matches, function($a, $b) 
        {
             return strlen($a['path']) <=> strlen($b['path']);
        }
        );
        return array_pop($matches);
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
        $injector = $request->getAttribute('dic');
        $requestServer = $request->getUri()->getHost();

        $uriScheme = $request->getUri()->getScheme();
        $registry = $request->getAttribute('registry');

        $found = $this->identifyApp($request, $registry);
        $prefix = $found['path'];

        // If we still found no app, give up
        if (empty($found)) {
            throw new \Exception("No App found for this path");
        }

        // Route mapper doesn't like / as prefix
        if ($prefix == '/') {
            $prefix = '';
        }
        $request = $request->withAttribute('routerPrefix', $prefix);
        $request = $request->withAttribute('app', $found['app']);
        return $handler->handle($request);
    }
}