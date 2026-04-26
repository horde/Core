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

namespace Horde\Core\Topbar;

use Horde\Core\Assets\JsDiscoverer;
use Horde\Core\PageOutput\AssetCollector;
use Horde\Injector\Attribute\Factory;
use Stringable;

/**
 * Renders TopbarData to an HTML string for the traditional desktop topbar.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
#[Factory(factory: TopbarRendererFactory::class, method: 'create')]
class TopbarRenderer
{
    public function __construct(
        private readonly AssetCollector $assetCollector,
        private readonly JsDiscoverer $jsDiscoverer,
    ) {}

    public function render(TopbarData $data): string
    {
        $this->registerScripts($data);

        if (!empty($data->jsConfig)) {
            $this->assetCollector->addJsVar('HordeTopbar.conf', $data->jsConfig);
        }

        $html = $this->renderMenubar($data);
        $html .= $this->renderSubbar($data);
        $html .= $this->renderBodyWrappers($data);

        return $html;
    }

    private function registerScripts(TopbarData $data): void
    {
        $scripts = $this->jsDiscoverer->resolveMany([
            'topbar.js',
            'date/date.js',
        ], 'horde');
        foreach ($scripts as $uri) {
            if ($uri !== null) {
                $this->assetCollector->addScript($uri);
            }
        }

        if ($data->searchConfig?->hasMenu) {
            $uri = $this->jsDiscoverer->resolve('form_ghost.js', 'horde');
            if ($uri !== null) {
                $this->assetCollector->addScript($uri);
            }
        }
    }

    private function renderMenubar(TopbarData $data): string
    {
        $html = '<div id="horde-head">' . "\n";
        $html .= '  <div id="horde-logo"><a class="icon" href="'
            . $this->esc($data->portalUrl) . '"></a></div>' . "\n";
        $html .= '  <div id="horde-version">' . $this->esc($data->version) . '</div>' . "\n";

        $html .= '  <div id="horde-navigation">' . "\n";
        $html .= $this->renderMenuTree($data->menuTree);
        $html .= '  </div>' . "\n";

        if ($data->logoutUrl !== null) {
            $html .= '  <div id="horde-logout"><a class="icon" title="Log out" href="'
                . $this->esc($data->logoutUrl) . '"></a></div>' . "\n";
        } elseif ($data->loginUrl !== null) {
            $html .= '  <div id="horde-login"><a class="icon" title="Log in" href="'
                . $this->esc($data->loginUrl) . '"></a></div>' . "\n";
        }

        if ($data->searchConfig !== null) {
            $html .= $this->renderSearchForm($data->searchConfig);
        }

        $html .= '</div>' . "\n";

        return $html;
    }

    /** @param TopbarMenuNode[] $nodes */
    private function renderMenuTree(array $nodes): string
    {
        $html = '';
        foreach ($nodes as $node) {
            $activeClass = $node->active ? '-active' : '';

            $html .= '    <div class="horde-navipoint">' . "\n";
            $html .= '      <div class="horde-point-left' . $activeClass . '"></div>' . "\n";
            $html .= '      <ul class="horde-dropdown">' . "\n";
            $html .= '        <li>' . "\n";

            $cssClass = $node->iconClass ?? '';
            $html .= '          <div class="' . $this->esc($cssClass) . '">' . "\n";

            $linkUrl = $node->url ?? '#';
            $linkAttrs = ' href="' . $this->esc($linkUrl) . '"';
            if ($node->target !== null) {
                $linkAttrs .= ' target="' . $this->esc($node->target) . '"';
            }
            if ($node->onclick !== null) {
                $linkAttrs .= ' onclick="' . $this->esc($node->onclick) . '"';
            }

            $html .= '            <a class="horde-mainnavi' . $activeClass . '"' . $linkAttrs . '>' . "\n";

            if (!empty($node->children) && !$node->noarrow) {
                $html .= '              <span class="horde-point-arrow' . $activeClass . '">&#9662;</span>' . "\n";
            }

            $html .= '              ' . $this->esc($node->label) . "\n";
            $html .= '            </a>' . "\n";
            $html .= '          </div>' . "\n";

            if (!empty($node->children)) {
                $html .= $this->renderSubmenu($node->children);
            }

            $html .= '        </li>' . "\n";
            $html .= '      </ul>' . "\n";
            $html .= '      <div class="horde-point-right' . $activeClass . '"></div>' . "\n";
            $html .= '    </div>' . "\n";
        }
        return $html;
    }

    /** @param TopbarMenuNode[] $children */
    private function renderSubmenu(array $children): string
    {
        $html = '        <ul>' . "\n";
        foreach ($children as $child) {
            $hasChildren = !empty($child->children);
            $html .= '          <li' . ($hasChildren ? ' class="arrow"' : '') . '>' . "\n";
            $html .= '            <div class="horde-drowdown-str">';

            if ($child->url !== null) {
                $linkAttrs = ' href="' . $this->esc($child->url) . '"';
                if ($child->target !== null) {
                    $linkAttrs .= ' target="' . $this->esc($child->target) . '"';
                }
                if ($child->onclick !== null) {
                    $linkAttrs .= ' onclick="' . $this->esc($child->onclick) . '"';
                }
                $html .= '<a class="horde-mainnavi"' . $linkAttrs . '>';
            }

            $html .= $this->esc($child->label);

            if ($child->url !== null) {
                $html .= '</a>';
            }

            $html .= '</div>' . "\n";

            if ($hasChildren) {
                $html .= $this->renderSubmenu($child->children);
            }

            $html .= '          </li>' . "\n";
        }
        $html .= '        </ul>' . "\n";
        return $html;
    }

    private function renderSearchForm(TopbarSearchConfig $config): string
    {
        $html = '  <div id="horde-search">' . "\n";
        $html .= '    <form action="' . $this->esc($config->action) . '" method="get">' . "\n";

        foreach ($config->parameters as $name => $value) {
            $html .= '      <input type="hidden" name="' . $this->esc($name)
                . '" value="' . $this->esc($value) . '" />' . "\n";
        }

        if ($config->hasMenu) {
            $html .= '      <div class="horde-fake-input">' . "\n";
            $html .= '        <span id="horde-search-dropdown">' . "\n";
            $html .= '          <span class="iconImg horde-popdown"></span>' . "\n";
            $html .= '        </span>' . "\n";
            $html .= '        <input autocomplete="off" id="horde-search-input" type="text" />' . "\n";
            $html .= '      </div>' . "\n";
        } else {
            $html .= '      <input type="text" id="horde-search-input" name="searchfield" class="formGhost" title="'
                . $this->esc($config->label) . '" />' . "\n";
        }

        $html .= '      <input type="image" id="horde-search-icon" src="'
            . $this->esc($config->iconUrl) . '" />' . "\n";
        $html .= '    </form>' . "\n";
        $html .= '  </div>' . "\n";

        return $html;
    }

    private function renderSubbar(TopbarData $data): string
    {
        $html = '<div id="horde-sub">' . "\n";
        $html .= '  <div id="horde-date">' . $this->esc($data->date) . '</div>' . "\n";
        $html .= '  <div id="horde-info">' . ($data->subinfo !== null ? $this->esc($data->subinfo) : '') . '</div>' . "\n";
        $html .= '</div>' . "\n";
        return $html;
    }

    private function renderBodyWrappers(TopbarData $data): string
    {
        $html = '<div id="horde-body"';
        if (!$data->sidebarEnabled) {
            $html .= ' class="horde-no-sidebar"';
        }
        $html .= '>' . "\n";

        $html .= '<div id="horde-contentwrapper">' . "\n";
        $html .= '<div id="horde-content"';
        if ($data->sidebarEnabled) {
            $html .= ' style="margin-left:' . $data->sidebarWidth . 'px"';
        }
        $html .= '>' . "\n";

        return $html;
    }

    private function esc(string|Stringable $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
