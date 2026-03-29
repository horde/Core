<?php

/**
 * Responsive Controller Trait
 *
 * Provides common functionality for responsive application controllers.
 * Centralizes asset loading, topbar rendering, and response helpers.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Core
 */

declare(strict_types=1);

namespace Horde\Core\Controller;

use Horde\Core\Assets\ResponsiveAssets;
use Horde\Core\View\ResponsiveTemplateView;
use Horde\Core\View\ResponsiveTopbar;
use Horde_Registry;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

/**
 * Responsive Controller Trait
 *
 * Provides common functionality for responsive application controllers:
 * - Topbar rendering with app lists
 * - Asset loading (CSS/JS) with proper cascade
 * - Template rendering with consistent data structure
 * - Redirect helpers
 * - Response factory access
 *
 * Usage:
 * <code>
 * class ResponsiveController implements RequestHandlerInterface
 * {
 *     use ResponsiveControllerTrait;
 *
 *     protected function getAppName(): string
 *     {
 *         return _("Tasks");
 *     }
 *
 *     protected function getTemplateBasePath(): string
 *     {
 *         return NAG_TEMPLATES . '/responsive/';
 *     }
 *
 *     private function index(ServerRequestInterface $request): ResponseInterface
 *     {
 *         return $this->renderTemplate('browse.html.php', [
 *             'tasks' => $tasks,
 *         ]);
 *     }
 * }
 * </code>
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Core
 */
trait ResponsiveControllerTrait
{
    /**
     * Get application name for topbar display
     *
     * Must be implemented by the using controller.
     *
     * @return string Localized application name
     */
    abstract protected function getAppName(): string;

    /**
     * Get template base path for this application
     *
     * Must be implemented by the using controller.
     * Should return path with trailing slash.
     *
     * @return string Template base path (e.g., NAG_TEMPLATES . '/responsive/')
     */
    abstract protected function getTemplateBasePath(): string;

    /**
     * Get Horde registry instance
     *
     * Must be implemented by the using controller.
     * Controller should provide registry via constructor injection.
     *
     * @return Horde_Registry Registry instance
     */
    abstract protected function getRegistry(): Horde_Registry;

    /**
     * Get PSR-7 URI factory instance
     *
     * Must be implemented by the using controller.
     * Controller should provide factory via constructor injection.
     *
     * @return UriFactoryInterface URI factory instance
     */
    abstract protected function getUriFactory(): UriFactoryInterface;

    /**
     * Get PSR-7 response factory instance
     *
     * Must be implemented by the using controller.
     * Controller should provide factory via constructor injection.
     *
     * @return ResponseFactoryInterface Response factory instance
     */
    abstract protected function getResponseFactory(): ResponseFactoryInterface;

    /**
     * Get PSR-7 stream factory instance
     *
     * Must be implemented by the using controller.
     * Controller should provide factory via constructor injection.
     *
     * @return StreamFactoryInterface Stream factory instance
     */
    abstract protected function getStreamFactory(): StreamFactoryInterface;

    /**
     * Render template with topbar and assets
     *
     * Automatically includes:
     * - Responsive topbar with app lists
     * - CSS cascade (horde base + app)
     * - JavaScript (topbar + app-specific)
     *
     * @param string $template Template filename (e.g., 'browse.html.php')
     * @param array $data Template variables
     * @param array $extraJsFiles Additional JS files to load (optional)
     *
     * @return ResponseInterface HTTP response with rendered HTML
     */
    protected function renderTemplate(
        string $template,
        array $data,
        array $extraJsFiles = []
    ): ResponseInterface {
        $registry = $this->getRegistry();
        $responseFactory = $this->getResponseFactory();

        // Create helpers
        $responsiveAssets = new ResponsiveAssets($registry);
        $responsiveTopbar = new ResponsiveTopbar($registry, $this->getAppName());

        // Render topbar
        $data['topbarHtml'] = $responsiveTopbar->render();

        // Add asset URLs
        $data['cssUrls'] = $responsiveAssets->getCssUrls();

        // Build JS file list
        $jsFiles = array_merge(['responsive-topbar.js'], $extraJsFiles);
        $data['jsUrls'] = array_merge(
            $responsiveAssets->getJsUrls($jsFiles, 'horde'),
            $responsiveAssets->getJsUrls(['responsive.js'])
        );

        // Render template
        $templatePath = $this->getTemplateBasePath() . $template;
        $view = new ResponsiveTemplateView($templatePath, $data);

        // Add escape helper for templates
        $data['escape'] = [$view, 'escape'];

        // Add URL builder helper for templates (PSR-7 Uri pattern)
        $data['url'] = function (string $path, array $params = []) {
            return $this->buildUrl($path, $params);
        };

        // Re-create view with helpers
        $view = new ResponsiveTemplateView($templatePath, $data);

        // Create response
        $response = $responseFactory->createResponse(200)
            ->withHeader('Content-Type', 'text/html; charset=UTF-8');
        $response->getBody()->write($view->render());

        return $response;
    }

    /**
     * Build application URL using PSR-7 UriFactory
     *
     * Creates properly encoded URLs with query parameters.
     * Uses UriFactory from dependency injection.
     *
     * @param string $path Path relative to app webroot (e.g., 'responsive/task/123')
     * @param array $params Optional query parameters
     *
     * @return string Full URL string
     */
    protected function buildUrl(string $path, array $params = []): string
    {
        $registry = $this->getRegistry();
        $uriFactory = $this->getUriFactory();

        $webroot = $registry->get('webroot', $registry->getApp());
        $uri = $uriFactory->createUri($webroot . '/' . ltrim($path, '/'));

        if (!empty($params)) {
            // PSR-7 Uri is IMMUTABLE - with* methods return NEW instances
            $uri = $uri->withQuery(http_build_query($params));
        }

        return (string) $uri;
    }

    /**
     * Create redirect response
     *
     * Helper for creating 302 redirect responses.
     *
     * @param string $url URL to redirect to
     * @param string $message Optional notification message
     * @param string $messageType Message type ('horde.success', 'horde.error', etc.)
     *
     * @return ResponseInterface HTTP 302 redirect response
     */
    protected function redirectTo(
        string $url,
        string $message = '',
        string $messageType = 'horde.error'
    ): ResponseInterface {
        global $notification;

        $responseFactory = $this->getResponseFactory();

        if ($message) {
            $notification->push($message, $messageType);
        }

        return $responseFactory->createResponse(302)
            ->withHeader('Location', $url);
    }

    /**
     * Create file download response
     *
     * Helper for creating file download responses with proper headers.
     *
     * @param string $content File content
     * @param string $filename Download filename
     * @param string $mimeType MIME type (default: application/octet-stream)
     *
     * @return ResponseInterface HTTP response with download headers
     */
    protected function downloadFile(
        string $content,
        string $filename,
        string $mimeType = 'application/octet-stream'
    ): ResponseInterface {
        $streamFactory = $this->getStreamFactory();
        $responseFactory = $this->getResponseFactory();

        return $responseFactory->createResponse(200)
            ->withHeader('Content-Type', $mimeType . '; charset=UTF-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withBody($streamFactory->createStream($content));
    }

    /**
     * Create JSON response
     *
     * Helper for creating JSON API responses.
     *
     * @param array $data Data to encode as JSON
     * @param int $status HTTP status code (default: 200)
     *
     * @return ResponseInterface HTTP response with JSON content
     */
    protected function jsonResponse(array $data, int $status = 200): ResponseInterface
    {
        $streamFactory = $this->getStreamFactory();
        $responseFactory = $this->getResponseFactory();

        $json = json_encode($data, JSON_THROW_ON_ERROR);

        return $responseFactory->createResponse($status)
            ->withHeader('Content-Type', 'application/json; charset=UTF-8')
            ->withBody($streamFactory->createStream($json));
    }

    /**
     * Get query parameter with default
     *
     * Helper for safely getting query parameters.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request object
     * @param string $name Parameter name
     * @param mixed $default Default value if not set
     *
     * @return mixed Parameter value or default
     */
    protected function getQueryParam($request, string $name, $default = null)
    {
        $params = $request->getQueryParams();
        return $params[$name] ?? $default;
    }

    /**
     * Get POST parameter with default
     *
     * Helper for safely getting POST parameters.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request object
     * @param string $name Parameter name
     * @param mixed $default Default value if not set
     *
     * @return mixed Parameter value or default
     */
    protected function getPostParam($request, string $name, $default = null)
    {
        $body = $request->getParsedBody();
        if (is_array($body)) {
            return $body[$name] ?? $default;
        }
        return $default;
    }

    /**
     * Get route parameter from request attributes
     *
     * Helper for getting route parameters set by the router.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request object
     * @param string $name Parameter name
     * @param mixed $default Default value if not set
     *
     * @return mixed Parameter value or default
     */
    protected function getRouteParam($request, string $name, $default = null)
    {
        $route = $request->getAttribute('route', []);
        return $route[$name] ?? $default;
    }

    /**
     * Check if request is POST
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request object
     *
     * @return bool True if POST request
     */
    protected function isPost($request): bool
    {
        return $request->getMethod() === 'POST';
    }

    /**
     * Check if request is GET
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request object
     *
     * @return bool True if GET request
     */
    protected function isGet($request): bool
    {
        return $request->getMethod() === 'GET';
    }
}
