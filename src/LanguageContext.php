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

namespace Horde\Core;

/**
 * Owner of the request's display language / locale.
 *
 * Replaces reads of $GLOBALS['language'] with an injectable service for READING.
 *
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
interface LanguageContext
{
    /**
     * The currently resolved display language (e.g. 'de_DE').
     *
     * Resolves and caches via preferredLang() on first access if
     * setLanguage() has not been called yet.
     */
    public function getLanguage(): string;

    /**
     * The ICU-compatible locale identifier for the current language.
     *
     * Currently identical to getLanguage(); kept as a distinct method
     * so callers expressing an ICU/locale need (e.g. date formatting)
     * are not coupled to the language-cascade wording.
     */
    public function getLocale(): string;

    /**
     * Run the language cascade without persisting anything.
     *
     * @param string|null $lang  Explicit candidate language (e.g. from
     *                           a login-screen selector), used only if
     *                           no session/preference value exists.
     *
     * @return string  The selected language abbreviation.
     */
    public function preferredLang(?string $lang = null): string;

    /**
     * Resolve (via preferredLang()) and persist the language for this
     * request (writes the 'horde' / 'language' session scope).
     *
     * @return string  The resolved, now-current language.
     */
    public function setLanguage(?string $lang = null): string;

    /**
     * Whether $lang is a configured, selectable language.
     */
    public function validLang(string $lang): bool;
}
