<?php

/**
 * Copyright 2010-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2010-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

/**
 * This class is responsible for parsing/building theme elements and then
 * caching these results.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2010-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class Horde_Themes_Cache implements Serializable
{
    /* Constants */
    public const HORDE_DEFAULT = 1;
    public const APP_DEFAULT = 2;
    public const HORDE_THEME = 4;
    public const APP_THEME = 8;
    public const VIEW = 16;

    /**
     * Has the data changed?
     *
     * @var boolean
     */
    public $changed = false;

    /**
     * Application name.
     *
     * @var string
     */
    protected $_app;

    /**
     * The cache ID.
     *
     * @var string
     */
    protected $_cacheid;

    /**
     * Is this a complete representation of the theme?
     *
     * @var boolean
     */
    protected $_complete = false;

    /**
     * Theme data.
     *
     * @var array
     */
    protected $_data = [];

    /**
     * Theme name.
     *
     * @var string
     */
    protected $_theme;

    /**
     * Cached list of apps the theme declares it covers completely (lower-cased,
     * 'horde' meaning the shell). Read from the theme's info.php.
     *
     * @var string[]
     */
    protected $_covers;

    /**
     * Cached list of script files the theme ships. Read from the theme's
     * info.php.
     *
     * @var string[]
     */
    protected $_scripts;

    /**
     * Cached declarations read from the theme's info.php.
     *
     * @var array
     */
    protected $_info;

    /**
     * Constructor.
     *
     * @param string $app    The application name.
     * @param string $theme  The theme name.
     */
    public function __construct($app, $theme)
    {
        $this->_app = $app;
        $this->_theme = $theme;
    }

    /**
     * Build the entire theme data structure.
     *
     * @return array  The list of theme files.
     */
    public function build()
    {
        if (!$this->_complete) {
            $this->_data = [];

            $this->_build('horde', 'default', self::HORDE_DEFAULT);
            $this->_build('horde', $this->_theme, self::HORDE_THEME);
            if ($this->_app != 'horde') {
                $this->_build($this->_app, 'default', self::APP_DEFAULT);
                $this->_build($this->_app, $this->_theme, self::APP_THEME);
            }

            $this->changed = $this->_complete = true;
        }

        return array_keys($this->_data);
    }

    /**
     * Add theme data from an app/theme combo.
     *
     * @param string $app    The application name.
     * @param string $theme  The theme name.
     * @param integer $mask  Mask for the app/theme combo.
     */
    protected function _build($app, $theme, $mask)
    {
        $path = $GLOBALS['registry']->get('themesfs', $app) . '/' . $theme;

        try {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        } catch (UnexpectedValueException $e) {
            return;
        }

        foreach ($it as $val) {
            if (!$val->isDir()) {
                $sub = $it->getSubPathname();

                if (isset($this->_data[$sub])) {
                    $this->_data[$sub] |= $mask;
                } else {
                    $this->_data[$sub] = $mask;
                }
            }
        }
    }

    /**
     */
    public function get($item, $mask = 0)
    {
        if ($mask & self::VIEW) {
            $item_dir = Horde_Themes::viewDir($GLOBALS['registry']->getView()) . '/' . $item;
            $mask &= ~self::VIEW;

            if (!is_null($out = $this->get($item_dir, $mask))) {
                return $out;
            }
        }

        if (!($entry = $this->_get($item))) {
            return null;
        }

        if ($mask) {
            $entry &= $mask;
        }

        if ($entry & self::APP_THEME) {
            $app = $this->_app;
            $theme = $this->_theme;
        } elseif ($entry & self::HORDE_THEME) {
            $app = 'horde';
            $theme = $this->_theme;
        } elseif ($entry & self::APP_DEFAULT) {
            $app = $this->_app;
            $theme = 'default';
        } elseif ($entry & self::HORDE_DEFAULT) {
            $app = 'horde';
            $theme = 'default';
        } else {
            return null;
        }

        return $this->_getOutput($app, $theme, $item);
    }

    /**
     */
    protected function _get($item)
    {
        if (!isset($this->_data[$item])) {
            $entry = 0;

            $path = $GLOBALS['registry']->get('themesfs', 'horde');
            if (file_exists($path . '/default/' . $item)) {
                $entry |= self::HORDE_DEFAULT;
            }
            if (file_exists($path . '/' . $this->_theme . '/' . $item)) {
                $entry |= self::HORDE_THEME;
            }

            if ($this->_app != 'horde') {
                $path = $GLOBALS['registry']->get('themesfs', $this->_app);
                if (file_exists($path . '/default/' . $item)) {
                    $entry |= self::APP_DEFAULT;
                }
                if (file_exists($path . '/' . $this->_theme . '/' . $item)) {
                    $entry |= self::APP_THEME;
                }
            }

            $this->_data[$item] = $entry;
            $this->changed = true;
        }

        return $this->_data[$item];
    }

    /**
     */
    protected function _getOutput($app, $theme, $item)
    {
        return [
            'app' => $app,
            'fs' => $GLOBALS['registry']->get('themesfs', $app) . '/' . $theme . '/' . $item,
            'uri' => $GLOBALS['registry']->get('themesuri', $app) . '/' . $theme . '/' . $item,
        ];
    }

    /**
     * Returns the list of "apps" (including the special 'horde' shell) that
     * the current theme declares it covers completely.
     *
     * A theme opts out of the default-theme CSS fallback on a per-app basis by
     * listing those apps in its info.php:
     *
     *   $theme_covers = array('horde', 'imp', 'kronolith');
     *
     * For a covered app the default-theme CSS is not loaded underneath the
     * theme. Apps that are NOT listed keep inheriting their own default theme
     * CSS, so a third-party app that ships its own default/ assets still works.
     *
     * Themes without the declaration inherit everything, exactly as before.
     *
     * @return string[]  Lower-cased app names fully covered by the theme.
     */
    protected function _coveredApps()
    {
        if (!isset($this->_covers)) {
            $info = $this->_themeInfo();
            $this->_covers = array_map(
                'strtolower',
                (array) ($info['theme_covers'] ?? [])
            );
        }

        return $this->_covers;
    }

    /**
     * Returns the declarations made by the current theme's info.php.
     *
     * @return array  The variables the theme declares, keyed by name. Empty if
     *                the theme has no info.php (or an unsafe name).
     */
    protected function _themeInfo()
    {
        if (isset($this->_info)) {
            return $this->_info;
        }

        $theme_covers = [];
        $theme_scripts = [];

        /* Theme names originate from user prefs/options, so guard against
         * path traversal: only include info.php for a plain directory name
         * (no separators, no '..'). Anything else declares nothing. */
        if (preg_match('/^[A-Za-z0-9_-]+$/', (string) $this->_theme)) {
            global $registry;
            $info = $registry->get('themesfs', 'horde') . '/' . $this->_theme . '/info.php';
            if (is_readable($info)) {
                include $info;
            }
        }

        $this->_info = [
            'theme_covers' => $theme_covers,
            'theme_scripts' => $theme_scripts,
        ];

        return $this->_info;
    }

    /**
     * Returns the javascript files the current theme ships.
     *
     * Themes may ship scripts alongside their CSS, images and sounds, by
     * listing them in info.php:
     *
     *   $theme_scripts = array('theme.js');
     *
     * Only plain file names are accepted, and they are resolved inside the
     * theme directory: a theme cannot pull in a script from an arbitrary path
     * or an external host. Files that do not exist are skipped.
     *
     * Themes without the declaration ship no script, exactly as before.
     *
     * @return string[]  Script file names, relative to the theme directory.
     */
    public function themeScripts()
    {
        if (!isset($this->_scripts)) {
            $info = $this->_themeInfo();
            $base = $GLOBALS['registry']->get('themesfs', 'horde') .
                '/' . $this->_theme . '/';
            $this->_scripts = [];

            foreach ((array) ($info['theme_scripts'] ?? []) as $script) {
                /* Plain file names only: no directory separators, no '..'.
                 * The theme directory is the only place a script may come
                 * from. */
                if (!preg_match('/^[A-Za-z0-9_.-]+\.js$/', (string) $script) ||
                    strpos($script, '..') !== false) {
                    continue;
                }
                if (is_readable($base . $script)) {
                    $this->_scripts[] = $script;
                }
            }
        }

        return $this->_scripts;
    }

    /**
     * Whether the theme covers the given app (so the default-theme CSS
     * fallback for that app should be skipped).
     *
     * @param string $app  The app name ('horde' for the shell).
     *
     * @return boolean  True if the theme fully covers $app.
     */
    protected function _covers($app)
    {
        return in_array(strtolower($app), $this->_coveredApps(), true);
    }

    /**
     */
    public function getAll($item, $mask = 0)
    {
        if (!($entry = $this->_get($item))) {
            return [];
        }

        if ($mask) {
            $entry &= $mask;
        }
        $out = [];

        if ($entry & self::APP_THEME) {
            $out[] = $this->_getOutput($this->_app, $this->_theme, $item);
        }
        if ($entry & self::HORDE_THEME) {
            $out[] = $this->_getOutput('horde', $this->_theme, $item);
        }
        /* Load the default-theme CSS as a fallback, unless the theme declares
         * it fully covers this app (app-level) or the horde shell. Apps not
         * listed in the theme's $theme_covers keep their default fallback. */
        if (($entry & self::APP_DEFAULT) && ($this->_theme != 'default') && !$this->_covers($this->_app)) {
            $out[] = $this->_getOutput($this->_app, 'default', $item);
        }
        if (($entry & self::HORDE_DEFAULT) && ($this->_theme != 'default') && !$this->_covers('horde')) {
            $out[] = $this->_getOutput('horde', 'default', $item);
        }

        return $out;
    }

    /**
     */
    public function getCacheId()
    {
        global $conf, $registry;

        if (!isset($this->_cacheid)) {
            $check = $conf['cachethemesparams']['check']
                ?? null;

            switch ($check) {
                case 'appversion':
                default:
                    $id = [$registry->getVersion($this->_app)];
                    if ($this->_app != 'horde') {
                        $id[] = $registry->getVersion('horde');
                    }
                    $this->_cacheid = 'v:' . implode('|', $id);
                    break;

                case 'none':
                    $this->_cacheid = '';
                    break;
            }
        }

        return $this->_cacheid;
    }

    /* Serializable methods. */

    /**
     */
    public function serialize()
    {
        return serialize($this->__serialize());
    }

    public function __serialize(): array
    {
        return [
            'a' => $this->_app,
            'c' => $this->_complete,
            'd' => $this->_data,
            'id' => $this->getCacheId(),
            't' => $this->_theme,
        ];

    }

    public function __unserialize(array $data): void
    {

        // Needed to generate cache ID.
        if (isset($data['a'])) {
            $this->_app = $data['a'];
        }

        if (isset($data['id']) && ($data['id'] != $this->getCacheId())) {
            throw new Exception('Cache invalidated for ' . $data['a'] . ': ' . $data['id'] . ' != ' . $this->getCacheId());
        }

        $this->_complete = $data['c'];
        $this->_data = $data['d'];
        $this->_theme = $data['t'];
    }
    /**
     */
    public function unserialize($data)
    {
        $this->__unserialize(@unserialize($data, ['allowed_classes' => false]));
    }
}
