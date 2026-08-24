<?php

/**
 * Copyright 2012-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2012-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

/**
 * This class represents a javascript script file located in a theme's
 * directory.
 *
 * Themes may ship their own scripts alongside their CSS, images and sounds,
 * by listing them in the theme's info.php:
 *
 *   $theme_scripts = array('theme.js');
 *
 * Only plain file names are accepted; they are resolved inside the theme
 * directory. See Horde_Themes_Cache::themeScripts().
 *
 * @category  Horde
 * @copyright 2012-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class Horde_Script_File_ThemeDir extends Horde_Script_File
{
    /**
     * The theme this file belongs to.
     *
     * @var string
     */
    protected $_theme;

    /**
     * @param string $file   The script file name.
     * @param string $theme  The theme name.
     * @param string $app    The application name. Defaults to the current
     *                       application.
     */
    public function __construct($file, $theme, $app = null)
    {
        parent::__construct($file, $app);
        $this->_theme = $theme;
    }

    /**
     */
    public function __get($name)
    {
        switch ($name) {
            case 'path':
                return $GLOBALS['registry']->get('themesfs', $this->_app) .
                    '/' . $this->_theme . '/';

            case 'url':
            case 'url_full':
                return $this->_url(
                    $GLOBALS['registry']->get('themesuri', $this->_app) .
                        '/' . $this->_theme . '/' . $this->_file,
                    ($name == 'url_full')
                );
        }

        return parent::__get($name);
    }
}
