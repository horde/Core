<?php

use Horde\Util\Util;

/**
 * Copyright 1999-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @author   Jon Parise <jon@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL
 * @package  Core
 */

/**
 * Provides the base functionality shared by all Horde applications.
 *
 * Methods in this class delegate to the namespaced {@see Horde\Core\Horde}
 * wherever possible.  Callers should migrate to the namespaced class directly.
 *
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @author   Jon Parise <jon@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL
 * @package  Core
 */
class Horde
{
    public const SSL_NEVER        = 0;
    public const SSL_ALWAYS       = 1;
    public const SSL_AUTO         = 2;
    public const SSL_ONLY_LOGIN   = 3;
    // Static state ($_bufferLevel, $_contentSent, $_labels,
    // $_noAccessKey, $_used) lives in \Horde\Core\Horde.

    /**
     * Shortcut to logging method.
     *
     * @see Horde_Core_Log_Logger
     */
    public static function log(
        $event,
        $priority = null,
        array $options = []
    ) {
        $options['trace'] = isset($options['trace'])
            ? ($options['trace'] + 1)
            : 1;
        $log_ob = new Horde_Core_Log_Object($event, $priority, $options);

        /* Chicken/egg: we must wait until we have basic framework setup
         * before we can start logging. Otherwise, queue entries. */
        if (isset($GLOBALS['injector'])
            && Horde_Core_Factory_Logger::available()) {
            $GLOBALS['injector']->getInstance('Horde_Log_Logger')->logObject($log_ob);
        } else {
            Horde_Core_Factory_Logger::queue($log_ob);
        }
    }

    /**
     * Shortcut to logging method.
     *
     * @deprecated Use log() instead
     * @see log()
     */
    public static function logMessage(
        $event,
        $priority = null,
        array $options = []
    ) {
        $options['trace'] = isset($options['trace'])
            ? ($options['trace'] + 1)
            : 1;
        self::log($event, $priority, $options);
    }

    /**
     * Debug method.  Allows quick shortcut to produce debug output into a
     * temporary file.
     *
     * @param mixed $event        Item to log.
     * @param string $fname       Filename to log to. If empty, logs to
     *                            'horde_debug.txt' in the PHP temporary
     *                            directory.
     * @param boolean $backtrace  Include backtrace information?
     */
    public static function debug(
        $event = null,
        $fname = null,
        $backtrace = true
    ) {
        if (is_null($fname)) {
            $fname = self::getTempDir() . '/horde_debug.txt';
        }

        try {
            $logger = new Horde_Log_Logger(new Horde_Log_Handler_Stream($fname));
        } catch (Exception $e) {
            return;
        }

        $html_ini = ini_set('html_errors', 'Off');
        self::startBuffer();
        if (!is_null($event)) {
            echo "Variable information:\n";
            var_dump($event);
            echo "\n";
        }

        if (is_resource($event)) {
            echo "Stream contents:\n";
            rewind($event);
            fpassthru($event);
            echo "\n";
        }

        if ($backtrace) {
            echo "Backtrace:\n";
            echo strval(new Horde_Support_Backtrace());
        }

        $logger->log(self::endBuffer(), Horde_Log::DEBUG);
        ini_set('html_errors', $html_ini);
    }

    /**
     * Adds a signature + timestamp to a URL and returns the signed URL.
     *
     * @since Horde_Core 2.30.0
     *
     * @param string|Horde_Url $url  The URL to sign.
     * @param integer $now           The timestamp at which to sign. Leave
     *                               blank for generating signatures; specify
     *                               when testing.
     *
     * @return string|Horde_Url  The signed URL.
     */
    public static function signUrl($url, $now = null)
    {
        global $conf;

        if (!isset($conf['secret_key'])) {
            return $url;
        }

        if (is_null($now)) {
            $now = time();
        }

        if ($url instanceof Horde_Url) {
            $url->setRaw(true)->add(['_t' => $now, '_h' => '']);
            $url->add(
                '_h',
                Horde_Url::uriB64Encode(
                    hash_hmac('sha1', $url . '=', $conf['secret_key'], true)
                )
            );
            return $url;
        }

        if (!$url) {
            return $url;
        }

        if (strpos($url, '?')) {
            $url .= '&';
        } else {
            $url .= '?';
        }
        $url .= '_t=' . $now . '&_h=';
        $url .= Horde_Url::uriB64Encode(
            hash_hmac('sha1', $url, $conf['secret_key'], true)
        );

        return $url;
    }

    /**
     * Verifies a signature and timestamp on a URL.
     *
     * @deprecated Use {@see Horde\Core\Horde::verifySignedUrl()} instead.
     *
     * @since Horde_Core 2.30.0
     *
     * @param string $data  The signed URL.
     * @param integer $now  The current time (can override for testing).
     *
     * @return string|boolean  The URL stripped off the signature, of false if
     *                         not verified.
     */
    public static function verifySignedUrl($data, $now = null)
    {
        if (!is_string($data)) {
            return false;
        }
        return Horde\Core\Horde::verifySignedUrl($data, $now);
    }

    /**
     * Adds a signature + timestamp to a query string and returns the signed
     * query string.
     *
     * @deprecated Use {@see Horde\Core\Horde::signQueryString()} instead.
     *
     * @param mixed $queryString  The query string (or Horde_Url object)
     *                            to sign.
     * @param integer $now        The timestamp at which to sign. Leave blank
     *                            for generating signatures; specify when
     *                            testing.
     *
     * @return mixed  The signed query string (or Horde_Url object).
     */
    public static function signQueryString($queryString, $now = null)
    {
        return Horde\Core\Horde::signQueryString($queryString ?? '', $now);
    }

    /**
     * Verifies a signature and timestamp on a query string.
     *
     * @deprecated Use {@see Horde\Core\Horde::verifySignedQueryString()} instead.
     *
     * @param string $data  The signed query string.
     * @param integer $now  The current time (can override for testing).
     *
     * @return boolean  Whether or not the string was valid.
     */
    public static function verifySignedQueryString($data, $now = null)
    {
        return Horde\Core\Horde::verifySignedQueryString((string) ($data ?? ''), $now);
    }

    /**
     * Do necessary escaping to output JSON.
     *
     * @deprecated Use {@see Horde\Core\Horde::escapeJson()} instead.
     *
     * @param mixed $data     The data to JSON-ify.
     * @param array $options  Additional options:
     *   - nodelimit: (boolean) Don't add security delimiters?
     *                DEFAULT: false
     *   - urlencode: (boolean) URL encode the json string
     *                DEFAULT: false
     *
     * @return string  The escaped string.
     */
    public static function escapeJson($data, array $options = [])
    {
        return Horde\Core\Horde::escapeJson($data, $options);
    }

    /**
     * Is the current HTTP connection considered secure?
     * @TODO Move this to the request classes!
     *
     * @deprecated Use {@see Horde\Core\Horde::isConnectionSecure()} instead.
     *
     * @return boolean
     */
    public static function isConnectionSecure()
    {
        return Horde\Core\Horde::isConnectionSecure();
    }

    /**
     * Throws an exception if not using a secure connection.
     *
     * @deprecated Use {@see Horde\Core\Horde::requireSecureConnection()} instead.
     *
     * @throws Horde_Exception
     */
    public static function requireSecureConnection()
    {
        Horde\Core\Horde::requireSecureConnection();
    }

    /**
     * Returns the driver parameters for the specified backend.
     *
     * @deprecated Use {@see Horde\Core\Horde::getDriverConfig()} instead.
     *
     * @param mixed $backend  The backend system (e.g. 'prefs', 'categories',
     *                        'contacts') being used.
     *                        The used configuration array will be
     *                        $conf[$backend]. If an array gets passed, it will
     *                        be $conf[$key1][$key2].
     * @param string $type    The type of driver. If null, will not merge with
     *                        base config.
     *
     * @return array  The connection parameters.
     */
    public static function getDriverConfig($backend, $type = 'sql')
    {
        return Horde\Core\Horde::getDriverConfig($backend ?? '', $type);
    }

    /**
     * Checks if all necessary parameters for a driver configuration
     * are set and throws a fatal error with a detailed explanation
     * how to fix this, if something is missing.
     *
     * @deprecated Use {@see Horde\Core\Horde::assertDriverConfig()} instead.
     *
     * @param array $params     The configuration array with all parameters.
     * @param string $driver    The key name (in the configuration array) of
     *                          the driver.
     * @param array $fields     An array with mandatory parameter names for
     *                          this driver.
     * @param string $name      The clear text name of the driver. If not
     *                          specified, the application name will be used.
     * @param string $file      The configuration file that should contain
     *                          these settings.
     * @param string $variable  The name of the configuration variable.
     *
     * @throws Horde_Exception
     */
    public static function assertDriverConfig(
        $params,
        $driver,
        $fields,
        $name = null,
        $file = 'conf.php',
        $variable = '$conf'
    ) {
        Horde\Core\Horde::assertDriverConfig(
            is_array($params) ? $params : [],
            (string) ($driver ?? ''),
            is_array($fields) ? $fields : [],
            $name,
            (string) ($file ?? 'conf.php'),
            (string) ($variable ?? '$conf'),
        );
    }

    /**
     * Returns a session-id-ified version of $uri.
     * If a full URL is requested, all parameter separators get converted to
     * "&", otherwise to "&amp;".
     *
     * @param mixed $uri      The URI to be modified (either a string or any
     *                        object with a __toString() function).
     * @param boolean $full   Generate a full (http://server/path/) URL.
     * @param mixed $opts     Additional options. If a string/integer, it is
     *                        taken to be the 'append_session' option.  If an
     *                        array, one of the following:
     *   - app: (string) Use this app for the webroot.
     *          DEFAULT: current application
     *   - append_session: (integer) 0 = only if needed [DEFAULT], 1 = always,
     *                     -1 = never.
     *   - force_ssl: (boolean) Ignore $conf['use_ssl'] and force creation of
     *                a SSL URL?
     *                DEFAULT: false
     *
     * @return Horde_Url  The URL with the session id appended (if needed).
     */
    public static function url($uri, $full = false, $opts = [])
    {
        if (is_array($opts)) {
            $append_session = $opts['append_session'] ?? 0;
            if (!empty($opts['force_ssl'])) {
                $full = true;
            }
        } else {
            $append_session = $opts;
            $opts = [];
        }

        $puri = parse_url($uri) ?: [];

        /* Normalize missing components */
        foreach (['host', 'path'] as $key) {
            if (!isset($puri[$key])) {
                $puri[$key] = '';
            }
        }

        $url = '';
        $schemeRegexp = '|^([a-zA-Z][a-zA-Z0-9+.-]{0,19})://|';
        $webroot = ltrim(
            (string) $GLOBALS['registry']->get(
                'webroot',
                empty($opts['app']) ? null : $opts['app']
            ),
            '/'
        );

        if ($full
            && !isset($puri['scheme'])
            && !preg_match($schemeRegexp, $webroot)) {

            /* Store connection parameters in local variables. */
            $server_name = $GLOBALS['conf']['server']['name'];
            $server_port = $GLOBALS['conf']['server']['port'] ?? '';

            $protocol = 'http';
            switch ($GLOBALS['conf']['use_ssl']) {
                case self::SSL_ALWAYS:
                    $protocol = 'https';
                    break;

                case self::SSL_AUTO:
                    if ($GLOBALS['browser']->usingSSLConnection()) {
                        $protocol = 'https';
                    }
                    break;

                case self::SSL_ONLY_LOGIN:
                    if (!empty($opts['force_ssl'])) {
                        $protocol = 'https';
                        $server_port = '';
                    }
                    break;
            }

            /* If using a non-standard port, add to the URL. */
            if (!empty($server_port)
                && ($protocol === 'http' && $server_port != 80
                 || $protocol === 'https' && $server_port != 443)) {
                $server_name .= ':' . $server_port;
            }

            $url = $protocol . '://' . $server_name;

        } elseif (isset($puri['scheme'])) {

            $url = $puri['scheme'] . ':';
            if ($puri['host'] !== '') {
                $url .= '//' . $puri['host'];

                /* If using a non-standard port, add to the URL. */
                if (isset($puri['port'])
                    && ($puri['scheme'] === 'http' && $puri['port'] != 80
                     || $puri['scheme'] === 'https' && $puri['port'] != 443)) {
                    $url .= ':' . $puri['port'];
                }
            }
        }

        if (substr($puri['path'], 0, 1) === '/'
            && (!preg_match($schemeRegexp, $webroot) || isset($puri['scheme']))) {

            $url .= $puri['path'];

        } elseif ($puri['path'] !== '' && preg_match($schemeRegexp, $webroot)) {

            if (substr($puri['path'], 0, 1) === '/') {
                $pwebroot = parse_url($webroot);
                $url = $pwebroot['scheme'] . '://' . $pwebroot['host']
                    . $puri['path'];
            } else {
                $url = $webroot . '/' . $puri['path'];
            }

        } else {
            $url .= '/' . ($webroot ? $webroot . '/' : '') . $puri['path'];
        }

        if (isset($puri['query'])) {
            $url .= '?' . $puri['query'];
        }
        if (isset($puri['fragment'])) {
            $url .= '#' . $puri['fragment'];
        }

        $ob = new Horde_Url($url, $full);

        if (empty($GLOBALS['conf']['session']['use_only_cookies'])
            && ($append_session === 1
             || $append_session === 0 && !isset($_COOKIE[session_name()]))) {
            $ob->add(session_name(), session_id());
        }

        return $ob;
    }

    /**
     * Returns an external link passed through the dereferrer to strip session
     * IDs from the referrer.
     *
     * @deprecated Use {@see Horde\Core\Horde::externalUrl()} instead.
     *
     * @param string $url   The external URL to link to.
     * @param boolean $tag  If true, a complete <a> tag is returned, only the
     *                      url otherwise.
     *
     * @return string  The link to the dereferrer script.
     */
    public static function externalUrl($url, $tag = false)
    {
        return Horde\Core\Horde::externalUrl((string) ($url ?? ''), (bool) $tag);
    }

    /**
     * Returns an anchor tag with the relevant parameters
     *
     * @deprecated Use {@see Horde\Core\Horde::link()} instead.
     *
     * @param Horde_Url|string $url  The full URL to be linked to.
     * @param string $title          The link title/description.
     * @param string $class          The CSS class of the link.
     * @param string $target         The window target to point to.
     * @param string $onclick        JavaScript action for the 'onclick' event.
     * @param string $title2         The link title (tooltip) (deprecated - just
     *                               use $title).
     * @param string $accesskey      The access key to use.
     * @param array $attributes      Any other name/value pairs to add to the
     *                               <a> tag.
     * @param boolean $escape        Whether to escape special characters in the
     *                               title attribute.
     *
     * @return string  The full <a href> tag.
     */
    public static function link(
        $url = '',
        $title = '',
        $class = '',
        $target = '',
        $onclick = '',
        $title2 = '',
        $accesskey = '',
        $attributes = [],
        $escape = true
    ) {
        if (!empty($title2)) {
            $title = $title2;
        }
        return Horde\Core\Horde::link(
            $url ?? '',
            (string) ($title ?? ''),
            (string) ($class ?? ''),
            (string) ($target ?? ''),
            (string) ($onclick ?? ''),
            (string) ($accesskey ?? ''),
            is_array($attributes) ? $attributes : [],
            (bool) $escape,
        );
    }

    /**
     * Uses DOM Tooltips to display the 'title' attribute for link() calls.
     *
     * @deprecated Use {@see Horde\Core\Horde::linkTooltip()} instead.
     *
     * @param string $url        The full URL to be linked to
     * @param string $status     The JavaScript mouse-over string
     * @param string $class      The CSS class of the link
     * @param string $target     The window target to point to.
     * @param string $onclick    JavaScript action for the 'onclick' event.
     * @param string $title      The link title (tooltip). Most not contain
     *                           HTML data other than &lt;br&gt;, which will
     *                           be converted to a linebreak.
     * @param string $accesskey  The access key to use.
     * @param array  $attributes Any other name/value pairs to add to the
     *                           &lt;a&gt; tag.
     *
     * @return string  The full <a href> tag.
     */
    public static function linkTooltip(
        $url,
        $status = '',
        $class = '',
        $target = '',
        $onclick = '',
        $title = '',
        $accesskey = '',
        $attributes = []
    ) {
        return Horde\Core\Horde::linkTooltip(
            $url ?? '',
            (string) ($status ?? ''),
            (string) ($class ?? ''),
            (string) ($target ?? ''),
            (string) ($onclick ?? ''),
            (string) ($title ?? ''),
            (string) ($accesskey ?? ''),
            is_array($attributes) ? $attributes : [],
        );
    }

    /**
     * Returns an anchor sequence with the relevant parameters for a widget
     * with accesskey and text.
     *
     * @deprecated Use {@see Horde\Core\Horde::widget()} instead.
     *
     * @param array $params  A hash with widget options (other options will be
     *                       passed as attributes to the link tag):
     *   - url: (string) The full URL to be linked to.
     *   - title: (string) The link title/description.
     *   - nocheck: (boolean, optional) Don't check if the accesskey already
     *              already has been used.
     *              Defaults to false (= check).
     *
     * @return string  The full <a href>Title</a> sequence.
     */
    public static function widget($params)
    {
        return Horde\Core\Horde::widget(is_array($params) ? $params : []);
    }

    /**
     * Returns a session-id-ified version of $SCRIPT_NAME resp. $PHP_SELF.
     *
     * @param boolean $script_params Include script parameters like
     *                               QUERY_STRING and PATH_INFO?
     *                               (Deprecated: use Horde::selfUrlParams()
     *                               instead.)
     * @param boolean $nocache       Include a cache-buster parameter in the
     *                               URL?
     * @param boolean $full          Return a full URL?
     * @param boolean $force_ssl     Ignore $conf['use_ssl'] and force creation
     *                               of a SSL URL?
     *
     * @return Horde_Url  The requested URL.
     */
    public static function selfUrl(
        $script_params = false,
        $nocache = true,
        $full = false,
        $force_ssl = false
    ) {
        if (!strncmp(PHP_SAPI, 'cgi', 3)) {
            // When using CGI PHP, SCRIPT_NAME may contain the path to
            // the PHP binary instead of the script being run; use
            // PHP_SELF instead.
            $url = $_SERVER['PHP_SELF'];
        } else {
            $url = $_SERVER['SCRIPT_NAME']
                ?? $_SERVER['PHP_SELF'];
        }
        if (isset($_SERVER['REQUEST_URI'])) {
            $url = Horde_String::common($_SERVER['REQUEST_URI'], $url);
        }
        if (substr($url, -9) == 'index.php') {
            $url = substr($url, 0, -9);
        }

        if ($script_params) {
            $url = new Horde_Url($url);
            if ($pathInfo = Util::getPathInfo()) {
                $url->pathInfo = ltrim((string) $pathInfo, '/');
            }
            if (!empty($_SERVER['QUERY_STRING'])) {
                parse_str($_SERVER['QUERY_STRING'], $args);
                $url->add($args);
            }
        }

        $url = self::url($url, $full, ['force_ssl' => $force_ssl]);

        return ($nocache && $GLOBALS['browser']->hasQuirk('cache_same_url'))
            ? $url->unique()
            : $url;
    }

    /**
     * Create a self URL of the current page, building the parameter list from
     * the current Horde_Variables object (or via another Variables object
     * passed as an optional argument) rather than the original request data.
     *
     * @since 2.3.0
     *
     * @param array $opts  Additional options:
     *   - force_ssl: (boolean) Force creation of an SSL URL?
     *                DEFAULT: false
     *   - full: (boolean) Return a full URL?
     *           DEFAULT: false
     *   - nocache: (boolean) Include a cache-buster parameter in the URL?
     *              DEFAULT: true
     *   - vars: (Horde_Variables) Use this Horde_Variables object instead of
     *           the Horde global object.
     *           DEFAULT: Use the Horde global object.
     *
     * @return Horde_Url  The self URL.
     */
    public static function selfUrlParams(array $opts = [])
    {
        $vars = $opts['vars']
            ?? $GLOBALS['injector']->createInstance('Horde_Variables');

        $url = self::selfUrl(
            false,
            (!array_key_exists('nocache', $opts) || empty($opts['nocache'])),
            !empty($opts['full']),
            !empty($opts['force_ssl'])
        )->add(iterator_to_array($vars));

        if (!isset($opts['vars'])) {
            $url->remove(array_keys($_COOKIE));
        }

        return $url;
    }

    /**
     * Determines the location of the system temporary directory. If a specific
     * configuration cannot be found, it defaults to /tmp.
     *
     * @deprecated Use {@see Horde\Core\Horde::getTempDir()} instead.
     *
     * @return string  A directory name that can be used for temp files.
     *                 Returns false if one could not be found.
     */
    public static function getTempDir()
    {
        return Horde\Core\Horde::getTempDir();
    }

    /**
     * Creates a temporary filename for the lifetime of the script, and
     * (optionally) registers it to be deleted at request shutdown.
     *
     * @deprecated Use {@see Horde\Core\Horde::getTempFile()} instead.
     *
     * @param string $prefix           Prefix to make the temporary name more
     *                                 recognizable.
     * @param boolean $delete          Delete the file at the end of the
     *                                 request?
     * @param string $dir              Directory to create the temporary file
     *                                 in.
     * @param boolean $secure          If deleting file, should we securely
     *                                 delete the file?
     * @param boolean $session_remove  Delete this file when session is
     *                                 destroyed?
     *
     * @return string   Returns the full path-name to the temporary file or
     *                  false if a temporary file could not be created.
     */
    public static function getTempFile(
        $prefix = 'Horde',
        $delete = true,
        $dir = '',
        $secure = false,
        $session_remove = false
    ) {
        return Horde\Core\Horde::getTempFile(
            (string) ($prefix ?? 'Horde'),
            (bool) $delete,
            (string) ($dir ?? ''),
            (bool) $secure,
            (bool) $session_remove,
        );
    }

    /**
     * Returns the Web server being used.
     * PHP string list built from the PHP 'configure' script.
     *
     * @deprecated Use {@see Horde\Core\Horde::webServerID()} instead.
     *
     * @return string  A web server identification string.
     * @see php_sapi_name()
     */
    public static function webServerID()
    {
        return Horde\Core\Horde::webServerID();
    }

    /**
     * Returns an un-used access key from the label given.
     *
     * @deprecated Use {@see Horde\Core\Horde::getAccessKey()} instead.
     *
     * @param string $label      The label to choose an access key from.
     * @param boolean $nocheck   Don't check if the access key already has been
     *                           used?
     * @param boolean $shutdown  Is this called as a shutdown function?
     *
     * @return string  A single lower case character access key, or an empty
     *                 string if no key can be found.
     */
    public static function getAccessKey(
        $label,
        $nocheck = false,
        $shutdown = false
    ) {
        return Horde\Core\Horde::getAccessKey((string) ($label ?? ''), (bool) $nocheck, (bool) $shutdown);
    }

    /**
     * Strips an access key from a label.
     *
     * For multibyte charset strings the access key gets removed completely,
     * otherwise only the underscore gets removed.
     *
     * @deprecated Use {@see Horde\Core\Horde::stripAccessKey()} instead.
     *
     * @param string $label  The label containing an access key.
     *
     * @return string  The label with the access key being stripped.
     */
    public static function stripAccessKey($label)
    {
        return Horde\Core\Horde::stripAccessKey((string) ($label ?? ''));
    }

    /**
     * Highlights an access key in a label.
     *
     * @deprecated Use {@see Horde\Core\Horde::highlightAccessKey()} instead.
     *
     * @param string $label      The label to highlight the access key in.
     * @param string $accessKey  The access key to highlight.
     *
     * @return string  The HTML version of the label with the access key
     *                 highlighted.
     */
    public static function highlightAccessKey($label, $accessKey)
    {
        return Horde\Core\Horde::highlightAccessKey((string) ($label ?? ''), (string) ($accessKey ?? ''));
    }

    /**
     * Returns the appropriate "accesskey" and "title" attributes for an HTML
     * tag and the given label.
     *
     * @deprecated Use {@see Horde\Core\Horde::getAccessKeyAndTitle()} instead.
     *
     * @param string $label          The title of an HTML element
     * @param boolean $nocheck       Don't check if the access key already has
     *                               been used?
     * @param boolean $return_array  Return attributes as a hash?
     *
     * @return string  The title, and if appropriate, the accesskey attributes
     *                 for the element.
     */
    public static function getAccessKeyAndTitle(
        $label,
        $nocheck = false,
        $return_array = false
    ) {
        return Horde\Core\Horde::getAccessKeyAndTitle(
            (string) ($label ?? ''),
            (bool) $nocheck,
            (bool) $return_array,
        );
    }

    /**
     * Returns a label element including an access key for usage in conjuction
     * with a form field. User preferences regarding access keys are respected.
     *
     * @deprecated Use {@see Horde\Core\Horde::label()} instead.
     *
     * @param string $for    The form field's id attribute.
     * @param string $label  The label text.
     * @param string $ak     The access key to use. If null a new access key
     *                       will be generated.
     *
     * @return string  The html code for the label element.
     */
    public static function label($for, $label, $ak = null)
    {
        return Horde\Core\Horde::label((string) ($for ?? ''), (string) ($label ?? ''), $ak);
    }

    /**
     * Print inline javascript to output buffer after wrapping with necessary
     * javascript tags.
     *
     * @deprecated Use {@see Horde\Core\Horde::wrapInlineScript()} instead.
     *
     * @param array $script  The script to output.
     *
     * @return string  The script with the necessary HTML javascript tags
     *                 appended.
     */
    public static function wrapInlineScript($script)
    {
        return Horde\Core\Horde::wrapInlineScript(is_array($script) ? $script : []);
    }

    /**
     * Creates a URL for cached data.
     *
     * @param string $type   The cache type ('app', 'css', 'js').
     * @param array $params  Optional parameters:
     *   - app: REQUIRED for $type == 'app'. Identifies the application to
     *          call the 'cacheOutput' API call, which is passed in the
     *          value of the entire $params array (which may include parameters
     *          other than those listed here). The return from cacheOutput
     *          should be a 2-element array: 'data' (the cached data) and
     *          'type' (the content-type of the data).
     *   - cid: REQUIRED for $type == 'css' || 'js'. The cacheid of the
     *          data (stored in Horde_Cache).
     *   - nocache: If true, sets the cache limiter to 'nocache' instead of
     *              the default 'public'.
     *
     * @return Horde_Url  The URL to the cache page.
     */
    public static function getCacheUrl($type, $params = [])
    {
        $url = $GLOBALS['registry']
            ->getserviceLink('cache', 'horde')
            ->add('cache', $type);
        foreach ($params as $key => $val) {
            $url .= '/' . $key . '=' . rawurlencode(strval($val));
        }

        return self::url($url, true, ['append_session' => -1]);
    }

    /**
     * Output the javascript needed to call the popup JS function.
     *
     * @deprecated Use {@see Horde\Core\Horde::popupJs()} instead.
     *
     * @param string|Horde_Url $url  The page to load.
     * @param array $options         Additional options:
     *   - height: (integer) The height of the popup window.
     *             DEFAULT: 650px
     *   - menu: (boolean) Show the browser menu in the popup window?
     *           DEFAULT: false
     *   - onload: (string) A JS function to call after the popup window is
     *             fully loaded.
     *             DEFAULT: None
     *   - params: (array) Additional parameters to pass to the URL.
     *             DEFAULT: None
     *   - urlencode: (boolean) URL encode the json string?
     *                DEFAULT: false
     *   - width: (integer) The width of the popup window.
     *            DEFAULT: 700 px
     *
     * @return string  The javascript needed to call the popup code.
     */
    public static function popupJs($url, $options = [])
    {
        return Horde\Core\Horde::popupJs($url ?? '', is_array($options) ? $options : []);
    }

    /**
     * Start buffering output.
     *
     * @deprecated Use {@see Horde\Core\Horde::startBuffer()} instead.
     */
    public static function startBuffer()
    {
        Horde\Core\Horde::startBuffer();
    }

    /**
     * End buffering output.
     *
     * @deprecated Use {@see Horde\Core\Horde::endBuffer()} instead.
     *
     * @return string  The buffered output.
     */
    public static function endBuffer()
    {
        return Horde\Core\Horde::endBuffer();
    }

    /**
     * Has any content been sent to the browser?
     *
     * @deprecated Use {@see Horde\Core\Horde::contentSent()} instead.
     *
     * @return boolean  True if content has been sent.
     */
    public static function contentSent()
    {
        return Horde\Core\Horde::contentSent();
    }

    /**
     * Returns the sidebar for the current application.
     *
     * @deprecated Use {@see Horde\Core\Horde::sidebar()} instead.
     *
     * @param string $app  The application to generate the menu for. Defaults
     *                     to the current app.
     *
     * @return Horve_View_Sidebar  The sidebar.
     */
    public static function sidebar($app = null)
    {
        return Horde\Core\Horde::sidebar($app);
    }

    /**
     * Process a permission denied error, running a user-defined hook if
     * necessary.
     *
     * @deprecated Use {@see Horde\Core\Horde::permissionDeniedError()} instead.
     *
     * @param string $app    Application name.
     * @param string $perm   Permission name.
     * @param string $error  An error message to output via the notification
     *                       system.
     */
    public static function permissionDeniedError($app, $perm, $error = null)
    {
        Horde\Core\Horde::permissionDeniedError((string) ($app ?? ''), (string) ($perm ?? ''), $error);
    }

    /**
     * Handle deprecated methods (located in Horde_Deprecated).
     */
    public static function __callStatic($name, $arguments)
    {
        return call_user_func_array(
            ['Horde_Deprecated', $name],
            $arguments
        );
    }

}
