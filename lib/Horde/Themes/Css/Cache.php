<?php

/**
 * Copyright 2014-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2014-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

/**
 * Object handling storage of cached CSS data.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2014-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 * @since     2.12.0
 */
abstract class Horde_Themes_Css_Cache
{
    /**
     * Configuration parameters.
     *
     * @var array
     */
    protected $_params;

    /**
     * Constructor.
     *
     * @param array $params  Configuration parameters.
     */
    public function __construct(array $params = [])
    {
        $this->_params = $params;
        if (!rand(0, 999)) {
            $this->gc();
        }
    }

    /**
     * Process a list of CSS files.
     *
     * @param array $css       See Horde_Themes_Css#getStylesheets().
     * @param string $cacheid  Cache ID.
     *
     * @return array  The list of URLs to display (Horde_Url objects).
     */
    abstract public function process($css, $cacheid);

    /**
     * Split off the stylesheets that are not local files (e.g. a CDN URL
     * added by the cssfiles hook). These cannot be compressed, so they are
     * linked as is, before the cached file. An absolute URL backed by a
     * readable local file is kept in the cached file.
     *
     * @param array $css  See Horde_Themes_Css#getStylesheets().
     *
     * @return array  Two elements: the list of external URLs (Horde_Url
     *                objects) and the list of local stylesheets.
     */
    protected function _splitExternal($css)
    {
        $external = $local = [];

        foreach ($css as $val) {
            if (isset($val['uri'])
                && preg_match('#^https?://#i', (string) $val['uri'])
                && !(isset($val['fs']) && is_readable($val['fs']))) {
                $external[] = Horde::url($val['uri'], true, -1);
            } else {
                $local[] = $val;
            }
        }

        return [$external, $local];
    }

    /**
     * Perform garbage collection.
     */
    public function gc() {}

}
