<?php

/**
 * The Horde_Config:: package provides a framework for managing the
 * configuration of Horde applications, writing conf.php files from
 * conf.xml source files, generating user interfaces, etc.
 *
 * Copyright 2002-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @category Horde
 * @package  Core
 */

use Horde\Core\Config\ConfigMetadataProvider;
use Horde\Core\Config\Legacy\LegacyConfigAdapter;
use Horde\Injector\Injector;

class Horde_Config
{
    /**
     * The name of the configured application.
     *
     * @var string
     */
    protected $_app;

    /**
     * The XML tree of the configuration file traversed to an
     * associative array.
     *
     * @var array
     */
    protected $_xmlConfigTree = null;

    /**
     * The content of the generated configuration file.
     *
     * @var string
     */
    protected $_phpConfig;

    /**
     * The content of the old configuration file.
     *
     * @var string
     */
    protected $_oldConfig;

    /**
     * The manual configuration in front of the generated configuration.
     *
     * @var string
     */
    protected $_preConfig;

    /**
     * The manual configuration after the generated configuration.
     *
     * @var string
     */
    protected $_postConfig;

    /**
     * The current $conf array of the configured application.
     *
     * @var array
     */
    protected $_currentConfig = [];

    /**
     * The SHA-1 hash of the conf.xml file which will be copied into the
     * conf.php file.
     *
     * @var string
     */
    protected $_configHash = '';

    /**
     * The version tag of the conf.xml file which will be copied into the
     * conf.php file.
     *
     * @var string
     * @deprecated
     * @todo Remove for Horde 6
     */
    protected $_versionTag = '';

    /**
     * The line marking the begin of the generated configuration.
     *
     * @var string
     */
    protected $_configBegin = "/* CONFIG START. DO NOT CHANGE ANYTHING IN OR AFTER THIS LINE. */\n";

    /**
     * The line marking the end of the generated configuration.
     *
     * @var string
     */
    protected $_configEnd = "/* CONFIG END. DO NOT CHANGE ANYTHING IN OR BEFORE THIS LINE. */\n";

    /**
     * Horde URL to check version information.
     *
     * @var string
     */
    protected $_versionUrl = 'https://pear.horde.org/packages.json';

    /**
     * Optional injector instance.
     *
     * @var Injector|null
     */
    protected ?Injector $_injector = null;

    /**
     * Constructor.
     *
     * @param string $app  The name of the application to be configured.
     * @param Injector|null $injector  Optional injector instance. If null,
     *                                  falls back to $GLOBALS['injector'].
     */
    public function __construct($app = 'horde', ?Injector $injector = null)
    {
        $this->_app = $app;
        $this->_injector = $injector;
    }

    /**
     * Contact Horde servers and get version information.
     *
     * @return array  Keys are app names, values are arrays with two keys:
     *                'version' and 'url'.
     * @throws Horde_Exception
     * @throws Horde_Http_Exception, Horde_Exception
     */
    public function checkVersions()
    {
        $response = $GLOBALS['injector']
            ->getInstance('Horde_Core_Factory_HttpClient')
            ->create([
                'request.timeout' => 60,
                'request.userAgent' => 'Horde ' . $GLOBALS['registry']->getVersion('horde', true),
            ])
            ->get($this->_versionUrl);
        if ($response->code != 200) {
            throw new Horde_Exception('Unexpected response from server.');
        }
        if (!is_array($result = json_decode($response->getBody(), true))) {
            throw new Horde_Exception('Unexpected response from server.');
        }

        $versions = [];

        foreach ($result as $package) {
            uksort($package['versions'], 'version_compare');
            $version = end($package['versions']);
            $versions[str_replace('pear-horde/', '', $package['name'])] = [
                'version' => $version['version'],
                'url' => 'https://pear.horde.org/',
            ];
        }

        return $versions;
    }

    /**
     */
    public function configFile()
    {
        if (defined('HORDE_CONFIG_BASE')) {
            $path = HORDE_CONFIG_BASE . DIRECTORY_SEPARATOR . $this->_app;
        } else {
            $path = $GLOBALS['registry']->get('fileroot', $this->_app) . '/config';
        }
        $configFile = $path . '/conf.php';
        if (is_link($configFile)) {
            $configFile = readlink($configFile);
        }
        return $configFile;
    }

    /**
     * Reads the application's conf.xml file and builds an associative array
     * from its XML tree.
     *
     * @param array $custom_conf  Any settings that shall be included in the
     *                            generated configuration.
     *
     * @return array  An associative array representing the configuration
     *                tree.
     */
    public function readXMLConfig($custom_conf = null)
    {
        if (!is_null($this->_xmlConfigTree) && !$custom_conf) {
            return $this->_xmlConfigTree;
        }

        $path = $GLOBALS['registry']->get('fileroot', $this->_app) . '/config';

        if ($custom_conf) {
            $this->_currentConfig = $custom_conf;
        } else {
            /* Fetch the current conf.php contents. */
            @eval($this->getPHPConfig());
            if (isset($conf)) {
                $this->_currentConfig = $conf;
            }
        }

        /* Load the DOM object. */
        $dom = new DOMDocument();
        $dom->loadXML(file_get_contents($path . '/conf.xml'));

        /* Create config file hash. */
        $this->_configHash = hash_file('sha1', $path . '/conf.xml');

        /* Check if there is a CVS/Git version tag and store it. */
        /* TODO: Remove for Horde 6. */
        $node = $dom->firstChild;
        while (!empty($node)) {
            if (($node->nodeType == XML_COMMENT_NODE)
                && ($vers_tag = $this->getVersion($node->nodeValue))) {
                $this->_versionTag = $vers_tag . "\n";
                break;
            }
            $node = $node->nextSibling;
        }

        /* Parse the config file. */
        $this->_xmlConfigTree = [];
        $root = $dom->documentElement;
        if ($root->hasChildNodes()) {
            $this->_parseLevel($this->_xmlConfigTree, $root->childNodes, '');
        }

        /* Parse additional config files. */
        foreach (glob($path . '/conf.d/*.xml') as $additional) {
            $dom = new DOMDocument();
            $dom->load($additional);
            $root = $dom->documentElement;
            if ($root->hasChildNodes()) {
                $tree = [];
                $this->_parseLevel($tree, $root->childNodes, '');
                $this->_xmlConfigTree = array_replace_recursive($this->_xmlConfigTree, $tree);
            }
        }

        return $this->_xmlConfigTree;
    }

    /**
     * Get the Horde version string for a config file.
     *
     * @param string $text  The text to parse.
     *
     * @return string  The version string or false if not found.
     */
    public function getVersion($text)
    {
        // Old CVS tag
        if (preg_match('/\$.*?conf\.xml,v .*? .*\$/', $text, $match)
            // New Git tag
            || preg_match('/\$Id:\s*[0-9a-f]+\s*\$/', $text, $match)) {
            return $match[0];
        }

        return false;
    }

    /**
     * Returns the file content of the current configuration file.
     *
     * @return string  The unparsed configuration file content.
     */
    public function getPHPConfig()
    {
        if (!is_null($this->_oldConfig)) {
            return $this->_oldConfig;
        }

        $path = $GLOBALS['registry']->get('fileroot', $this->_app) . '/config';
        if (file_exists($path . '/conf.php')) {
            $this->_oldConfig = file_get_contents($path . '/conf.php');
            if (!empty($this->_oldConfig)) {
                $this->_oldConfig = preg_replace('/<\?php\n?/', '', $this->_oldConfig);
                $pos = strpos($this->_oldConfig, $this->_configBegin);
                if ($pos !== false) {
                    $this->_preConfig = substr($this->_oldConfig, 0, $pos);
                    $this->_oldConfig = substr($this->_oldConfig, $pos);
                }
                $pos = strpos($this->_oldConfig, $this->_configEnd);
                if ($pos !== false) {
                    $this->_postConfig = substr($this->_oldConfig, $pos + strlen($this->_configEnd));
                    $this->_oldConfig = substr($this->_oldConfig, 0, $pos);
                }
            }
        } else {
            $this->_oldConfig = '';
        }

        return $this->_oldConfig;
    }

    /**
     * Generates and writes the content of the application's configuration
     * file.
     *
     * @param Horde_Variables $formvars  The processed configuration form
     *                                   data.
     * @param string $php                The content of the generated
     *                                   configuration file.
     *
     * @return boolean  True if the configuration file could be written
     *                  immediately to the file system.
     */
    public function writePHPConfig($formvars, &$php = null)
    {
        $php = $this->generatePHPConfig($formvars);
        if (defined('HORDE_CONFIG_BASE')) {
            $path = HORDE_CONFIG_BASE . DIRECTORY_SEPARATOR . $this->_app;
        } else {
            $path = $GLOBALS['registry']->get('fileroot', $this->_app) . '/config';
        }
        $configFile = $this->configFile();
        if (file_exists($configFile)) {
            if (@copy($configFile, $path . '/conf.bak.php')) {
                $GLOBALS['notification']->push(sprintf(Horde_Core_Translation::t('Successfully saved the backup configuration file %s.'), Horde_Util::realPath($path . '/conf.bak.php')), 'horde.success');
            } else {
                $GLOBALS['notification']->push(sprintf(Horde_Core_Translation::t('Could not save the backup configuration file %s.'), Horde_Util::realPath($path . '/conf.bak.php')), 'horde.warning');
            }
        }
        if ($fp = @fopen($configFile, 'w')) {
            /* Can write, so output to file. */
            fwrite($fp, $php);
            fclose($fp);
            $GLOBALS['registry']->rebuild();
            $GLOBALS['notification']->push(sprintf(Horde_Core_Translation::t('Successfully wrote %s'), Horde_Util::realPath($configFile)), 'horde.success');
            return true;
        }

        /* Cannot write. Save to session. */
        $GLOBALS['session']->set('horde', 'config/' . $this->_app, $php);

        return false;
    }

    /**
     * Generates the content of the application's configuration file.
     *
     * @param Horde_Variables $formvars  The processed configuration form
     *                                   data.
     * @param array $custom_conf         Any settings that shall be included
     *                                   in the generated configuration.
     *
     * @return string  The content of the generated configuration file.
     */
    public function generatePHPConfig($formvars, $custom_conf = null)
    {
        $this->readXMLConfig($custom_conf);
        $this->getPHPConfig();

        $this->_phpConfig = "<?php\n" . $this->_preConfig
            . $this->_configBegin . '// $Hash: ' . $this->_configHash . "\n";
        if (!empty($this->_versionTag)) {
            $this->_phpConfig .= '// ' . $this->_versionTag;
        }
        $this->_generatePHPConfig($this->_xmlConfigTree, '', $formvars);
        $this->_phpConfig .= $this->_configEnd . $this->_postConfig;

        return $this->_phpConfig;
    }

    /**
     * Generates the configuration file items for a part of the configuration
     * tree.
     *
     * @param array $section             An associative array containing the
     *                                   part of the traversed XML
     *                                   configuration tree that should be
     *                                   processed.
     * @param string $prefix             A configuration prefix determining
     *                                   the current position inside the
     *                                   configuration file. This prefix will
     *                                   be translated to keys of the $conf
     *                                   array in the generated configuration
     *                                   file.
     * @param Horde_Variables $formvars  The processed configuration form
     *                                   data.
     */
    protected function _generatePHPConfig($section, $prefix, $formvars)
    {
        if (!is_array($section)) {
            return;
        }

        foreach ($section as $name => $configitem) {
            if (is_array($configitem) && isset($configitem['tab'])) {
                continue;
            }

            $prefixedname = empty($prefix)
                ? $name
                : $prefix . '|' . $name;
            $configname = str_replace('|', '__', $prefixedname);
            $quote = (!isset($configitem['quote']) || $configitem['quote'] !== false);

            if ($configitem == 'placeholder') {
                $this->_phpConfig .= '$conf[\'' . str_replace('|', '\'][\'', $prefix) . "'] = array();\n";
            } elseif (isset($configitem['switch'])) {
                $val = $formvars->getExists($configname, $wasset);
                if (!$wasset) {
                    $val = $configitem['default'] ?? null;
                }
                if (isset($configitem['switch'][$val])) {
                    $value = $val;
                    if ($quote && $value != 'true' && $value != 'false') {
                        $value = "'" . $value . "'";
                    }
                    $this->_generatePHPConfig($configitem['switch'][$val]['fields'], $prefix, $formvars);
                }
            } elseif (isset($configitem['_type'])) {
                $val = $formvars->getExists($configname, $wasset);
                if (!$wasset
                    && ((array_key_exists('is_default', $configitem) && $configitem['is_default'])
                     || !array_key_exists('is_default', $configitem))) {

                    $val = $configitem['default'] ?? null;
                }

                $type = $configitem['_type'];
                switch ($type) {
                    case 'multienum':
                        if (is_array($val)) {
                            $encvals = [];
                            foreach ($val as $v) {
                                $encvals[] = $this->_quote($v);
                            }
                            $arrayval = "'" . implode('\', \'', $encvals) . "'";
                            if ($arrayval == "''") {
                                $arrayval = '';
                            }
                        } else {
                            $arrayval = '';
                        }
                        $value = 'array(' . $arrayval . ')';
                        break;

                    case 'boolean':
                        if (is_bool($val)) {
                            $value = $val ? 'true' : 'false';
                        } else {
                            $value = ($val == 'on') ? 'true' : 'false';
                        }
                        break;

                    case 'stringlist':
                        $values = explode(',', $val);
                        if (!is_array($values)) {
                            $value = "array('" . $this->_quote(trim($values)) . "')";
                        } else {
                            $encvals = [];
                            foreach ($values as $v) {
                                $encvals[] = $this->_quote(trim($v));
                            }
                            $arrayval = "'" . implode('\', \'', $encvals) . "'";
                            if ($arrayval == "''") {
                                $arrayval = '';
                            }
                            $value = 'array(' . $arrayval . ')';
                        }
                        break;

                    case 'int':
                        if (strlen($val)) {
                            $value = (int) $val;
                        }
                        break;

                    case 'octal':
                        $value = sprintf('0%o', octdec($val));
                        break;

                    case 'header':
                    case 'description':
                        break;

                    default:
                        if ($val != '') {
                            $value = $val;
                            if ($quote && $value != 'true' && $value != 'false') {
                                $value = "'" . $this->_quote($value) . "'";
                            }
                        }
                        break;
                }
            } else {
                $this->_generatePHPConfig($configitem, $prefixedname, $formvars);
            }

            if (isset($value)) {
                $this->_phpConfig .= '$conf[\'' . str_replace('__', '\'][\'', $configname) . '\'] = ' . $value . ";\n";
            }
            unset($value);
        }
    }

    /**
     * Parses one level of the configuration XML tree into the associative
     * array containing the traversed configuration tree.
     *
     * @param array &$conf           The already existing array where the
     *                               processed XML tree portion should be
     *                               appended to.
     * @param DOMNodeList $children  The XML nodes of the level that should
     *                               be parsed.
     * @param string $ctx            A string representing the current
     *                               position (context prefix) inside the
     *                               configuration XML file.
     */
    protected function _parseLevel(&$conf, $children, $ctx)
    {
        foreach ($children as $node) {
            if ($node->nodeType != XML_ELEMENT_NODE) {
                continue;
            }
            $name = $node->getAttribute('name');
            // Don't pass Null or Integer to a Text Filter
            $desc = (string) $node->getAttribute('desc');
            $desc = $GLOBALS['injector']->getInstance('Horde_Core_Factory_TextFilter')->filter($desc, 'linkurls');
            $required = !($node->getAttribute('required') == 'false');
            $quote = !($node->getAttribute('quote') == 'false');

            $curctx = empty($ctx)
                ? $name
                : $ctx . '|' . $name;

            switch ($node->tagName) {
                case 'configdescription':
                    if (empty($name)) {
                        $name = uniqid(mt_rand());
                    }

                    $conf[$name] = [
                        '_type' => 'description',
                        'desc' => $GLOBALS['injector']->getInstance('Horde_Core_Factory_TextFilter')->filter($this->_default($curctx, $this->_getNodeOnlyText($node)), 'linkurls'),
                    ];
                    break;

                case 'configheader':
                    if (empty($name)) {
                        $name = uniqid(mt_rand());
                    }

                    $conf[$name] = [
                        '_type' => 'header',
                        'desc' => $this->_default($curctx, $this->_getNodeOnlyText($node)),
                    ];
                    break;

                case 'configswitch':
                    $values = $this->_getSwitchValues($node, $ctx) ?? '';
                    [$default, $isDefault] = $quote
                        ? $this->__default($curctx, $this->_getNodeOnlyText($node))
                        : $this->__defaultRaw($curctx, $this->_getNodeOnlyText($node));

                    if ($default === '') {
                        $default = key($values);
                    }

                    if (is_bool($default)) {
                        $default = $default ? 'true' : 'false';
                    }

                    $conf[$name] = [
                        'desc' => $desc,
                        'switch' => $values,
                        'default' => $default,
                        'is_default' => $isDefault,
                        'quote' => $quote,
                    ];
                    break;

                case 'configenum':
                    $values = $this->_getEnumValues($node);
                    [$default, $isDefault] = $quote
                        ? $this->__default($curctx, $this->_getNodeOnlyText($node))
                        : $this->__defaultRaw($curctx, $this->_getNodeOnlyText($node));

                    if ($default === '') {
                        $default = key($values);
                    }

                    if (is_bool($default)) {
                        $default = $default ? 'true' : 'false';
                    }

                    $conf[$name] = [
                        '_type' => 'enum',
                        'required' => $required,
                        'quote' => $quote,
                        'values' => $values,
                        'desc' => $desc,
                        'default' => $default,
                        'is_default' => $isDefault,
                    ];
                    break;

                case 'configlist':
                    [$default, $isDefault] = $this->__default($curctx, null);

                    if (is_null($default)) {
                        $default = $this->_getNodeOnlyText($node);
                    } elseif (is_array($default)) {
                        $default = implode(', ', $default);
                    }

                    $conf[$name] = [
                        '_type' => 'stringlist',
                        'required' => $required,
                        'desc' => $desc,
                        'default' => $default,
                        'is_default' => $isDefault,
                    ];
                    break;

                case 'configmultienum':
                    $default = $this->_getNodeOnlyText($node);
                    if (strlen($default)) {
                        $default = explode(',', $default);
                    } else {
                        $default = [];
                    }
                    [$default, $isDefault] = $this->__default($curctx, $default);

                    $conf[$name] = [
                        '_type' => 'multienum',
                        'required' => $required,
                        'values' => $this->_getEnumValues($node),
                        'desc' => $desc,
                        'default' => Horde_Array::valuesToKeys($default),
                        'is_default' => $isDefault,
                    ];
                    break;

                case 'configpassword':
                    $conf[$name] = [
                        '_type' => 'password',
                        'required' => $required,
                        'desc' => $desc,
                        'default' => $this->_default($curctx, $this->_getNodeOnlyText($node)),
                        'is_default' => $this->_isDefault($curctx, $this->_getNodeOnlyText($node)),
                    ];
                    break;

                case 'configstring':
                    $conf[$name] = [
                        '_type' => 'text',
                        'required' => $required,
                        'desc' => $desc,
                        'default' => $this->_default($curctx, $this->_getNodeOnlyText($node)),
                        'is_default' => $this->_isDefault($curctx, $this->_getNodeOnlyText($node)),
                    ];

                    if ($conf[$name]['default'] === false) {
                        $conf[$name]['default'] = 'false';
                    } elseif ($conf[$name]['default'] === true) {
                        $conf[$name]['default'] = 'true';
                    }
                    break;

                case 'configboolean':
                    $default = $this->_getNodeOnlyText($node);
                    $default = !(empty($default) || $default === 'false');

                    $conf[$name] = [
                        '_type' => 'boolean',
                        'required' => $required,
                        'desc' => $desc,
                        'default' => $this->_default($curctx, $default),
                        'is_default' => $this->_isDefault($curctx, $default),
                    ];
                    break;

                case 'configinteger':
                    $values = $this->_getEnumValues($node);

                    $conf[$name] = [
                        '_type' => 'int',
                        'required' => $required,
                        'values' => $values,
                        'desc' => $desc,
                        'default' => $this->_default($curctx, $this->_getNodeOnlyText($node)),
                        'is_default' => $this->_isDefault($curctx, $this->_getNodeOnlyText($node)),
                    ];

                    if ($node->getAttribute('octal') == 'true'
                        && $conf[$name]['default'] != '') {
                        $conf[$name]['_type'] = 'octal';
                        $conf[$name]['default'] = sprintf('0%o', $this->_default($curctx, octdec($this->_getNodeOnlyText($node))));
                    }
                    break;

                case 'configldap':
                    $conf[$node->getAttribute('switchname')] = $this->_configLDAP($ctx, $node);
                    break;

                case 'configldapuser':
                    $conf = array_merge($conf, $this->_configLDAPUser($ctx, $node));
                    break;

                case 'configphp':
                    $conf[$name] = [
                        '_type' => 'php',
                        'required' => $required,
                        'quote' => false,
                        'desc' => $desc,
                        'default' => $this->_defaultRaw($curctx, $this->_getNodeOnlyText($node)),
                        'is_default' => $this->_isDefaultRaw($curctx, $this->_getNodeOnlyText($node)),
                    ];
                    break;

                case 'configsecret':
                    $conf[$name] = [
                        '_type' => 'text',
                        'required' => true,
                        'desc' => $desc,
                        'default' => $this->_default($curctx, strval(new Horde_Support_Randomid())),
                        'is_default' => $this->_isDefault($curctx, $this->_getNodeOnlyText($node)),
                    ];
                    break;

                case 'configsql':
                    $conf[$node->getAttribute('switchname')] = $this->configSQL($ctx, $node);
                    break;

                case 'confignosql':
                    $conf[$node->getAttribute('switchname')] = $this->configNoSQL($ctx, $node);
                    break;

                case 'configvfs':
                    $conf[$node->getAttribute('switchname')] = $this->_configVFS($ctx, $node);
                    break;

                case 'configsection':
                    $conf[$name] = [];
                    $cur = &$conf[$name];
                    if ($node->hasChildNodes()) {
                        $this->_parseLevel($cur, $node->childNodes, $curctx);
                    }
                    break;

                case 'configtab':
                    $key = uniqid(mt_rand());

                    $conf[$key] = [
                        'tab' => $name,
                        'desc' => $desc,
                    ];

                    if ($node->hasChildNodes()) {
                        $this->_parseLevel($conf, $node->childNodes, $ctx);
                    }
                    break;

                case 'configplaceholder':
                    $conf[uniqid(mt_rand())] = 'placeholder';
                    break;

                default:
                    $conf[$name] = [];
                    $cur = &$conf[$name];
                    if ($node->hasChildNodes()) {
                        $this->_parseLevel($cur, $node->childNodes, $curctx);
                    }
                    break;
            }
        }
    }

    /**
     * Returns the configuration tree for an LDAP backend configuration to
     * replace a <configldap> tag.
     * Subnodes will be parsed and added to both the Horde defaults and the
     * Custom configuration parts.
     *
     * @param string $ctx         The context of the <configldap> tag.
     * @param DomNode $node       The DomNode representation of the
     *                            <configldap> tag.
     * @param string $switchname  If $node is not set, the value of the tag's
     *                            switchname attribute.
     *
     * @return array  An associative array with the LDAP configuration tree.
     */
    protected function _configLDAP(
        $ctx,
        $node = null,
        $switchname = 'driverconfig'
    ) {
        if ($node) {
            $xpath = new DOMXPath($node->ownerDocument);
        }

        $host = $node
            ? explode(',', ($xpath->evaluate('string(configstring[@name="hostspec"])', $node) ?: ''))
            : [];
        $host = $this->_default($ctx . '|hostspec', $host);
        $fields = [
            'hostspec' => [
                '_type' => 'stringlist',
                'required' => true,
                'desc' => 'LDAP server(s)/hostname(s)',
                'default' => is_array($host) ? implode(',', $host) : $host,
            ],

            'port' => [
                '_type' => 'int',
                'required' => false,
                'desc' => 'Port on which LDAP is listening, if non-standard',
                'default' => $this->_default(
                    $ctx . '|port',
                    $node ? ($xpath->evaluate('string(configinteger[@name="port"])', $node) ?: null) : null
                ),
            ],

            'tls' => [
                '_type' => 'boolean',
                'required' => false,
                'desc' => 'Use TLS to connect to the server?',
                'default' => $this->_default(
                    $ctx . '|tls',
                    $node ? ($xpath->evaluate('string(configboolean[@name="tls"])', $node) ?: false) : false
                ),
            ],

            'timeout' => [
                '_type' => 'int',
                'required' => false,
                'desc' => 'Connection timeout',
                'default' => $this->_default(
                    $ctx . '|timeout',
                    $node ? ($xpath->evaluate('string(configinteger[@name="timeout"])', $node) ?: 5) : 5
                ),
            ],

            'version' => [
                '_type' => 'int',
                'required' => true,
                'quote' => false,
                'desc' => 'LDAP protocol version',
                'default' => $this->_default(
                    $ctx . '|version',
                    $node ? ($xpath->evaluate('normalize-space(configswitch[@name="version"]/text())', $node) ?: 3) : 3
                ),
                'switch' => [
                    '2' => [
                        'desc' => '2 (deprecated)',
                        'fields' => [],
                    ],
                    '3' => [
                        'desc' => '3',
                        'fields' => [],
                    ],
                ],
            ],

            'bindas' => [
                'desc' => 'Bind to LDAP as which user?',
                'default' => $this->_default(
                    $ctx . '|bindas',
                    $node ? ($xpath->evaluate('normalize-space(configswitch[@name="bindas"]/text())', $node) ?: 'admin') : 'admin'
                ),
                'switch' => [
                    'anon' => [
                        'desc' => 'Bind anonymously',
                        'fields' => $this->_configLDAPUser($ctx, $node),
                    ],
                    'user' => [
                        'desc' => 'Bind as the currently logged-in user',
                        'fields' => $this->_configLDAPUser($ctx, $node),
                    ],
                    'admin' => [
                        'desc' => 'Bind with administrative/system credentials',
                        'fields' => array_merge(
                            [
                                'binddn' => [
                                    '_type' => 'text',
                                    'required' => true,
                                    'desc' => 'DN used to bind to LDAP',
                                    'default' => $this->_default(
                                        $ctx . '|binddn',
                                        $node ? ($xpath->evaluate('string(configsection/configstring[@name="binddn"])', $node) ?: '') : ''
                                    ),
                                ],
                                'bindpw' => [
                                    '_type' => 'text',
                                    'required' => true,
                                    'desc' => 'Password for bind DN',
                                    'default' => $this->_default(
                                        $ctx . '|bindpw',
                                        $node ? ($xpath->evaluate('string(configsection/configstring[@name="bindpw"])', $node) ?: '') : ''
                                    ),
                                ],
                            ],
                            $this->_configLDAPUser($ctx, $node)
                        ),
                    ],
                ],
            ],
        ];

        if (isset($node) && $node->getAttribute('excludebind')) {
            $excludes = explode(',', $node->getAttribute('excludebind'));
            foreach ($excludes as $exclude) {
                unset($fields['bindas']['switch'][$exclude]);
            }
        }

        if (isset($node) && $node->getAttribute('baseconfig') == 'true') {
            return [
                'desc' => 'Use LDAP?',
                'default' => $this->_default(
                    $ctx . '|' . $node->getAttribute('switchname'),
                    $node ? ($xpath->evaluate('normalize-space(text())', $node) ?: false) : false
                ),
                'switch' => [
                    'false' => [
                        'desc' => 'No',
                        'fields' => [],
                    ],
                    'true' => [
                        'desc' => 'Yes',
                        'fields' => $fields,
                    ],
                ],
            ];
        }

        $standardFields = [
            'basedn' => [
                '_type' => 'text',
                'required' => true,
                'desc' => 'Base DN',
                'default' => $this->_default(
                    $ctx . '|basedn',
                    $node ? ($xpath->evaluate('string(configstring[@name="basedn"])', $node) ?: '') : ''
                ),
            ],
            'scope' => [
                '_type' => 'enum',
                'required' => true,
                'desc' => 'Search scope',
                'default' => $this->_default(
                    $ctx . '|scope',
                    $node ? ($xpath->evaluate('normalize-space(configenum[@name="scope"]/text())', $node) ?: '') : ''
                ),
                'values' => [
                    'sub' => 'Subtree search',
                    'one' => 'One level'],
            ],
        ];

        [$default, $isDefault] = $this->__default($ctx . '|' . (isset($node) ? $node->getAttribute('switchname') : $switchname), 'horde');
        $config = [
            'desc' => 'Driver configuration',
            'default' => $default,
            'is_default' => $isDefault,
            'switch' => [
                'horde' => [
                    'desc' => 'Horde defaults',
                    'fields' => $standardFields,
                ],
                'custom' => [
                    'desc' => 'Custom parameters',
                    'fields' => $fields + $standardFields,
                ],
            ],
        ];

        if (isset($node) && $node->hasChildNodes()) {
            $cur = [];
            $this->_parseLevel($cur, $node->childNodes, $ctx);
            $config['switch']['horde']['fields'] = array_merge($config['switch']['horde']['fields'], $cur);
            $config['switch']['custom']['fields'] = array_merge($config['switch']['custom']['fields'], $cur);
        }

        return $config;
    }

    /**
     * Returns the configuration tree for an LDAP configuration to search user
     * DNs to replace a <configldapuser> tag.
     *
     * Subnodes will be parsed and added.
     *
     * @param string $ctx         The context of the <configldapuser> tag.
     * @param DomNode $node       The DomNode representation of the
     *                            <configldapuser> tag.
     *
     * @return array  A list of associative arrays with the LDAP configuration
     *                tree.
     */
    protected function _configLDAPUser($ctx, $node = null)
    {
        if ($node) {
            $xpath = new DOMXPath($node->ownerDocument);
        }

        return [
            'user' => [
                'basedn' => [
                    '_type' => 'text',
                    'required' => false,
                    'desc' => 'Base DN for searching the user\'s DN',
                    'default' => $this->_default(
                        $ctx . '|user|basedn',
                        $node ? ($xpath->evaluate('string(configsection/configstring[@name="basedn"])', $node) ?: '') : ''
                    ),
                ],
                'uid' => [
                    '_type' => 'text',
                    'required' => true,
                    'desc' => 'The username search key (set to samaccountname for AD).',
                    'default' => $this->_default(
                        $ctx . '|user|uid',
                        $node ? ($xpath->evaluate('string(configsection/configstring[@name="uid"])', $node) ?: 'uid') : 'uid'
                    ),
                ],
                'filter_type' => [
                    'required' => false,
                    'desc' => 'How to specify a filter for the user lists.',
                    'default' => $this->_default(
                        $ctx . '|user|filter_type',
                        $node ? ($xpath->evaluate('normalize-space(configsection/configswitch[@name="filter_type"]/text())', $node) ?: 'objectclass') : 'objectclass'
                    ),
                    'switch' => [
                        'filter' => [
                            'desc' => 'LDAP filter string',
                            'fields' => [
                                'filter' => [
                                    '_type' => 'text',
                                    'required' => true,
                                    'desc' => 'The LDAP filter string used to search for users.',
                                    'default' => $this->_default(
                                        $ctx . '|user|filter',
                                        $node ? ($xpath->evaluate('string(configsection/configstring[@name="filter"])', $node) ?: '(objectClass=*)') : '(objectClass=*)'
                                    ),
                                ],
                            ],
                        ],
                        'objectclass' => [
                            'desc' => 'List of objectClasses',
                            'fields' => [
                                'objectclass' => [
                                    '_type' => 'stringlist',
                                    'required' => true,
                                    'desc' => 'The objectclass filter used to search for users. Can be a single objectclass or a comma-separated list.',
                                    'default' => implode(', ', $this->_default(
                                        $ctx . '|user|objectclass',
                                        $node ? ($xpath->evaluate('string(configsection/configlist[@name="objectclass"])', $node) ?: ['*']) : ['*']
                                    )),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Returns the configuration tree for a NoSQL backend configuration to
     * replace a <confignosql> tag.
     * Subnodes will be parsed and added to both the Horde defaults and the
     * custom configuration parts.
     *
     * @param string $ctx         The context of the <confignosql> tag.
     * @param DomNode $node       The DomNode representation of the
     *                            <confignosql> tag.
     * @param string $switchname  If DomNode is not set, the value of the
     *                            tag's switchname attribute.
     *
     * @return array  An associative array with the SQL configuration tree.
     */
    public function configNoSQL(
        $ctx,
        $node = null,
        $switchname = 'driverconfig'
    ) {
        if ($node) {
            $xpath = new DOMXPath($node->ownerDocument);
        }

        $custom_fields = [
            'required' => true,
            'desc' => 'What database backend should we use?',
            'default' => $this->_default(
                $ctx . '|phptype',
                $node ? $node->getAttribute('default') : ''
            ),
            'switch' => [
                'false' => [
                    'desc' => '[None]',
                    'fields' => [],
                ],
                'mongo' => [
                    'desc' => 'MongoDB',
                    'fields' => [
                        'hostspec' => [
                            '_type' => 'text',
                            'required' => false,
                            'desc' => 'Server specification (format: "mongodb://[username:password@]host1[:port1][,host2[:port2:],...]/db"; see http://www.php.net/manual/en/mongoclient.construct.php for further details)',
                            'default' => $this->_default(
                                $ctx . '|hostspec',
                                $node ? ($xpath->evaluate('string(configstring[@name="hostspec"])', $node) ?: '') : ''
                            ),
                        ],
                        'dbname' => [
                            '_type' => 'text',
                            'required' => false,
                            'desc' => 'Database name to use',
                            'default' => $this->_default(
                                $ctx . '|dbname',
                                $node ? ($xpath->evaluate('string(configstring[@name="dbname"])', $node) ?: '') : ''
                            ),
                        ],
                    ],
                ],
            ],
        ];

        if (isset($node) && $node->getAttribute('baseconfig') == 'true') {
            return $custom_fields;
        }

        [$default, $isDefault] = $this->__default($ctx . '|' . (isset($node) ? $node->getAttribute('switchname') : $switchname), 'horde');
        $config = [
            'desc' => 'NoSQL driver configuration',
            'default' => $default,
            'is_default' => $isDefault,
            'switch' => [
                'horde' => [
                    'desc' => 'Horde defaults',
                    'fields' => [],
                ],
                'custom' => [
                    'desc' => 'Custom parameters',
                    'fields' => [
                        'phptype' => $custom_fields,
                    ],
                ],
            ],
        ];

        if (isset($node) && $node->hasChildNodes()) {
            $cur = [];
            $this->_parseLevel($cur, $node->childNodes, $ctx);
            $config['switch']['horde']['fields'] = array_merge($config['switch']['horde']['fields'], $cur);
            $config['switch']['custom']['fields'] = array_merge($config['switch']['custom']['fields'], $cur);
        }

        return $config;
    }

    /**
     * Returns the configuration tree for an SQL backend configuration to
     * replace a <configsql> tag.
     * Subnodes will be parsed and added to both the Horde defaults and the
     * Custom configuration parts.
     *
     * @param string $ctx         The context of the <configsql> tag.
     * @param DomNode $node       The DomNode representation of the <configsql>
     *                            tag.
     * @param string $switchname  If DomNode is not set, the value of the
     *                            tag's switchname attribute.
     *
     * @return array  An associative array with the SQL configuration tree.
     */
    public function configSQL($ctx, $node = null, $switchname = 'driverconfig')
    {
        // Try new metadata system first if enabled
        $useMetadata = isset($GLOBALS['conf']['config']['use_metadata'])
            && $GLOBALS['conf']['config']['use_metadata'];
        $injector = $this->_injector ?? $GLOBALS['injector'] ?? null;

        if ($useMetadata && $injector) {
            try {
                $provider = $injector->getInstance(
                    ConfigMetadataProvider::class
                );
                $adapter = new LegacyConfigAdapter($provider);

                // Get driver metadata converted to legacy format
                $result = $adapter->toConfigSQL('sql');
                $drivers = $result['switch'] ?? [];

                // Build the custom_fields structure expected by legacy code
                $custom_fields = [
                    'required' => true,
                    'desc' => 'What database backend should we use?',
                    'default' => $this->_default(
                        $ctx . '|phptype',
                        $node ? $node->getAttribute('default') : ''
                    ),
                    'switch' => array_merge(
                        [
                            'false' => [
                                'desc' => '[None]',
                                'fields' => [],
                            ],
                        ],
                        $drivers
                    ),
                ];

                // If baseconfig, return just the driver switch
                if (isset($node) && $node->getAttribute('baseconfig') == 'true') {
                    return $custom_fields;
                }

                // Otherwise wrap in the horde/custom switch
                [$default, $isDefault] = $this->__default(
                    $ctx . '|' . (isset($node) ? $node->getAttribute('switchname') : $switchname),
                    'horde'
                );

                $config = [
                    'desc' => 'Driver configuration',
                    'default' => $default,
                    'is_default' => $isDefault,
                    'switch' => [
                        'horde' => [
                            'desc' => 'Horde defaults',
                            'fields' => [],
                        ],
                        'custom' => [
                            'desc' => 'Custom parameters',
                            'fields' => [
                                'phptype' => $custom_fields,
                            ],
                        ],
                    ],
                ];

                if (isset($node) && $node->hasChildNodes()) {
                    $cur = [];
                    $this->_parseLevel($cur, $node->childNodes, $ctx);
                    $config['switch']['horde']['fields'] = array_merge(
                        $config['switch']['horde']['fields'],
                        $cur
                    );
                    $config['switch']['custom']['fields'] = array_merge(
                        $config['switch']['custom']['fields'],
                        $cur
                    );
                }

                return $config;
            } catch (Exception $e) {
                // Fall through to legacy implementation
            }
        }

        // Legacy implementation below
        if ($node) {
            $xpath = new DOMXPath($node->ownerDocument);
        }

        $hostspec = [
            '_type' => 'text',
            'required' => true,
            'desc' => 'Database server/host',
            'default' => $this->_default(
                $ctx . '|hostspec',
                $node ? ($xpath->evaluate('string(configstring[@name="hostspec"])', $node) ?: '') : ''
            ),
        ];

        $username = [
            '_type' => 'text',
            'required' => true,
            'desc' => 'Username to connect to the database as',
            'default' => $this->_default(
                $ctx . '|username',
                $node ? ($xpath->evaluate('string(configstring[@name="username"])', $node) ?: '') : ''
            ),
        ];

        $password = [
            '_type' => 'text',
            'required' => false,
            'desc' => 'Password to connect with',
            'default' => $this->_default(
                $ctx . '|password',
                $node ? ($xpath->evaluate('string(configstring[@name="password"])', $node) ?: '') : ''
            ),
        ];

        $database = [
            '_type' => 'text',
            'required' => true,
            'desc' => 'Database name to use',
            'default' => $this->_default(
                $ctx . '|database',
                $node ? ($xpath->evaluate('string(configstring[@name="database"])', $node) ?: '') : ''
            ),
        ];

        $socket = [
            '_type' => 'text',
            'required' => false,
            'desc' => 'Location of UNIX socket',
            'default' => $this->_default(
                $ctx . '|socket',
                $node ? ($xpath->evaluate('string(configstring[@name="socket"])', $node) ?: '') : ''
            ),
        ];

        $port = [
            '_type' => 'int',
            'required' => false,
            'desc' => 'Port the DB is running on, if non-standard',
            'default' => $this->_default(
                $ctx . '|port',
                $node ? ($xpath->evaluate('string(configinteger[@name="port"])', $node) ?: null) : null
            ),
        ];

        $protocol = [
            'desc' => 'How should we connect to the database?',
            'default' => $this->_default(
                $ctx . '|protocol',
                $node ? ($xpath->evaluate('normalize-space(configswitch[@name="protocol"]/text())', $node) ?: 'unix') : 'unix'
            ),
            'switch' => [
                'unix' => [
                    'desc' => 'UNIX Sockets',
                    'fields' => [
                        'socket' => $socket,
                    ],
                ],
                'tcp' => [
                    'desc' => 'TCP/IP',
                    'fields' => [
                        'hostspec' => $hostspec,
                        'port' => $port,
                    ],
                ],
            ],
        ];

        $mysql_protocol = $protocol;
        $mysql_protocol['switch']['tcp']['fields']['port']['default']
            = $this->_default(
                $ctx . '|port',
                $node ? ($xpath->evaluate('string(configinteger[@name="port"])', $node) ?: 3306) : 3306
            );

        $pgsql_protocol = $protocol;
        $pgsql_protocol['switch']['unix']['fields']['port'] = $port;

        $charset = [
            '_type' => 'text',
            'required' => true,
            'desc' => 'Internally used charset',
            'default' => $this->_default(
                $ctx . '|charset',
                $node ? ($xpath->evaluate('string(configstring[@name="charset"])', $node) ?: 'utf-8') : 'utf-8'
            ),
        ];

        $ssl = [
            '_type' => 'boolean',
            'required' => false,
            'desc' => 'Use SSL to connect to the server?',
            'default' => $this->_default(
                $ctx . '|ssl',
                $node ? ($xpath->evaluate('string(configboolean[@name="ssl"])', $node) ?: false) : false
            ),
            'switch' => [
                'false' => ['desc' => 'No', 'fields' => []],
                'true' => [
                    'desc' => 'Yes',
                    'fields' => [
                        'ca' => [
                            '_type' => 'text',
                            'required' => false,
                            'desc' => 'Certification Authority to use for SSL connections',
                            'default' => $this->_default(
                                $ctx . '|ca',
                                $node ? ($xpath->evaluate('string(configstring[@name="ca"])', $node) ?: '') : ''
                            ),
                        ],
                    ],
                ],
            ],
        ];

        [$default, $isDefault] = $this->__default(
            $ctx . '|logqueries',
            $node
            ? ($xpath->evaluate('string(configboolean[@name="logqueries"])', $node) ?: false)
            : false
        );
        $logqueries = [
            '_type' => 'boolean',
            'required' => false,
            'desc' => 'Should Horde log all queries. If selected, queries will be logged at the DEBUG level to your configured logger.',
            'default' => $default,
            'is_default' => $isDefault,
        ];

        $custom_fields = [
            'required' => true,
            'desc' => 'What database backend should we use?',
            'default' => $this->_default(
                $ctx . '|phptype',
                $node ? $node->getAttribute('default') : ''
            ),
            'switch' => [
                'false' => [
                    'desc' => '[None]',
                    'fields' => [],
                ],
                'mysql' => [
                    'desc' => 'MySQL / PDO',
                    'fields' => [
                        'username' => $username,
                        'password' => $password,
                        'protocol' => $mysql_protocol,
                        'database' => $database,
                        'charset' => $charset,
                        'ssl' => $ssl,
                        'splitread' => $this->_configSQLSplitRead($ctx, $node, 'mysql'),
                        'logqueries' => $logqueries,
                    ],
                ],
                'mysqli' => [
                    'desc' => 'MySQL (mysqli)',
                    'fields' => [
                        'username' => $username,
                        'password' => $password,
                        'protocol' => $mysql_protocol,
                        'database' => $database,
                        'charset' => $charset,
                        'ssl' => $ssl,
                        'splitread' => $this->_configSQLSplitRead($ctx, $node, 'mysqli'),
                        'logqueries' => $logqueries,
                    ],
                ],
                'oci8' => [
                    'desc' => 'Oracle',
                    'fields' => [
                        'username' => $username,
                        'password' => $password,
                        'charset' => $charset,
                        'method' => [
                            'desc' => 'How should the database connection be specified',
                            'default' => $this->_default(
                                $ctx . '|method',
                                $node ? ($xpath->evaluate('normalize-space(configswitch[@name="method"]/text())', $node) ?: 'easy') : 'easy'
                            ),
                            'switch' => [
                                'tns' => [
                                    'desc' => 'TNS',
                                    'fields' => [
                                        'tns' => [
                                            '_type' => 'text',
                                            'required' => true,
                                            'desc' => 'Connect name from tnsnames.ora',
                                            'default' => $this->_default(
                                                $ctx . '|tns',
                                                $node ? ($xpath->evaluate('string(configstring[@name="tns"])', $node) ?: false) : false
                                            ),
                                        ],
                                    ],
                                ],
                                'easy' => [
                                    'desc' => 'Easy Connect',
                                    'fields' => [
                                        'hostspec' => $hostspec,
                                        'port' => $port,
                                        'service' => [
                                            '_type' => 'text',
                                            'required' => false,
                                            'desc' => 'Service name',
                                            'default' => $this->_default(
                                                $ctx . '|service',
                                                $node ? ($xpath->evaluate('string(configstring[@name="service"])', $node) ?: false) : false
                                            ),
                                        ],
                                        'type' => [
                                            '_type' => 'text',
                                            'required' => false,
                                            'desc' => 'Server type',
                                            'default' => $this->_default(
                                                $ctx . '|type',
                                                $node ? ($xpath->evaluate('string(configstring[@name="type"])', $node) ?: false) : false
                                            ),
                                        ],
                                        'instance' => [
                                            '_type' => 'text',
                                            'required' => false,
                                            'desc' => 'Instance name',
                                            'default' => $this->_default(
                                                $ctx . '|instance',
                                                $node ? ($xpath->evaluate('string(configstring[@name="instance"])', $node) ?: false) : false
                                            ),
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'logqueries' => $logqueries,
                    ],
                ],
                'pgsql' => [
                    'desc' => 'PostgreSQL',
                    'fields' => [
                        'username' => $username,
                        'password' => $password,
                        'protocol' => $pgsql_protocol,
                        'database' => $database,
                        'charset' => $charset,
                        'splitread' => $this->_configSQLSplitRead($ctx, $node, 'pgsql'),
                        'logqueries' => $logqueries,
                    ],
                ],
                'sqlite' => [
                    'desc' => 'SQLite',
                    'fields' => [
                        'database' => [
                            '_type' => 'text',
                            'required' => true,
                            'desc' => 'Absolute path to the database file',
                            'default' => $this->_default(
                                $ctx . '|database',
                                $node ? ($xpath->evaluate('string(configstring[@name="database"])', $node) ?: '') : ''
                            ),
                        ],
                        'charset' => $charset,
                        'logqueries' => $logqueries,
                    ],
                ],
            ],
        ];

        if (isset($node) && $node->getAttribute('baseconfig') == 'true') {
            return $custom_fields;
        }

        [$default, $isDefault] = $this->__default($ctx . '|' . (isset($node) ? $node->getAttribute('switchname') : $switchname), 'horde');
        $config = [
            'desc' => 'Driver configuration',
            'default' => $default,
            'is_default' => $isDefault,
            'switch' => [
                'horde' => [
                    'desc' => 'Horde defaults',
                    'fields' => [],
                ],
                'custom' => [
                    'desc' => 'Custom parameters',
                    'fields' => [
                        'phptype' => $custom_fields,
                    ],
                ],
            ],
        ];

        if (isset($node) && $node->hasChildNodes()) {
            $cur = [];
            $this->_parseLevel($cur, $node->childNodes, $ctx);
            $config['switch']['horde']['fields'] = array_merge($config['switch']['horde']['fields'], $cur);
            $config['switch']['custom']['fields'] = array_merge($config['switch']['custom']['fields'], $cur);
        }

        return $config;
    }

    /**
     * Returns the configuration items for split-read database setups.
     *
     * @param string $ctx         The context of the <configsql> tag.
     * @param DomNode $node       The DomNode representation of the <configsql>
     *                            tag.
     * @param string $phptype     The SQL backend name.
     *
     * @return array  An associative array with the split-read SQL
     *                configuration tree.
     */
    protected function _configSQLSplitRead($ctx, $node, $phptype)
    {
        if (preg_match('/\|read$/', $ctx)) {
            return null;
        }

        if ($node) {
            $xpath = new DOMXPath($node->ownerDocument);
        }

        $splitread_fields = $this->configSQL($ctx . '|read');
        $splitread_fields = $splitread_fields['switch']['custom']['fields']['phptype']['switch'][$phptype]['fields'];
        return [
            '_type' => 'boolean',
            'required' => false,
            'desc' => 'Split reads to a different server?',
            'default' => $this->_default(
                $ctx . '|splitread',
                $node ? ($xpath->evaluate('normalize-space(configswitch[@name="splitread"]/text())', $node) ?: 'false') : 'false'
            ),
            'switch' => [
                'false' => [
                    'desc' => 'Disabled',
                    'fields' => [],
                ],
                'true' => [
                    'desc' => 'Enabled',
                    'fields' => ['read' => $splitread_fields],
                ],
            ],
        ];
    }

    /**
     * Returns the configuration tree for a VFS backend configuration to
     * replace a <configvfs> tag.
     * Subnodes will be parsed and added to both the Horde defaults and the
     * Custom configuration parts.
     *
     * @param string $ctx    The context of the <configvfs> tag.
     * @param DomNode $node  The DomNode representation of the <configvfs>
     *                       tag.
     *
     * @return array  An associative array with the VFS configuration tree.
     */
    protected function _configVFS($ctx, $node)
    {
        $nosql = $this->configNoSQL($ctx . '|params');
        $sql = $this->configSQL($ctx . '|params');
        $default = $node->getAttribute('default');
        $default = empty($default) ? 'horde' : $default;
        [$default, $isDefault] = $this->__default($ctx . '|' . $node->getAttribute('switchname'), $default);
        $xpath = new DOMXPath($node->ownerDocument);

        $config = [
            'desc' => 'What VFS driver should we use?',
            'default' => $default,
            'is_default' => $isDefault,
            'switch' => [
                'None' => [
                    'desc' => 'None',
                    'fields' => [],
                ],
                'File' => [
                    'desc' => 'Files on the local system',
                    'fields' => [
                        'params' => [
                            'vfsroot' => [
                                '_type' => 'text',
                                'desc' => 'Where on the real filesystem should Horde use as root of the virtual filesystem?',
                                'default' => $this->_default(
                                    $ctx . '|params|vfsroot',
                                    $xpath->evaluate('string(configsection/configstring[@name="vfsroot"])', $node) ?: '/tmp'
                                ),
                            ],
                        ],
                    ],
                ],
                'Nosql' => [
                    'desc' => 'NoSQL database',
                    'fields' => [
                        'params' => [
                            'driverconfig' => $nosql,
                        ],
                    ],
                ],
                'Sql' => [
                    'desc' => 'SQL database',
                    'fields' => [
                        'params' => [
                            'driverconfig' => $sql,
                        ],
                    ],
                ],
                'Ssh2' => [
                    'desc' => 'SSH2 (SFTP)',
                    'fields' => [
                        'params' => [
                            'hostspec' => [
                                '_type' => 'text',
                                'required' => true,
                                'desc' => 'SSH server/host',
                                'default' => $this->_default(
                                    $ctx . '|hostspec',
                                    $xpath->evaluate('string(configsection/configstring[@name="hostspec"])', $node) ?: ''
                                ),
                            ],
                            'port' => [
                                '_type' => 'text',
                                'required' => false,
                                'desc' => 'Port number on which SSH listens',
                                'default' => $this->_default(
                                    $ctx . '|port',
                                    $xpath->evaluate('string(configsection/configstring[@name="port"])', $node) ?: '22'
                                ),
                            ],
                            'username' => [
                                '_type' => 'text',
                                'required' => true,
                                'desc' => 'Username to connect to the SSH server',
                                'default' => $this->_default(
                                    $ctx . '|username',
                                    $xpath->evaluate('string(configsection/configstring[@name="username"])', $node) ?: ''
                                ),
                            ],
                            'password' => [
                                '_type' => 'text',
                                'required' => true,
                                'desc' => 'Password with which to connect',
                                'default' => $this->_default(
                                    $ctx . '|password',
                                    $xpath->evaluate('string(configsection/configstring[@name="password"])', $node) ?: ''
                                ),
                            ],
                            'vfsroot' => [
                                '_type' => 'text',
                                'desc' => 'Where on the real filesystem should Horde use as root of the virtual filesystem?',
                                'default' => $this->_default(
                                    $ctx . '|vfsroot',
                                    $xpath->evaluate('string(configsection/configstring[@name="vfsroot"])', $node) ?: '/tmp'
                                ),
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if (isset($node) && $node->getAttribute('baseconfig') != 'true') {
            $config['switch']['horde'] = [
                'desc' => 'Horde defaults',
                'fields' => [],
            ];
        }
        $cases = $this->_getSwitchValues($node, $ctx . '|params');
        foreach ($cases as $case => $fields) {
            if (isset($config['switch'][$case])) {
                $config['switch'][$case]['fields']['params'] = array_merge($config['switch'][$case]['fields']['params'], $fields['fields']);
            }
        }

        return $config;
    }

    /**
     * Returns a certain value from the current configuration array or
     * a default value, if not found.
     *
     * @param string $ctx     A string representing the key of the
     *                        configuration array to return.
     * @param mixed $default  The default value to return if the key wasn't
     *                        found.
     *
     * @return mixed  Either the value of the configuration array's requested
     *                key or the default value if the key wasn't found.
     */
    protected function _default($ctx, $default)
    {
        [$ptr, ] = $this->__default($ctx, $default);
        return $ptr;
    }

    /**
     * Returns whether a certain value from the current configuration array
     * exists or a default value will be used.
     *
     * @param string $ctx     A string representing the key of the
     *                        configuration array to return.
     * @param mixed $default  The default value to return if the key wasn't
     *                        found.
     *
     * @return boolean  Whether the default value will be used.
     */
    protected function _isDefault($ctx, $default)
    {
        [, $isDefault] = $this->__default($ctx, $default);
        return $isDefault;
    }

    /**
     * Returns a certain value from the current configuration array or a
     * default value, if not found, and which of the values have been
     * returned.
     *
     * @param string $ctx     A string representing the key of the
     *                        configuration array to return.
     * @param mixed $default  The default value to return if the key wasn't
     *                        found.
     *
     * @return array  First element: either the value of the configuration
     *                array's requested key or the default value if the key
     *                wasn't found.
     *                Second element: whether the returned value was the
     *                default value.
     */
    protected function __default($ctx, $default)
    {
        $ctx = explode('|', $ctx);
        $ptr = $this->_currentConfig;

        for ($i = 0, $ctx_count = count($ctx); $i < $ctx_count; ++$i) {
            if (!isset($ptr[$ctx[$i]])) {
                return [$default, true];
            }

            $ptr = $ptr[$ctx[$i]];
        }

        return [$ptr, false];
    }

    /**
     * Returns a certain value from the current configuration file or
     * a default value, if not found.
     * It does NOT return the actual value, but the PHP expression as used
     * in the configuration file.
     *
     * @param string $ctx     A string representing the key of the
     *                        configuration array to return.
     * @param mixed $default  The default value to return if the key wasn't
     *                        found.
     *
     * @return mixed  Either the value of the configuration file's requested
     *                key or the default value if the key wasn't found.
     */
    protected function _defaultRaw($ctx, $default)
    {
        [$ptr, ] = $this->__defaultRaw($ctx, $default);
        return $ptr;
    }

    /**
     * Returns whether a certain value from the current configuration array
     * exists or a default value will be used.
     *
     * @param string $ctx     A string representing the key of the
     *                        configuration array to return.
     * @param mixed $default  The default value to return if the key wasn't
     *                        found.
     *
     * @return boolean  Whether the default value will be used.
     */
    protected function _isDefaultRaw($ctx, $default)
    {
        [, $isDefault] = $this->__defaultRaw($ctx, $default);
        return $isDefault;
    }

    /**
     * Returns a certain value from the current configuration file or
     * a default value, if not found, and which of the values have been
     * returned.
     *
     * It does NOT return the actual value, but the PHP expression as used
     * in the configuration file.
     *
     * @param string $ctx     A string representing the key of the
     *                        configuration array to return.
     * @param mixed $default  The default value to return if the key wasn't
     *                        found.
     *
     * @return array  First element: either the value of the configuration
     *                array's requested key or the default value if the key
     *                wasn't found.
     *                Second element: whether the returned value was the
     *                default value.
     */
    protected function __defaultRaw($ctx, $default)
    {
        $ctx = explode('|', $ctx);
        $pattern = '/^\$conf\[\'' . implode("'\]\['", $ctx) . '\'\] = (.*);\r?$/m';

        return preg_match($pattern, $this->getPHPConfig(), $matches)
            ? [$matches[1], false]
            : [$default, true];
    }

    /**
     * Returns the content of all text node children of the specified node.
     *
     * @param DomNode $node  A DomNode object whose text node children to
     *                       return.
     *
     * @return string  The concatenated values of all text nodes.
     */
    protected function _getNodeOnlyText($node)
    {
        $text = '';

        if (!$node->hasChildNodes()) {
            return $node->textContent;
        }

        foreach ($node->childNodes as $tnode) {
            if ($tnode->nodeType == XML_TEXT_NODE) {
                $text .= $tnode->textContent;
            }
        }

        return trim($text);
    }

    /**
     * Returns an associative array containing all possible values of the
     * specified <configenum> tag.
     *
     * The keys contain the actual enum values while the values contain their
     * corresponding descriptions.
     *
     * @param DomNode $node  The DomNode representation of the <configenum>
     *                       tag whose values should be returned.
     *
     * @return array  An associative array with all possible enum values.
     */
    protected function _getEnumValues($node)
    {
        $values = [];

        if (!$node->hasChildNodes()) {
            return $values;
        }

        foreach ($node->childNodes as $vnode) {
            if ($vnode->nodeType == XML_ELEMENT_NODE
                && $vnode->tagName == 'values') {
                if (!$vnode->hasChildNodes()) {
                    return [];
                }

                foreach ($vnode->childNodes as $value) {
                    if ($value->nodeType == XML_ELEMENT_NODE) {
                        if ($value->tagName == 'configspecial') {
                            return $this->_handleSpecials($value);
                        }
                        if ($value->tagName == 'value') {
                            $text = $value->textContent;
                            $desc = $value->getAttribute('desc');
                            $values[$text] = empty($desc) ? $text : $desc;
                        }
                    }
                }
            }
        }

        return $values;
    }

    /**
     * Returns a multidimensional associative array representing the specified
     * <configswitch> tag.
     *
     * @param DomNode &$node  The DomNode representation of the <configswitch>
     *                        tag to process.
     *
     * @return array  An associative array representing the node.
     */
    protected function _getSwitchValues(&$node, $curctx)
    {
        $values = [];

        if (!$node->hasChildNodes()) {
            return $values;
        }

        foreach ($node->childNodes as $case) {
            if ($case->nodeType == XML_ELEMENT_NODE) {
                $name = $case->getAttribute('name');
                $values[$name] = [
                    'desc' => $case->getAttribute('desc'),
                    'fields' => [],
                ];
                if ($case->hasChildNodes()) {
                    $this->_parseLevel($values[$name]['fields'], $case->childNodes, $curctx);
                }
            }
        }

        return $values;
    }

    /**
     * Returns an associative array containing the possible values of a
     * <configspecial> tag as used inside of enum configurations.
     *
     * @param DomNode $node  The DomNode representation of the <configspecial>
     *                       tag.
     *
     * @return array  An associative array with the possible values.
     */
    protected function _handleSpecials($node)
    {
        $registry = $GLOBALS['registry'];
        $app = $node->getAttribute('application');
        try {
            if (!in_array($app, $registry->listAllApps())) {
                $app = $registry->hasInterface($app);
            }
        } catch (Horde_Exception $e) {
            return [];
        }
        if (!$app) {
            return [];
        }

        try {
            return $registry->callAppMethod($app, 'configSpecialValues', ['args' => [$node->getAttribute('name')], 'noperms' => true]);
        } catch (Horde_Exception $e) {
            /* callAppMethod failed — likely because pushApp() rejected an
             * unconfigured/inactive app. Fall back to calling
             * configSpecialValues() directly without full app init. */
            try {
                $appOb = $registry->getApiInstance($app, 'application');
                if (method_exists($appOb, 'configSpecialValues')) {
                    return $appOb->configSpecialValues($node->getAttribute('name'));
                }
            } catch (Throwable $e2) {
                Horde::log('configSpecialValues failed for ' . $app . ': ' . $e2->getMessage(), 'DEBUG');
            }
            return [];
        }
    }

    /**
     * Returns the specified string with escaped single quotes
     *
     * @param string $string  A string to escape.
     *
     * @return string  The specified string with single quotes being escaped.
     */
    protected function _quote($string)
    {
        return str_replace("'", "\'", $string);
    }

}
