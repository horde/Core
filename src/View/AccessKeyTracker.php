<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Core\View;

use Horde\Injector\Attribute\Factory;
use Horde\Util\StringTransliterate;
use Horde_Core_Translation;

/**
 * Per-request access key allocator.
 *
 * Assigns keyboard shortcuts from `_X`-marked labels, tracks which
 * keys have already been used on the current page, and produces the
 * HTML fragments that highlight the chosen key in the label text.
 */
#[Factory(factory: AccessKeyTrackerFactory::class, method: 'create')]
class AccessKeyTracker
{
    private array $used = [];

    private array $labels = [];

    public function __construct(
        private readonly bool $accessKeysEnabled = true,
        private readonly bool $multibyte = false,
    ) {}

    /**
     * Acquire an unused access key from a label.
     *
     * Labels mark the preferred key with an underscore: `_Edit` → `e`.
     * Once a key is used, subsequent labels requesting the same key
     * get an empty string (unless $nocheck is true AND the same label
     * was already registered).
     */
    public function acquire(string $label, bool $nocheck = false): string
    {
        if (!$this->accessKeysEnabled) {
            return '';
        }

        if (!preg_match('/_(\w)/u', $label, $match)) {
            return '';
        }

        $key = StringTransliterate::toAscii($match[1]);

        if (isset($this->used[strtolower($key)])
            && !($nocheck && isset($this->labels[$label]))) {
            return '';
        }

        $this->used[strtolower($key)] = true;
        $this->labels[$label] = true;

        return $key;
    }

    /**
     * Strip the `_X` access key marker from a label.
     *
     * In multibyte locales, if the label contains high bytes the marker
     * and its letter are removed entirely (CJK characters cannot serve
     * as access key mnemonics).  In single-byte locales the underscore
     * is removed but the letter is kept.
     */
    public function strip(string $label): string
    {
        $replace = $this->multibyte && preg_match('/[\x80-\xff]/', $label)
            ? ''
            : '$1';

        return preg_replace('/_(\w)/u', $replace, $label);
    }

    /**
     * Highlight the access key letter in a label with an HTML span.
     *
     * In multibyte locales the key is appended after the label as
     * `(X)` because the letter may not appear in the label text.
     */
    public function highlight(string $label, string $accessKey): string
    {
        $stripped = $this->strip($label);

        if ($accessKey === '') {
            return $stripped;
        }

        if ($this->multibyte) {
            return $stripped . "\xe2\x80\xad"
                . '(<span class="accessKey">' . strtoupper($accessKey)
                . '</span>)';
        }

        return preg_replace(
            '/_(\w)/u',
            '<span class="accessKey">$1</span>',
            $label,
        );
    }

    /**
     * Get access key and title attributes for an HTML element.
     *
     * @return array{title: string, accesskey?: string}
     */
    public function getAccessKeyAndTitle(
        string $label,
        bool $nocheck = false,
    ): array {
        $ak = $this->acquire($label, $nocheck);
        $attributes = ['title' => $this->strip($label)];

        if ($ak !== '') {
            $attributes['title'] .= sprintf(
                Horde_Core_Translation::t(' (Accesskey %s)'),
                strtoupper($ak),
            );
            $attributes['accesskey'] = $ak;
        }

        return $attributes;
    }

    /**
     * Clear all per-page state.
     */
    public function reset(): void
    {
        $this->used = [];
        $this->labels = [];
    }
}
