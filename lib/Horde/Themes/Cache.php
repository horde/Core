<?php
/**
 * Copyright 2010-2017 Horde LLC (http://www.horde.org/)
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
    const HORDE_DEFAULT = 1;
    const APP_DEFAULT = 2;
    const HORDE_THEME = 4;
    const APP_THEME = 8;
    const VIEW = 16;

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
    protected $_data = array();

    /**
     * Theme name.
     *
     * @var string
     */
    protected $_theme;

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
            $this->_data = array();

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
        $path = $GLOBALS['registry']->get('themesfs', $app) . '/'. $theme;

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
        return array(
            'app' => $app,
            'fs' => $GLOBALS['registry']->get('themesfs', $app) . '/' . $theme . '/' . $item,
            'uri' => $GLOBALS['registry']->get('themesuri', $app) . '/' . $theme . '/' . $item
        );
    }

    /**
     */
    public function getAll($item, $mask = 0)
    {
        if (!($entry = $this->_get($item))) {
            return array();
        }

        if ($mask) {
            $entry &= $mask;
        }
        $out = array();

        if ($entry & self::APP_THEME) {
            $out[] = $this->_getOutput($this->_app, $this->_theme, $item);
        }
        if ($entry & self::HORDE_THEME) {
            $out[] = $this->_getOutput('horde', $this->_theme, $item);
        }
        if (($this->_theme != 'default') && $entry & self::APP_DEFAULT) {
            $out[] = $this->_getOutput($this->_app, 'default', $item);
        }
        if (($this->_theme != 'default') && $entry & self::HORDE_DEFAULT) {
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
            $check = isset($conf['cachethemesparams']['check'])
                ? $conf['cachethemesparams']['check']
                : null;

            switch ($check) {
            case 'appversion':
            default:
                $id = array($registry->getVersion($this->_app));
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
        return array(
            'a' => $this->_app,
            'c' => $this->_complete,
            'd' => $this->_data,
            'id' => $this->getCacheId(),
            't' => $this->_theme
        );
        
    }

    public function __unserialize(array $data): void
    {

        // Needed to generate cache ID.
        if (isset($data['a'])) {
                $this->_app = $data['a'];
        }

        if (isset($data['id']) && ($data['id'] != $this->getCacheId())) {
            throw new Exception('Cache invalidated for ' . $data['a'] . ': ' . $data['id'] . " != ".$this->getCacheId());
        }

        $this->_complete = $data['c'];
        $this->_data = $data['d'];
        $this->_theme = $data['t'];    
    }
    /**
     */
    public function unserialize($data)
    {
        $this->__unserialize(@unserialize($data));
    }
}
