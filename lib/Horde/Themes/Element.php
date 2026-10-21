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

use Horde\Core\Horde;

/**
 * An object-oriented interface to a themes element.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2010-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 *
 * @property-read string $fs  Filesystem location.
 * @property-read string $fulluri  Full URI.
 * @property-read string $uri  Relative URI.
 */
class Horde_Themes_Element
{
    /**
     * Current application name.
     *
     * @var string
     */
    public $app;

    /**
     * URI/filesystem path values.
     *
     * @var array
     */
    protected $_data = [];

    /**
     * The default directory name for this element type.
     *
     * @var string
     */
    protected $_dirname = '';

    /**
     * Element name.
     *
     * @var string
     */
    protected $_name;

    /**
     * Options.
     *
     * @var array
     */
    protected $_opts;

    /**
     * Constructor.
     *
     * @param string $name    The element name. If null, will return the
     *                        element directory.
     * @param array $options  Additional options:
     *   - app: (string) Use this application instead of the current app.
     *   - data: (array) Contains 2 elements: 'fs' - filesystem path,
     *                   'uri' - the element URI. If set, use as the data
     *                   values instead of auto determining.
     *   - nohorde: (boolean) If true, do not fallback to horde for element.
     *   - noview: (boolean) If true, do not load images from view-specific
     *             directories. (Since 2.4.0)
     *   - theme: (string) Use this theme instead of the Horde default.
     *   - uri: (string) Use this as the URI value.
     */
    public function __construct($name = '', array $options = [])
    {
        $this->app = empty($options['app'])
            ? $GLOBALS['registry']->getApp()
            : $options['app'];
        $this->_name = $name;
        $this->_opts = $options;

        if ($GLOBALS['registry']->get('status', $this->app) == 'heading') {
            $this->app = 'horde';
        }

        if (isset($this->_opts['data'])) {
            $this->_data = $this->_opts['data'];
            unset($this->_opts['data']);
        }
    }

    /**
     * String representation of this object.
     *
     * @return string  The relative URI.
     */
    public function __toString()
    {
        try {
            return (string) $this->uri;
        } catch (Exception $e) {
            Horde::log($e, Horde_Log::ERR);
            return '';
        }
    }

    /**
     */
    public function __get($name)
    {
        global $prefs, $registry;

        if (empty($this->_data)) {
            $theme = array_key_exists('theme', $this->_opts)
                ? $this->_opts['theme']
                : $prefs->getValue('theme');

            if (is_null($this->_name)) {
                /* Return directory only. */
                $this->_data = [
                    'fs' => $registry->get('themesfs', $this->app) . '/' . $theme . '/' . $this->_dirname,
                    'uri' => $registry->get('themesuri', $this->app) . '/' . $theme . '/' . $this->_dirname,
                ];
            } else {
                $cache = $GLOBALS['injector']->get(Horde_Core_Factory_ThemesCache::class)->create($this->app, $theme);
                $mask = empty($this->_opts['nohorde'])
                    ? 0
                    : Horde_Themes_Cache::APP_DEFAULT | Horde_Themes_Cache::APP_THEME;
                if (empty($this->_opts['noview'])) {
                    $mask |= Horde_Themes_Cache::VIEW;
                }

                $this->_data = $cache->get((strlen($this->_dirname) ? $this->_dirname . '/' : '') . $this->_name, $mask);
            }
        }
        /* Guard: Cache does not have this element, non-array returned */
        if (!is_array($this->_data)) {
            return null;
        }
        switch ($name) {
            case 'fs':
            case 'uri':
                return $this->_data[$name];

            case 'fulluri':
                return Horde::url($this->_data['uri'], true);

            default:
                return null;
        }
    }

    /**
     * Convert a URI into a Horde_Themes_Element object.
     *
     * The URI is first matched against the themes, javascript and static
     * locations of the registered applications, since these may be served
     * outside of the Horde webroot (e.g. /themes/horde in a composer
     * installation). Otherwise, the URI is resolved against the Horde
     * fileroot.
     *
     * @param string $uri  The URI to convert.
     *
     * @return Horde_Themes_Element  A theme element object.
     */
    public static function fromUri($uri)
    {
        global $registry;

        $uri = (string) $uri;
        $fs = null;
        $match = 0;

        foreach ($registry->listAllApps() as $app) {
            foreach (['themes', 'js', 'static'] as $type) {
                $base_uri = rtrim((string) $registry->get($type . 'uri', $app), '/');
                $base_fs = $registry->get($type . 'fs', $app);
                if (!strlen($base_uri)
                    || !strlen((string) $base_fs)
                    || (strlen($base_uri) <= $match)
                    || !str_starts_with($uri, $base_uri . '/')) {
                    continue;
                }
                $fs = rtrim($base_fs, '/') . substr($uri, strlen($base_uri));
                $match = strlen($base_uri);
            }
        }

        if (is_null($fs)) {
            $fileroot = $registry->get('fileroot', 'horde');
            $webroot = rtrim((string) $registry->get('webroot', 'horde'), '/');
            $fs = realpath($fileroot ?? '') . '/'
                . ltrim(preg_replace('/^' . preg_quote($webroot, '/') . '(?=\/)/', '', $uri), '/');
        }

        return new self('', [
            'data' => [
                'fs' => $fs,
                'uri' => $uri,
            ],
        ]);
    }

}
