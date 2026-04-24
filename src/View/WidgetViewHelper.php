<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Core\View;

use Horde\Url\Url;
use Horde_Serialize;
use Horde_Url;
use Horde_View_Helper_Base;

/**
 * View helper that produces access-key-aware anchor and label elements.
 *
 * Replaces the static Horde::widget() and Horde::label() methods with
 * an injectable, testable helper that can be called from templates as
 * `$this->hordeWidget(...)`.
 */
class WidgetViewHelper extends Horde_View_Helper_Base
{
    private AccessKeyTracker $accessKeys;

    public function __construct($view, AccessKeyTracker $accessKeys)
    {
        $this->accessKeys = $accessKeys;
        parent::__construct($view);
    }

    /**
     * Produce an `<a href="...">Title</a>` with access key support.
     *
     * @param Url|Horde_Url|string  $url        Link target.
     * @param string                $title      Label with optional `_X` access key marker.
     * @param string                $class      CSS class.
     * @param string                $target     Link target window.
     * @param string                $onclick    Onclick handler.
     * @param bool                  $nocheck    Skip duplicate-key check.
     * @param array<string, string> $attributes Extra HTML attributes.
     */
    public function hordeWidget(
        Url|Horde_Url|string $url,
        string $title,
        string $class = '',
        string $target = '',
        string $onclick = '',
        bool $nocheck = false,
        array $attributes = [],
    ): string {
        $accessKey = $this->accessKeys->acquire($title, $nocheck);

        $attrs = $attributes;
        if ($class !== '') {
            $attrs['class'] = $class;
        }
        if ($target !== '') {
            $attrs['target'] = $target;
        }
        if ($onclick !== '') {
            $attrs['onclick'] = $onclick;
        }
        if ($accessKey !== '') {
            $attrs['accesskey'] = $accessKey;
        }

        $label = $this->accessKeys->highlight($title, $accessKey);

        if (is_string($url)) {
            $url = new Url($url);
        }

        return $url->link($attrs) . $label . '</a>';
    }

    /**
     * Conditional widget — returns the anchor when $condition is true,
     * empty string otherwise.
     */
    public function hordeWidgetIf(
        bool $condition,
        Url|Horde_Url|string $url,
        string $title,
        string $class = '',
        string $target = '',
        string $onclick = '',
        bool $nocheck = false,
        array $attributes = [],
    ): string {
        if (!$condition) {
            return '';
        }

        return $this->hordeWidget($url, $title, $class, $target, $onclick, $nocheck, $attributes);
    }

    /**
     * Produce a `<label>` element with access key support.
     */
    public function hordeLabel(
        string $for,
        string $label,
        ?string $ak = null,
    ): string {
        if ($ak === null) {
            $ak = $this->accessKeys->acquire($label, true);
        }

        $text = $this->accessKeys->highlight($label, $ak);

        return sprintf(
            '<label for="%s"%s>%s</label>',
            $for,
            $ak !== '' ? ' accesskey="' . $ak . '"' : '',
            $text,
        );
    }

    /**
     * Open an `<a>` tag with title escaping.
     *
     * Produces the opening tag only — the caller appends content and
     * `</a>`.  Title values are escaped to handle newlines and
     * pre-existing HTML entities safely.
     */
    public function hordeLink(
        Url|Horde_Url|string $url = '',
        string $title = '',
        string $class = '',
        string $target = '',
        string $onclick = '',
        string $accesskey = '',
        array $attributes = [],
        bool $escape = true,
    ): string {
        if (is_string($url)) {
            $url = new Url($url);
        }

        if ($onclick !== '') {
            $attributes['onclick'] = $onclick;
        }
        if ($class !== '') {
            $attributes['class'] = $class;
        }
        if ($target !== '') {
            $attributes['target'] = $target;
        }
        if ($accesskey !== '') {
            $attributes['accesskey'] = $accesskey;
        }
        if ($title !== '') {
            if ($escape) {
                $title = str_replace(
                    ["\r", "\n"],
                    '',
                    htmlspecialchars(nl2br(htmlspecialchars($title))),
                );
                $title = preg_replace('/&amp;([a-z]+|(#\d+));/i', '&\\1;', $title);
            }
            $attributes['title.raw'] = $title;
        }

        return $url->link($attributes);
    }

    /**
     * Open an `<a>` tag with a DOM tooltip.
     *
     * Serializes the title into a `nicetitle` JSON attribute for the
     * Horde tooltip JS.  The controller is responsible for loading
     * `tooltips.js` — this method produces HTML only.
     */
    public function hordeLinkTooltip(
        Url|Horde_Url|string $url,
        string $class = '',
        string $target = '',
        string $onclick = '',
        string $title = '',
        string $accesskey = '',
        array $attributes = [],
    ): string {
        if ($title !== '') {
            $attributes['nicetitle'] = Horde_Serialize::serialize(
                preg_split(
                    '/\r?\n/',
                    preg_replace('/<br\s*\/?\s*>/', "\n", $title),
                ),
                Horde_Serialize::JSON,
            );
            $title = '';
        }

        return $this->hordeLink(
            $url,
            $title,
            $class,
            $target,
            $onclick,
            $accesskey,
            $attributes,
            false,
        );
    }
}
