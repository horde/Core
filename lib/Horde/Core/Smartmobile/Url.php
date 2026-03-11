<?php

/**
 * Extend the base URL class to allow for use with the URL parameter scheme
 * used in Horde's smartmobile framework.
 *
 * Copyright 2012-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Michael Slusarz <slusarz@horde.org>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @category Horde
 * @package  Core
 */

/**
 * Extend the base URL class to allow for use with the URL parameter scheme
 * used in Horde's smartmobile framework.
 *
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @package  Core
 */
class Horde_Core_Smartmobile_Url extends \Horde\Url\Url
{
    /**
     * The URL used as the base for the smartmobile anchor.
     *
     * @var \Horde\Url\Url
     */
    protected $_baseUrl;

    /**
     * Constructor.
     *
     * @param \Horde\Url\Url|Horde_Url|string|null $url  The basic URL.
     * @param bool|null $raw                              Whether to output the URL in raw format or HTML-encoded.
     */
    public function __construct(\Horde\Url\Url|Horde_Url|string|null $url = null, ?bool $raw = null)
    {
        if ($url === null) {
            $url = new \Horde\Url\Url();
        } elseif ($url instanceof Horde_Url) {
            // Extract the modern instance from the legacy wrapper
            $url = new \Horde\Url\Url((string)$url, $raw);
            // Copy parameters from the wrapper
            foreach ((array)$url->parameters as $key => $value) {
                $url->add($key, $value);
            }
        } elseif (is_string($url)) {
            $url = new \Horde\Url\Url($url, $raw);
        } elseif (!($url instanceof \Horde\Url\Url)) {
            throw new InvalidArgumentException('First argument must be a URL object or string');
        }

        $query = '';

        /* Smartmobile URLs carry around query information in fragment, so
         * copy any information found in the incoming URL. */
        if (strlen($url->anchor)) {
            $anchor = parse_url($url->anchor);
            if (isset($anchor['query'])) {
                $this->anchor = $anchor['path'];
                $query = '?' . $anchor['query'];
            } else {
                $this->anchor = $url->anchor;
            }
            $url->anchor = '';
        }

        $this->_baseUrl = $url;
        parent::__construct($query, $raw);
    }

    /**
     * Creates the full URL string.
     *
     * @param bool $raw   Whether to output the URL in the raw URL format or HTML-encoded.
     * @param bool $full  Output the full URL?
     *
     * @return string  The string representation of this object.
     */
    public function toString(bool $raw = false, bool $full = true): string
    {
        if ($this->toStringCallback || !strlen($this->anchor)) {
            $baseUrl = clone $this->_baseUrl;
            $baseUrl->parameters = array_merge(
                $baseUrl->parameters,
                $this->parameters
            );
            if (strlen($this->pathInfo)) {
                $baseUrl->pathInfo = $this->pathInfo;
            }
            return $baseUrl->toString($raw, $full);
        }

        $url = $this->_baseUrl->toString($raw, $full);

        if (strlen($this->pathInfo)) {
            $url = rtrim($url, '/');
            $url .= '/' . $this->pathInfo;
        }

        if ($this->anchor) {
            $url .= '#' . ($raw ? $this->anchor : rawurlencode($this->anchor));
        }

        if ($params = $this->parameters) {
            $url .= '?' . http_build_query($params, '', $raw ? '&' : '&amp;');
        }

        return $url;
    }

    /**
     * Magic __toString method for PHP 8+ compatibility
     */
    public function __toString(): string
    {
        return $this->toString($this->raw ?? false);
    }
}
