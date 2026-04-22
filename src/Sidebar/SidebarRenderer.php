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

namespace Horde\Core\Sidebar;

use Horde\Core\PageOutput\AssetCollector;

/**
 * Renders SidebarData to an HTML string for the traditional desktop sidebar.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class SidebarRenderer
{
    public function __construct(
        private readonly AssetCollector $assetCollector,
    ) {}

    public function render(SidebarData $data): string
    {
        $hasHeaders = false;
        foreach ($data->containers as $container) {
            if ($container->header !== null) {
                $hasHeaders = true;
                break;
            }
        }

        $html = '</div>' . "\n" . '</div>' . "\n\n";

        $html .= '<div id="horde-sidebar" style="width:' . $data->width . 'px">' . "\n\n";

        if ($data->newButton !== null) {
            $html .= $this->renderNewButton($data->newButton);
        }

        if (!empty($data->containers)) {
            foreach ($data->containers as $index => $container) {
                $html .= $this->renderContainer($container, $index);
            }
        } elseif ($data->content !== null && $data->content !== '') {
            $html .= $data->content . "\n";
        }

        $html .= "\n</div>\n\n";

        $leftPos = $data->isRtl ? 'right:' : 'left:';
        $html .= '<div id="horde-slideleft" class="horde-splitbar-vert" style="'
            . $leftPos . $data->width . 'px">' . "\n";
        $html .= '  <div id="horde-slideleftcursor" class="horde-splitbar-vert-handle"></div>' . "\n";
        $html .= '</div>' . "\n";

        return $html;
    }

    private function renderNewButton(SidebarButton $button): string
    {
        $html = '  <div class="horde-new">' . "\n";

        if ($button->extra !== null) {
            $html .= '    <div class="horde-new-extra">' . $button->extra . '&nbsp;</a></div>' . "\n";
            $html .= '    <div class="horde-new-split"></div>' . "\n";
        }

        $html .= '    <span class="horde-new-link">' . $button->url . $button->label . '</a></span>' . "\n";
        $html .= '  </div>' . "\n";

        return $html;
    }

    private function renderContainer(SidebarContainer $container, int $index): string
    {
        $html = '';

        if ($index > 0) {
            $html .= '<div class="horde-sidebar-split"></div>' . "\n";
        }

        if ($container->header !== null) {
            $html .= $this->renderHeader($container->header);
        }

        $divAttrs = '';
        if ($container->id !== null) {
            $divAttrs .= ' id="' . $this->esc($container->id) . '"';
        }
        if ($container->header !== null && $container->header->collapsed) {
            $divAttrs .= ' style="display:none"';
        }
        $html .= '<div' . $divAttrs . '>' . "\n";

        if ($container->content !== null) {
            $html .= $container->content . "\n";
        } elseif (!empty($container->rows)) {
            if ($container->type === 'tree') {
                foreach ($container->rows as $row) {
                    $html .= $this->renderTreeRow($row);
                }
            } else {
                $html .= '<div class="horde-resources">' . "\n";
                foreach ($container->rows as $row) {
                    $html .= $this->renderResourceRow($row);
                }
                $html .= '</div>' . "\n";
            }
        } else {
            $html .= '<div class="horde-info">No items to display</div>' . "\n";
        }

        $html .= '</div>' . "\n";

        return $html;
    }

    private function renderHeader(SidebarHeader $header): string
    {
        $html = '<h3>' . "\n";

        if ($header->addUrl !== null) {
            $html .= '  <a href="' . $this->esc($header->addUrl)
                . '" class="horde-add" title="'
                . $this->esc($header->addLabel ?? '') . '">+</a>' . "\n";
        }

        $collapseClass = $header->collapsed ? 'horde-expand' : 'horde-collapse';
        $collapseTitle = $header->collapsed ? 'Expand' : 'Collapse';
        $html .= '  <span id="' . $this->esc($header->id)
            . '" class="' . $collapseClass
            . '" title="' . $collapseTitle . '">'
            . $this->esc($header->label) . '</span>' . "\n";
        $html .= '</h3>' . "\n";

        return $html;
    }

    private function renderTreeRow(SidebarRow $row): string
    {
        $divClass = 'horde-subnavi';
        if ($row->selected) {
            $divClass .= ' horde-subnavi-active';
        }

        $html = '<div class="' . $divClass . '"';
        if ($row->style !== null) {
            $html .= ' style="' . $this->esc($row->style) . '"';
        }
        $html .= '>' . "\n";

        $html .= ' <div class="horde-subnavi-icon ' . $this->esc($row->cssClass ?? '') . '">' . "\n";
        $html .= '  <a class="icon"></a>' . "\n";
        $html .= ' </div>';

        $html .= '<div';
        if ($row->id !== null) {
            $html .= ' id="' . $this->esc($row->id) . '"';
        }
        $html .= ' class="horde-subnavi-point">' . $row->linkHtml . "\n";
        $html .= ' </div>' . "\n";
        $html .= '</div>' . "\n";

        return $html;
    }

    private function renderResourceRow(SidebarRow $row): string
    {
        $html = '<div';
        if ($row->style !== null) {
            $html .= ' style="' . $this->esc($row->style) . '"';
        }
        $html .= '>' . "\n";

        if ($row->editLinkHtml !== null) {
            $html .= '  ' . $row->editLinkHtml . "\n";
        }

        $html .= '  <div class="horde-resource-link">' . "\n";
        $html .= '    ' . $row->linkHtml . "\n";
        $html .= '  </div>' . "\n";
        $html .= '</div>' . "\n";

        return $html;
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
