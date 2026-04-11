<?php

declare(strict_types=1);

/**
 * Copyright 1999-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Core;

use Horde_Array;
use Horde_Auth;
use Horde_Browser;
use Horde_Cache;
use Horde_Core_Factory_Logger;
use Horde_Core_Hooks;
use Horde_Core_HordeMap;
use Horde_Core_Log_Object;
use Horde_Core_Script_Package_Popup;
use Horde_Core_Translation;
use Horde_Exception;
use Horde_Exception_HookNotSet;
use Horde_Log;
use Horde_Log_Handler_Stream;
use Horde_Log_Logger;
use Horde_Menu;
use Horde_Registry;
use Horde_Serialize;
use Horde_Session;
use Horde_String;
use Horde_String_Transliterate;
use Horde_Support_Backtrace;
use Horde_Url;
use Horde_Util;
use Horde_Variables;
use Horde_View_Sidebar;
use Stringable;
use stdClass;

/**
 * Provides the base functionality shared by all Horde applications.
 *
 * This is the modern namespaced version of the legacy global Horde class,
 * without deprecated method delegation.
 *
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @author   Jon Parise <jon@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL
 * @package  Core
 */
class Horde
{
    public const SSL_NEVER      = 0;
    public const SSL_ALWAYS     = 1;
    public const SSL_AUTO       = 2;
    public const SSL_ONLY_LOGIN = 3;

    /**
     * The current buffer level.
     */
    protected static int $_bufferLevel = 0;

    /**
     * Has content been sent at the base buffer level?
     */
    protected static bool $_contentSent = false;

    /**
     * The labels already used in this page.
     */
    protected static array $_labels = [];

    /**
     * Are accesskeys supported on this system.
     */
    protected static ?bool $_noAccessKey = null;

    /**
     * The access keys already used in this page.
     */
    protected static array $_used = [];

    /**
     * Shortcut to logging method.
     *
     * @see Horde_Core_Log_Logger
     */
    public static function log(
        mixed $event,
        ?int $priority = null,
        array $options = [],
    ): void {
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
     * Debug method.  Allows quick shortcut to produce debug output into a
     * temporary file.
     */
    public static function debug(
        mixed $event = null,
        ?string $fname = null,
        bool $backtrace = true,
    ): void {
        if ($fname === null) {
            $fname = self::getTempDir() . '/horde_debug.txt';
        }

        try {
            $logger = new Horde_Log_Logger(new Horde_Log_Handler_Stream($fname));
        } catch (\Exception $e) {
            return;
        }

        $html_ini = ini_set('html_errors', 'Off');
        self::startBuffer();
        if ($event !== null) {
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
     * @param string|Horde_Url $url  The URL to sign.
     * @param int|null $now          The timestamp at which to sign.
     *
     * @return string|Horde_Url  The signed URL.
     */
    public static function signUrl(string|Horde_Url $url, ?int $now = null): string|Horde_Url
    {
        global $conf;

        if (!isset($conf['secret_key'])) {
            return $url;
        }

        if ($now === null) {
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
     * @return string|false  The URL stripped of the signature, or false if
     *                       not verified.
     */
    public static function verifySignedUrl(string $data, ?int $now = null): string|false
    {
        global $conf;

        if ($now === null) {
            $now = time();
        }

        $pos = strrpos($data, '&_h=');
        if ($pos === false) {
            return false;
        }
        $pos += 4;

        $url = substr($data, 0, $pos);
        $hmac = substr($data, $pos);

        if ($hmac != Horde_Url::uriB64Encode(hash_hmac('sha1', $url, $conf['secret_key'], true))) {
            return false;
        }

        // String was not tampered with; now validate timestamp
        parse_str((string) parse_url($url, PHP_URL_QUERY), $values);
        if ($values['_t'] + $conf['urls']['hmac_lifetime'] * 60 < $now) {
            return false;
        }

        $pos = strrpos($data, '&_t=');
        if ($pos === false) {
            $pos = strrpos($data, '?_t=');
        }
        if ($pos === false) {
            return false;
        }

        return substr($data, 0, $pos);
    }

    /**
     * Adds a signature + timestamp to a query string and returns the signed
     * query string.
     *
     * @return string|Horde_Url  The signed query string (or Horde_Url object).
     */
    public static function signQueryString(
        string|Horde_Url $queryString,
        ?int $now = null,
    ): string|Horde_Url {
        if (!isset($GLOBALS['conf']['secret_key'])) {
            return $queryString;
        }

        if ($now === null) {
            $now = time();
        }

        if ($queryString instanceof Horde_Url) {
            $queryString->setRaw(true)->add(['_t' => $now, '_h' => '']);
            $query = parse_url((string) $queryString, PHP_URL_QUERY);
            $queryString->add('_h', Horde_Url::uriB64Encode(hash_hmac('sha1', $query . '=', $GLOBALS['conf']['secret_key'], true)));
            return $queryString;
        }

        $queryString .= '&_t=' . $now . '&_h=';

        return $queryString . Horde_Url::uriB64Encode(hash_hmac('sha1', $queryString, $GLOBALS['conf']['secret_key'], true));
    }

    /**
     * Verifies a signature and timestamp on a query string.
     */
    public static function verifySignedQueryString(string $data, ?int $now = null): bool
    {
        if ($now === null) {
            $now = time();
        }

        $pos = strrpos($data, '&_h=');
        if ($pos === false) {
            return false;
        }
        $pos += 4;

        $queryString = substr($data, 0, $pos);
        $hmac = substr($data, $pos);

        if ($hmac != Horde_Url::uriB64Encode(hash_hmac('sha1', $queryString, $GLOBALS['conf']['secret_key'], true))) {
            return false;
        }

        // String was not tampered with; now validate timestamp
        parse_str($queryString, $values);

        return !($values['_t'] + $GLOBALS['conf']['urls']['hmac_lifetime'] * 60 < $now);
    }

    /**
     * Do necessary escaping to output JSON.
     *
     * @param mixed $data     The data to JSON-ify.
     * @param array $options  Additional options:
     *   - nodelimit: (bool) Don't add security delimiters?
     *   - urlencode: (bool) URL encode the json string
     */
    public static function escapeJson(mixed $data, array $options = []): string
    {
        $json = Horde_Serialize::serialize($data, Horde_Serialize::JSON);
        if (empty($options['nodelimit'])) {
            $json = '/*-secure-' . $json . '*/';
        }

        return empty($options['urlencode'])
            ? $json
            : '\'' . rawurlencode($json) . '\'';
    }

    /**
     * Is the current HTTP connection considered secure?
     */
    public static function isConnectionSecure(): bool
    {
        global $browser, $conf, $registry;

        if ($browser->usingSSLConnection()) {
            return true;
        }

        if (!empty($conf['safe_ips'])) {
            if (reset($conf['safe_ips']) == '*') {
                return true;
            }

            $remote = $registry->remoteHost();
            if (!$remote->proxy) {
                foreach ($conf['safe_ips'] as $safe_ip) {
                    $safe_ip = preg_replace('/(\.0)*$/', '', $safe_ip);
                    if (strpos($remote->addr, (string) $safe_ip) === 0) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Throws an exception if not using a secure connection.
     *
     * @throws Horde_Exception
     */
    public static function requireSecureConnection(): void
    {
        if (!self::isConnectionSecure()) {
            throw new Horde_Exception(Horde_Core_Translation::t('The encryption features require a secure web connection.'));
        }
    }

    /**
     * Returns the driver parameters for the specified backend.
     *
     * @param string|array $backend  The backend system (e.g. 'prefs',
     *                               'categories', 'contacts').
     * @param string|null $type      The type of driver. If null, will not
     *                               merge with base config.
     *
     * @return array  The connection parameters.
     */
    public static function getDriverConfig(string|array $backend, ?string $type = 'sql'): array
    {
        global $conf;

        if ($type !== null) {
            $type = Horde_String::lower($type);
        }

        if (is_array($backend)) {
            $c = Horde_Array::getElement($conf, $backend);
        } elseif (isset($conf[$backend])) {
            $c = $conf[$backend];
        } else {
            $c = null;
        }

        if ($c !== null && isset($c['params'])) {
            $c['params']['umask'] = $conf['umask'];

            $result = ($type !== null && isset($conf[$type]))
                ? array_merge($conf[$type], $c['params'])
                : $c['params'];

            if ((!isset($c['params']['driverconfig'])
                 || $c['params']['driverconfig'] != 'horde')
                && $type !== null && $type === 'sql') {
                if (($c['params']['protocol'] ?? null) === 'unix') {
                    unset($result['hostspec'], $result['port']);
                } else {
                    unset($result['socket']);
                }
            }

            return $result;
        }

        return ($type !== null && isset($conf[$type]))
            ? $conf[$type]
            : [];
    }

    /**
     * Checks if all necessary parameters for a driver configuration
     * are set and throws a fatal error with a detailed explanation
     * how to fix this, if something is missing.
     *
     * @throws Horde_Exception
     */
    public static function assertDriverConfig(
        array $params,
        string $driver,
        array $fields,
        ?string $name = null,
        string $file = 'conf.php',
        string $variable = '$conf',
    ): void {
        global $registry;

        if ($name === null) {
            $name = isset($registry) ? $registry->getApp() : '[unknown]';
        }
        $fileroot = isset($registry) ? $registry->get('fileroot') : '';

        if (!count($params)) {
            throw new Horde_Exception(
                sprintf(Horde_Core_Translation::t('No configuration information specified for %s.'), $name) . "\n\n"
                . sprintf(
                    Horde_Core_Translation::t('The file %s should contain some %s settings.'),
                    $fileroot . '/config/' . $file,
                    sprintf("%s['%s']['params']", $variable, $driver)
                )
            );
        }

        foreach ($fields as $field) {
            if (!isset($params[$field])) {
                throw new Horde_Exception(
                    sprintf(Horde_Core_Translation::t('Required "%s" not specified in %s configuration.'), $field, $name) . "\n\n"
                    . sprintf(
                        Horde_Core_Translation::t('The file %s should contain a %s setting.'),
                        $fileroot . '/config/' . $file,
                        sprintf("%s['%s']['params']['%s']", $variable, $driver, $field)
                    )
                );
            }
        }
    }

    /**
     * Returns a session-id-ified version of $uri.
     *
     * @param string|Stringable $uri  The URI to be modified.
     * @param bool $full              Generate a full URL.
     * @param array|int $opts         Additional options or append_session value.
     */
    public static function url(
        string|Stringable $uri,
        bool $full = false,
        array|int $opts = [],
    ): Horde_Url {
        if (is_array($opts)) {
            $append_session = $opts['append_session'] ?? 0;
            if (!empty($opts['force_ssl'])) {
                $full = true;
            }
        } else {
            $append_session = $opts;
            $opts = [];
        }

        $puri = parse_url((string) $uri) ?: [];

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
     */
    public static function externalUrl(string $url, bool $tag = false): string
    {
        if (!isset($_GET[session_name()])
            || Horde_String::substr($url, 0, 1) == '#'
            || Horde_String::substr($url, 0, 7) == 'mailto:') {
            $ext = $url;
        } else {
            $ext = (string) self::signQueryString($GLOBALS['registry']->getServiceLink('go', 'horde')->add('url', $url));
        }

        if ($tag) {
            $ext = self::link($ext, $url, '', '_blank');
        }

        return $ext;
    }

    /**
     * Returns an anchor tag with the relevant parameters.
     *
     * @param Horde_Url|string $url  The full URL to be linked to.
     * @param string $title          The link title/description.
     * @param string $class          The CSS class of the link.
     * @param string $target         The window target to point to.
     * @param string $onclick        JavaScript action for the 'onclick' event.
     * @param string $accesskey      The access key to use.
     * @param array $attributes      Any other name/value pairs to add to the
     *                               <a> tag.
     * @param bool $escape           Whether to escape special characters in the
     *                               title attribute.
     *
     * @return string  The full <a href> tag.
     */
    public static function link(
        Horde_Url|string $url = '',
        string $title = '',
        string $class = '',
        string $target = '',
        string $onclick = '',
        string $accesskey = '',
        array $attributes = [],
        bool $escape = true,
    ): string {
        if (!($url instanceof Horde_Url)) {
            $url = new Horde_Url($url);
        }

        if (!empty($onclick)) {
            $attributes['onclick'] = $onclick;
        }
        if (!empty($class)) {
            $attributes['class'] = $class;
        }
        if (!empty($target)) {
            $attributes['target'] = $target;
        }
        if (!empty($accesskey)) {
            $attributes['accesskey'] = $accesskey;
        }
        if (!empty($title)) {
            if ($escape) {
                $title = str_replace(
                    ["\r", "\n"],
                    '',
                    htmlspecialchars(nl2br(htmlspecialchars($title)))
                );
                /* Remove double encoded entities. */
                $title = preg_replace('/&amp;([a-z]+|(#\d+));/i', '&\\1;', $title);
            }
            $attributes['title.raw'] = $title;
        }

        return $url->link($attributes);
    }

    /**
     * Uses DOM Tooltips to display the 'title' attribute for link() calls.
     *
     * @return string  The full <a href> tag.
     */
    public static function linkTooltip(
        Horde_Url|string $url,
        string $status = '',
        string $class = '',
        string $target = '',
        string $onclick = '',
        string $title = '',
        string $accesskey = '',
        array $attributes = [],
    ): string {
        if (strlen($title)) {
            $attributes['nicetitle'] = Horde_Serialize::serialize(
                preg_split(
                    '/\r?\n/',
                    preg_replace('/<br\s*\/?\s*>/', "\n", $title)
                ),
                Horde_Serialize::JSON
            );
            $title = '';
            $GLOBALS['injector']->getInstance('Horde_PageOutput')
                ->addScriptFile('tooltips.js', 'horde');
        }

        return self::link(
            $url,
            $title,
            $class,
            $target,
            $onclick,
            $accesskey,
            $attributes,
            false
        );
    }

    /**
     * Returns an anchor sequence with the relevant parameters for a widget
     * with accesskey and text.
     *
     * @return string  The full <a href>Title</a> sequence.
     */
    public static function widget(array $params): string
    {
        $params = array_merge(
            [
                'class' => '',
                'target' => '',
                'onclick' => '',
                'nocheck' => false,
            ],
            $params
        );

        $url = ($params['url'] instanceof Horde_Url)
            ? $params['url']
            : new Horde_Url($params['url']);
        $title = $params['title'];
        $params['accesskey'] = self::getAccessKey($title, $params['nocheck']);

        unset($params['url'], $params['title'], $params['nocheck']);

        return $url->link($params)
            . self::highlightAccessKey($title, $params['accesskey'])
            . '</a>';
    }

    /**
     * Returns a session-id-ified version of $SCRIPT_NAME resp. $PHP_SELF.
     */
    public static function selfUrl(
        bool $script_params = false,
        bool $nocache = true,
        bool $full = false,
        bool $force_ssl = false,
    ): Horde_Url {
        if (!strncmp(PHP_SAPI, 'cgi', 3)) {
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
            if ($pathInfo = Horde_Util::getPathInfo()) {
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
     * the current Horde_Variables object.
     */
    public static function selfUrlParams(array $opts = []): Horde_Url
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
     * Determines the location of the system temporary directory.
     *
     * @return string|false  A directory name, or false if one could not
     *                       be found.
     */
    public static function getTempDir(): string|false
    {
        global $conf;

        $tmp = '';

        /* If one has been specifically set, then use that */
        if (!empty($conf['tmpdir'])) {
            $tmp = $conf['tmpdir'];
        }

        /* Next, try sys_get_temp_dir(). */
        if (empty($tmp)) {
            $tmp = sys_get_temp_dir();
        }

        return empty($tmp) ? false : $tmp;
    }

    /**
     * Creates a temporary filename for the lifetime of the script, and
     * (optionally) registers it to be deleted at request shutdown.
     *
     * @return string|false  Returns the full path-name to the temporary file
     *                       or false if a temporary file could not be created.
     */
    public static function getTempFile(
        string $prefix = 'Horde',
        bool $delete = true,
        string $dir = '',
        bool $secure = false,
        bool $session_remove = false,
    ): string|false {
        if (empty($dir) || !is_dir($dir)) {
            $dir = self::getTempDir();
        }
        $tmpfile = Horde_Util::getTempFile($prefix, $delete, $dir, $secure);
        if ($session_remove) {
            $gcfiles = $GLOBALS['session']->get('horde', 'gc_tempfiles', Horde_Session::TYPE_ARRAY);
            $gcfiles[] = $tmpfile;
            $GLOBALS['session']->set('horde', 'gc_tempfiles', $gcfiles);
        }

        return $tmpfile;
    }

    /**
     * Returns the Web server being used.
     *
     * @see php_sapi_name()
     */
    public static function webServerID(): string
    {
        switch (PHP_SAPI) {
            case 'apache':
                return 'apache1';

            case 'apache2filter':
            case 'apache2handler':
                return 'apache2';

            default:
                return PHP_SAPI;
        }
    }

    /**
     * Returns an un-used access key from the label given.
     *
     * @return string  A single lower case character access key, or an empty
     *                 string if no key can be found.
     */
    public static function getAccessKey(
        string $label,
        bool $nocheck = false,
        bool $shutdown = false,
    ): string {
        /* Shutdown call for translators? */
        if ($shutdown) {
            if (!count(self::$_labels)) {
                return '';
            }
            $script = basename($_SERVER['PHP_SELF']);
            $labels = array_keys(self::$_labels);
            sort($labels);
            $used = array_keys(self::$_used);
            sort($used);
            $remaining = str_replace($used, [], 'abcdefghijklmnopqrstuvwxyz');
            self::log('Access key information for ' . $script);
            self::log('Used labels: ' . implode(',', $labels));
            self::log('Used keys: ' . implode('', $used));
            self::log('Free keys: ' . $remaining);
            return '';
        }

        /* Use access keys at all? */
        if (self::$_noAccessKey === null) {
            self::$_noAccessKey = !$GLOBALS['browser']->hasFeature('accesskey') || !$GLOBALS['prefs']->getValue('widget_accesskey');
        }

        if (self::$_noAccessKey
            || !preg_match('/_(\w)/u', $label, $match)) {
            return '';
        }
        $key = Horde_String_Transliterate::toAscii($match[1]);

        /* Has this key already been used? */
        if (isset(self::$_used[strtolower($key)])
            && !($nocheck && isset(self::$_labels[$label]))) {
            return '';
        }

        /* Save key and label. */
        self::$_used[strtolower($key)] = true;
        self::$_labels[$label] = true;

        return $key;
    }

    /**
     * Strips an access key from a label.
     */
    public static function stripAccessKey(string $label): string
    {
        $replace = $GLOBALS['registry']->nlsconfig->curr_multibyte
            && preg_match('/[\x80-\xff]/', $label)
            ? ''
            : '$1';
        return preg_replace('/_(\w)/u', $replace, $label);
    }

    /**
     * Highlights an access key in a label.
     */
    public static function highlightAccessKey(string $label, string $accessKey): string
    {
        $stripped_label = self::stripAccessKey($label);

        if (empty($accessKey)) {
            return $stripped_label;
        }

        if ($GLOBALS['registry']->nlsconfig->curr_multibyte) {
            return $stripped_label . "\xe2\x80\xad"
                . '(<span class="accessKey">' . strtoupper($accessKey)
                . '</span>' . ')';
        }

        return preg_replace(
            '/_(\w)/u',
            '<span class="accessKey">$1</span>',
            $label
        );
    }

    /**
     * Returns the appropriate "accesskey" and "title" attributes for an HTML
     * tag and the given label.
     *
     * @return string|array  The title and accesskey attributes as a string,
     *                       or as an array if $return_array is true.
     */
    public static function getAccessKeyAndTitle(
        string $label,
        bool $nocheck = false,
        bool $return_array = false,
    ): string|array {
        $ak = self::getAccessKey($label, $nocheck);
        $attributes = ['title' => self::stripAccessKey($label)];
        if (!empty($ak)) {
            $attributes['title'] .= sprintf(Horde_Core_Translation::t(' (Accesskey %s)'), strtoupper($ak));
            $attributes['accesskey'] = $ak;
        }

        if ($return_array) {
            return $attributes;
        }

        $html = '';
        foreach ($attributes as $attribute => $value) {
            $html .= sprintf(' %s="%s"', $attribute, $value);
        }
        return $html;
    }

    /**
     * Returns a label element including an access key for usage in
     * conjunction with a form field.
     */
    public static function label(string $for, string $label, ?string $ak = null): string
    {
        if ($ak === null) {
            $ak = self::getAccessKey($label, true);
        }
        $label = self::highlightAccessKey($label, $ak);

        return sprintf(
            '<label for="%s"%s>%s</label>',
            $for,
            !empty($ak) ? ' accesskey="' . $ak . '"' : '',
            $label
        );
    }

    /**
     * Print inline javascript to output buffer after wrapping with necessary
     * javascript tags.
     */
    public static function wrapInlineScript(array $script): string
    {
        return '<script type="text/javascript">//<![CDATA[' . "\n" . implode('', $script) . "\n//]]></script>\n";
    }

    /**
     * Creates a URL for cached data.
     *
     * @param string $type   The cache type ('app', 'css', 'js').
     * @param array $params  Optional parameters.
     */
    public static function getCacheUrl(string $type, array $params = []): Horde_Url
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
     * @param string|Horde_Url $url  The page to load.
     * @param array $options         Additional options.
     *
     * @return string  The javascript needed to call the popup code.
     */
    public static function popupJs(string|Horde_Url $url, array $options = []): string
    {
        $GLOBALS['page_output']->addScriptPackage('Horde_Core_Script_Package_Popup');

        $params = new stdClass();

        if (!$url instanceof Horde_Url) {
            $url = new Horde_Url($url);
        }
        $params->url = $url->url;

        if (!empty($url->parameters)) {
            if (!isset($options['params'])) {
                $options['params'] = [];
            }
            foreach (array_merge($url->parameters, $options['params']) as $key => $val) {
                $options['params'][$key] = addcslashes($val, '"');
            }
        }

        if (!empty($options['menu'])) {
            $params->menu = 1;
        }
        foreach (['height', 'onload', 'params', 'width'] as $key) {
            if (!empty($options[$key])) {
                $params->$key = $options[$key];
            }
        }

        return 'void(HordePopup.popup(' . self::escapeJson($params, ['nodelimit' => true, 'urlencode' => !empty($options['urlencode'])]) . '));';
    }

    /**
     * Start buffering output.
     */
    public static function startBuffer(): void
    {
        if (!self::$_bufferLevel) {
            self::$_contentSent = self::contentSent();
        }

        ++self::$_bufferLevel;
        ob_start();
    }

    /**
     * End buffering output.
     */
    public static function endBuffer(): string
    {
        if (self::$_bufferLevel) {
            --self::$_bufferLevel;
            return ob_get_clean();
        }

        return '';
    }

    /**
     * Has any content been sent to the browser?
     */
    public static function contentSent(): bool
    {
        return (self::$_bufferLevel && self::$_contentSent)
            || (!self::$_bufferLevel && (ob_get_length() || headers_sent()));
    }

    /**
     * Returns the sidebar for the current application.
     */
    public static function sidebar(?string $app = null): Horde_View_Sidebar
    {
        global $registry;

        if (empty($app)) {
            $app = $registry->getApp();
        }

        $menu = new Horde_Menu();
        $registry->callAppMethod($app, 'menu', [
            'args' => [$menu],
        ]);
        $sidebar = $menu->render();
        $registry->callAppMethod($app, 'sidebar', [
            'args' => [$sidebar],
        ]);

        return $sidebar;
    }

    /**
     * Process a permission denied error, running a user-defined hook if
     * necessary.
     */
    public static function permissionDeniedError(
        string $app,
        string $perm,
        ?string $error = null,
    ): void {
        try {
            $GLOBALS['injector']->getInstance('Horde_Core_Hooks')
                ->callHook('perms_denied', 'horde', [$app, $perm]);
        } catch (Horde_Exception_HookNotSet $e) {
        }

        if ($error !== null) {
            $GLOBALS['notification']->push($error, 'horde.warning');
        }
    }
}
