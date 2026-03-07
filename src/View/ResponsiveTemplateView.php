<?php

declare(strict_types=1);

namespace Horde\Core\View;

/**
 * Responsive Template View
 *
 * Immutable view object for responsive templates.
 * Holds all data needed for rendering and provides render() method.
 * Does NOT use ancient Horde_View - modern implementation.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @author   Claude Code Assistant
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class ResponsiveTemplateView
{
    /**
     * Template file path
     */
    private string $templatePath;

    /**
     * View data (readonly after construction)
     */
    private array $data;

    /**
     * Constructor
     *
     * @param string $templatePath Absolute path to template file
     * @param array $data View data (will be extracted for template)
     * @throws \InvalidArgumentException If template file not found
     */
    public function __construct(string $templatePath, array $data = [])
    {
        if (!file_exists($templatePath)) {
            throw new \InvalidArgumentException("Template not found: {$templatePath}");
        }

        $this->templatePath = $templatePath;
        $this->data = $data;
    }

    /**
     * Render template to string
     *
     * @return string Rendered HTML
     * @throws \RuntimeException If template rendering fails
     */
    public function render(): string
    {
        // Extract data for template
        extract($this->data, EXTR_SKIP);

        // Capture output
        ob_start();
        try {
            require $this->templatePath;
            return ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw new \RuntimeException(
                "Template rendering failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Get view data (for debugging/testing)
     *
     * @return array View data
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Magic getter for template convenience
     * Allows templates to use $this->propertyName
     *
     * @param string $name Property name
     * @return mixed Property value
     */
    public function __get(string $name)
    {
        return $this->data[$name] ?? null;
    }

    /**
     * Magic isset for template convenience
     *
     * @param string $name Property name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }

    /**
     * Prevent modification of view data
     *
     * @param string $name Property name
     * @param mixed $value Property value
     * @throws \RuntimeException Always - view is immutable
     */
    public function __set(string $name, $value): void
    {
        throw new \RuntimeException('ResponsiveTemplateView is immutable');
    }

    /**
     * Helper: Escape HTML
     *
     * @param mixed $value Value to escape
     * @return string Escaped value
     */
    public function escape($value): string
    {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Helper: Escape HTML attribute
     *
     * @param mixed $value Value to escape
     * @return string Escaped value
     */
    public function escapeAttr($value): string
    {
        return $this->escape($value);
    }

    /**
     * Helper: Escape URL
     *
     * @param string $url URL to escape
     * @return string Escaped URL
     */
    public function escapeUrl(string $url): string
    {
        return htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
