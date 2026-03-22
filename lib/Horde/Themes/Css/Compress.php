<?php

/**
 * Copyright 2014-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2014-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

use Horde\CssMinify\CssParserMinifier;
use Horde\CssMinify\ImportCallback;
use Horde\CssMinify\Input\CssFile;
use Horde\CssMinify\Input\FileCollectionInput;
use Horde\CssMinify\Settings;
use Horde\CssMinify\UrlCallback;
use Horde\Log\Logger;
use Psr\Log\LoggerInterface;

/**
 * Compresses CSS based on Horde configuration parameters.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2014-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 * @since     2.12.0
 */
class Horde_Themes_Css_Compress
{
    /**
     * Loads CSS files, cleans up the input (and compresses), and concatenates
     * to a string.
     *
     * @param array $css  See Horde_Themes_Css#getStylesheets().
     * @param LoggerInterface|null $logger  Optional PSR-3 logger for error reporting.
     *
     * @return string  CSS data.
     */
    public function compress($css, ?LoggerInterface $logger = null)
    {
        global $browser, $conf, $injector;

        $files = [];
        foreach ($css as $val) {
            if (!isset($val['uri']) || !isset($val['fs'])) {
                continue;
            }
            try {
                $files[] = new CssFile((string) $val['uri'], (string) $val['fs']);
            } catch (\InvalidArgumentException $e) {
                // Skip unreadable files
                if ($logger !== null) {
                    $logger->warning(
                        'Skipping unreadable CSS file: {file}',
                        ['file' => $val['fs'] ?? 'unknown', 'exception' => $e->getMessage()]
                    );
                }
                continue;
            }
        }

        if (empty($files)) {
            return '';
        }

        $dataUrlCallback = null;
        if (empty($conf['nobase64_img']) && $browser->hasFeature('dataurl')) {
            $dataUrlCallback = new UrlCallback([$this, 'dataurlCallback']);
        }

        // Use provided logger or attempt to get from injector
        if ($logger === null) {
            try {
                $logger = $injector->get(Logger::class);
            } catch (\Exception $e) {
                // Logger not available, continue without logging
                $logger = null;
            }
        }

        $minifier = new CssParserMinifier(
            new FileCollectionInput(...$files),
            new Settings(
                dataUrlCallback: $dataUrlCallback,
                importCallback: new ImportCallback([$this, 'importCallback']),
                logger: $logger
            )
        );

        return $minifier->minify();
    }

    /**
     */
    public function dataurlCallback($uri)
    {
        /* Limit data to 16 KB in stylesheets. */
        return Horde_Themes_Image::base64ImgData($uri, 16384);
    }

    /**
     */
    public function importCallback($uri)
    {
        $ob = Horde_Themes_Element::fromUri($uri);
        return [$ob->uri, $ob->fs];
    }

}
