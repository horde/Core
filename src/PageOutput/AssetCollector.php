<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

namespace Horde\Core\PageOutput;

/**
 * Mutable request-scoped accumulator for page assets.
 *
 * Collects scripts, stylesheets, inline JS, meta tags and link tags
 * during request processing. Each render method returns a string —
 * nothing is echoed or buffered.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
#[Factory(factory: AssetCollectorFactory::class, method: 'create')]
class AssetCollector
{
    /** @var string[] */
    private array $scriptUrls = [];

    /** @var array<string, true> */
    private array $scriptUrlSet = [];

    /** @var string[] */
    private array $stylesheetUrls = [];

    /** @var array<string, true> */
    private array $stylesheetUrlSet = [];

    private const ONLOAD_RAW = "\0raw";

    /** @var array<string, list<array{code: string, top: bool}>> */
    private array $inlineScripts = [];

    /** @var list<array{name: string, value: string, raw: bool, top: bool}> */
    private array $jsVars = [];

    /** @var array<string, array{content: string, httpEquiv: bool}> */
    private array $metaTags = [];

    /** @var string[] */
    private array $linkTags = [];

    public function addScript(string $url): void
    {
        if (isset($this->scriptUrlSet[$url])) {
            return;
        }
        $this->scriptUrlSet[$url] = true;
        $this->scriptUrls[] = $url;
    }

    public function addStylesheet(string $url): void
    {
        if (isset($this->stylesheetUrlSet[$url])) {
            return;
        }
        $this->stylesheetUrlSet[$url] = true;
        $this->stylesheetUrls[] = $url;
    }

    /**
     * @param string|false $onload  false for raw, 'prototype' or 'jquery'
     */
    public function addInlineScript(string $code, string|false $onload = false, bool $top = false): void
    {
        $code = trim($code);
        if ($code === '') {
            return;
        }
        $code = rtrim($code, ';') . ';';

        $key = $onload === false ? self::ONLOAD_RAW : $onload;
        $this->inlineScripts[$key][] = ['code' => $code, 'top' => $top];
    }

    public function addJsVar(string $name, mixed $value, bool $top = false): void
    {
        $this->jsVars[] = [
            'name' => $name,
            'value' => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'raw' => false,
            'top' => $top,
        ];
    }

    public function addRawJsVar(string $name, string $rawJs, bool $top = false): void
    {
        $this->jsVars[] = [
            'name' => $name,
            'value' => $rawJs,
            'raw' => true,
            'top' => $top,
        ];
    }

    public function addMetaTag(string $name, string $content, bool $httpEquiv = true): void
    {
        $this->metaTags[$name] = [
            'content' => $content,
            'httpEquiv' => $httpEquiv,
        ];
    }

    public function addLinkTag(array $attrs = []): void
    {
        $attrs = array_merge([
            'rel' => 'alternate',
            'type' => 'application/rss+xml',
        ], $attrs);

        $out = '<link';
        foreach ($attrs as $key => $val) {
            if ($val !== null) {
                $out .= ' ' . $key . '="' . htmlspecialchars((string) $val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
            }
        }
        $this->linkTags[] = $out . ' />';
    }

    /** @return string[] */
    public function getScriptUrls(): array
    {
        return $this->scriptUrls;
    }

    /** @return string[] */
    public function getStylesheetUrls(): array
    {
        return $this->stylesheetUrls;
    }

    public function renderMetaTags(): string
    {
        $html = '';
        foreach ($this->metaTags as $name => $tag) {
            $attr = $tag['httpEquiv'] ? 'http-equiv' : 'name';
            $html .= '<meta content="' . htmlspecialchars($tag['content'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '" ' . $attr . '="' . htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\" />\n";
        }
        return $html;
    }

    public function renderLinkTags(): string
    {
        if (empty($this->linkTags)) {
            return '';
        }
        return implode("\n", $this->linkTags);
    }

    public function renderScriptTags(): string
    {
        $html = '';
        foreach ($this->scriptUrls as $url) {
            $html .= '<script type="text/javascript" src="'
                . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '"></script>';
        }
        return $html;
    }

    public function renderStylesheetTags(): string
    {
        $html = '';
        foreach ($this->stylesheetUrls as $url) {
            $html .= '<link rel="stylesheet" type="text/css" href="'
                . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . "\" />\n";
        }
        return $html;
    }

    public function renderJsVarBlock(bool $topOnly = false): string
    {
        $lines = [];
        foreach ($this->jsVars as $entry) {
            if ($topOnly && !$entry['top']) {
                continue;
            }
            $lines[] = $entry['name'] . '=' . $entry['value'] . ';';
        }
        if (empty($lines)) {
            return '';
        }
        return $this->wrapInlineScript($lines);
    }

    /**
     * @param string|false $onload
     */
    public function renderInlineScriptBlock(string|false $onload, bool $topOnly = false): string
    {
        $key = $onload === false ? self::ONLOAD_RAW : $onload;
        if (!isset($this->inlineScripts[$key])) {
            return '';
        }

        $code = '';
        foreach ($this->inlineScripts[$key] as $entry) {
            if ($topOnly && !$entry['top']) {
                continue;
            }
            $code .= $entry['code'];
        }
        if ($code === '') {
            return '';
        }

        if ($onload !== false) {
            $code = match ($onload) {
                'prototype', 'dom' => 'document.observe("dom:loaded",function(){' . $code . '});',
                'jquery' => '$(function(){' . $code . '});',
                default => $code,
            };
        }

        return $this->wrapInlineScript([$code]);
    }

    public function renderAllInlineScripts(bool $topOnly = false): string
    {
        $parts = [];

        $vars = $this->renderJsVarBlock($topOnly);
        if ($vars !== '') {
            $parts[] = $vars;
        }

        foreach ($this->inlineScripts as $key => $entries) {
            $onload = $key === self::ONLOAD_RAW ? false : $key;
            $block = $this->renderInlineScriptBlock($onload, $topOnly);
            if ($block !== '') {
                $parts[] = $block;
            }
        }

        return implode('', $parts);
    }

    private function wrapInlineScript(array $scripts): string
    {
        return '<script type="text/javascript">//<![CDATA[' . "\n"
            . implode('', $scripts)
            . "\n//]]></script>\n";
    }
}
