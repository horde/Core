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

/**
 * Legacy adapter that mimics Horde_View_Sidebar's public API.
 *
 * Collects sidebar elements through addNewButton() and addRow(),
 * then converts to a typed SidebarData via toSidebarData().
 * Used by SidebarBuilder to bridge existing Application::menu()
 * and Application::sidebar() hooks.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class SidebarCollector
{
    /** @var array<string, array> */
    public array $containers = [];

    public ?string $newLink = null;
    public ?string $newText = null;
    public ?string $newExtra = null;

    /**
     * @param string $label Button text (may contain access key markup)
     * @param object|string $url Horde_Url or pre-built opening <a> tag HTML
     * @param array $extra Extra link attributes
     */
    public function addNewButton(string $label, object|string $url, array $extra = []): void
    {
        if (is_object($url) && method_exists($url, 'link')) {
            $this->newLink = $url->link($extra);
        } else {
            $this->newLink = (string) $url;
        }
        $this->newText = $label;
    }

    /**
     * Adds a row to the sidebar, replicating legacy Horde_View_Sidebar::addRow().
     *
     * @param array $row Row data matching legacy format
     * @param string $container Container ID (empty for default)
     */
    public function addRow(array $row, string $container = ''): void
    {
        if (!isset($this->containers[$container])) {
            $this->containers[$container] = ['rows' => []];
            if ($container !== '') {
                $this->containers[$container]['id'] = $container;
            }
        }

        $boxrow = isset($row['type'])
            && ($row['type'] === 'checkbox' || $row['type'] === 'radiobox');
        $label = htmlspecialchars($row['label']);

        if (!isset($row['link'])) {
            if (isset($row['url'])) {
                $url = $row['url'];
                $attributes = [];

                foreach (['onclick', 'target', 'class'] as $attr) {
                    if (!empty($row[$attr])) {
                        $attributes[$attr] = $row[$attr];
                    }
                }

                if ($boxrow) {
                    $class = 'horde-resource-' . (empty($row['selected']) ? 'off' : 'on');
                    if (($row['type'] ?? '') === 'radiobox') {
                        $class .= ' horde-radiobox';
                    }
                    if (empty($attributes['class'])) {
                        $attributes['class'] = $class;
                    } else {
                        $attributes['class'] .= ' ' . $class;
                    }
                }

                if (is_object($url) && method_exists($url, 'link')) {
                    $row['link'] = $url->link($attributes) . $label . '</a>';
                } else {
                    $attrHtml = '';
                    foreach ($attributes as $k => $v) {
                        $attrHtml .= ' ' . $k . '="' . htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
                    }
                    $row['link'] = '<a href="' . htmlspecialchars((string) $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
                        . $attrHtml . '>' . $label . '</a>';
                }
            } else {
                $row['link'] = '<span class="horde-resource-none">' . $label . '</span>';
            }
        }

        if ($boxrow) {
            $this->containers[$container]['type'] = $row['type'];
            if (!isset($row['style'])) {
                $row['style'] = '';
            }
            if (!isset($row['color'])) {
                $row['color'] = '#dddddd';
            }
            $foreground = self::calculateForeground($row['color']);
            if (strlen($row['style'])) {
                $row['style'] .= ';';
            }
            $row['style'] .= 'background-color:' . htmlspecialchars($row['color'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . ';color:#' . $foreground;

            if (isset($row['edit'])) {
                if (is_object($row['edit']) && method_exists($row['edit'], 'link')) {
                    $row['editLink'] = $row['edit']->link([
                        'title' => 'Edit',
                        'class' => 'horde-resource-edit-' . $foreground,
                    ]) . '&#9658;' . '</a>';
                } else {
                    $row['editLink'] = '<a href="' . htmlspecialchars((string) $row['edit'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                        . '" title="Edit" class="horde-resource-edit-' . $foreground . '">&#9658;</a>';
                }
            }
        }

        $this->containers[$container]['rows'][] = $row;
    }

    /**
     * Converts collected mutable state into an immutable SidebarData.
     *
     * @param int $width Sidebar width in pixels
     * @param bool $isRtl Right-to-left layout
     * @param array<string, string> $cookieData Cookie values for collapsed state overrides (key: container id, value: cookie value)
     */
    public function toSidebarData(int $width = 150, bool $isRtl = false, array $cookieData = []): SidebarData
    {
        $newButton = null;
        if ($this->newLink !== null && $this->newText !== null) {
            $newButton = new SidebarButton(
                label: $this->newText,
                url: $this->newLink,
                extra: $this->newExtra,
            );
        }

        $containers = [];
        foreach ($this->containers as $rawContainer) {
            $header = null;
            if (isset($rawContainer['header'])) {
                $h = $rawContainer['header'];
                $headerId = $h['id'] ?? '';
                $collapsed = !empty($h['collapsed']);

                $cookieKey = 'horde_sidebar_c_' . $headerId;
                if (isset($cookieData[$cookieKey])) {
                    $collapsed = !empty($cookieData[$cookieKey]);
                }

                $addUrl = null;
                $addLabel = null;
                if (isset($h['add'])) {
                    if (is_array($h['add'])) {
                        $addUrl = $h['add']['url'] ?? null;
                        $addLabel = $h['add']['label'] ?? null;
                    }
                }

                $header = new SidebarHeader(
                    id: $headerId,
                    label: $h['label'] ?? '',
                    collapsed: $collapsed,
                    addUrl: $addUrl !== null ? (string) $addUrl : null,
                    addLabel: $addLabel,
                );
            }

            $type = $rawContainer['type'] ?? 'tree';
            $rows = [];
            foreach ($rawContainer['rows'] ?? [] as $rawRow) {
                $rows[] = new SidebarRow(
                    label: $rawRow['label'] ?? '',
                    url: isset($rawRow['url']) ? (string) $rawRow['url'] : '',
                    selected: !empty($rawRow['selected']),
                    type: $rawRow['type'] ?? 'tree',
                    cssClass: $rawRow['cssClass'] ?? null,
                    id: $rawRow['id'] ?? null,
                    color: $rawRow['color'] ?? null,
                    foregroundColor: isset($rawRow['color']) ? '#' . self::calculateForeground($rawRow['color']) : null,
                    editUrl: isset($rawRow['edit']) ? (string) $rawRow['edit'] : null,
                    onclick: $rawRow['onclick'] ?? null,
                    target: $rawRow['target'] ?? null,
                    linkHtml: $rawRow['link'] ?? '',
                    editLinkHtml: $rawRow['editLink'] ?? null,
                    style: $rawRow['style'] ?? null,
                );
            }

            $containers[] = new SidebarContainer(
                id: $rawContainer['id'] ?? null,
                header: $header,
                rows: $rows,
                type: $type,
                content: $rawContainer['content'] ?? null,
            );
        }

        return new SidebarData(
            newButton: $newButton,
            containers: $containers,
            width: $width,
            isRtl: $isRtl,
        );
    }

    /**
     * Calculates foreground color (black or white) based on background brightness.
     *
     * Uses the YIQ brightness formula: round((R*299 + G*587 + B*114) / 1000).
     * Returns '000' for bright backgrounds, 'fff' for dark ones.
     */
    public static function calculateForeground(string $hexColor): string
    {
        $hex = ltrim($hexColor, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $brightness = (int) round(($r * 299 + $g * 587 + $b * 114) / 1000);

        return $brightness < 128 ? 'fff' : '000';
    }
}
