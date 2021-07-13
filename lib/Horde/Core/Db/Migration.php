<?php
/**
 * Horde_Core_Db_Migration provides a wrapper for all migration scripts
 * distributed through Horde applications or libraries.
 *
 * Copyright 2011-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author  Jan Schneider <jan@horde.org>
 * @package Core
 */

/**
 * @author  Jan Schneider <jan@horde.org>
 * @package Core
 */
class Horde_Core_Db_Migration
{
    /**
     * List of all migration directories.
     *
     * @var array
     */
    public $dirs = array();

    /**
     * List of all module names matching the directories in $dirs.
     *
     * @var array
     */
    public $apps = array();

    /**
     * List of all lower case module names matching the directories in $dirs.
     *
     * @var array
     */
    protected $_lower = array();

    /**
     * Constructor.
     *
     * Searches all installed applications and libraries for migration
     * directories and builds lists of migrateable modules and directories.
     *
     * @param string $basedir   Base directory of all Git checkouts.
     * @param string $pearconf  Path to a PEAR configuration file.
     */
    public function __construct($basedir = null, $pearconf = null)
    {
        // Loop through all applications.
        foreach ($GLOBALS['registry']->listAllApps() as $app) {
            $dir = $GLOBALS['registry']->get('fileroot', $app) . '/migration';
            if (is_dir($dir)) {
                $this->apps[] = $app;
                $this->_lower[] = Horde_String::lower($app);
                $this->dirs[] = realpath($dir);
            }
        }

        // Silence PEAR errors.
        $old_error_reporting = error_reporting();
        error_reporting($old_error_reporting & ~E_DEPRECATED);
        $pear = new PEAR_Config($pearconf);

        // Detect and handle the Composer use case
        if (class_exists('Composer\Autoload\ClassLoader', false)) {
            /*
              This is a bit brittle. We know where migration
              is relative to the package root and that it should
              be in the vendor dir - wherever that is. We also
              know migration files have a canonical place inside a package
              So loop through vendors and packages to identify packages with a 
              migration dir. We cannot hardcode the horde vendor, this would
              break third party solutions using the horde framework.
              Would be more fun to deduce from the package lock file
            */
            $vendorDir = dirname(__FILE__, 7);
            // Loop over all vendors/packages in vendor dir
            $vendorIterator = new DirectoryIterator("$vendorDir/");
            foreach ($vendorIterator as $vendor) {
                if (!is_dir("$vendorDir/$vendor/")) {
                    continue;
                }
                $packageIterator = new DirectoryIterator("$vendorDir/$vendor/");
                foreach ($packageIterator as $package) {
                    if (!is_dir("$vendorDir/$vendor/$package/migration")) {
                        continue;
                    }
                    // Apps don't go into vendor dir so it's always a lib.
                    // hope this doesn't break for three part names
                    $lcFullname = Horde_String::lower($vendor. '_' . $package);
                    $parts = [];
                    foreach (explode('_', $lcFullname) as $part) {
                        $parts[] = ucfirst($part);
                    }
                    $ucFullname = implode('_', $parts);
                    $this->apps[] = $ucFullname;
                    $this->_lower[] = $lcFullname;
                    $this->dirs[] = realpath("$vendorDir/$vendor/$package/migration");
                }
            }
        }

        // Loop through local framework checkouts.
        elseif ($basedir) {
            $path = $basedir . '/*/migration';
            foreach (glob($path) as $dir) {
                try {
                    $package = Horde_Yaml::loadFile($dir . '/../.horde.yml');
                } catch (Horde_Yaml_Exception $e) {
                    Horde::log(sprintf('Horde DB Migration failed loading: %s', $e->getMessage()), Horde_Log::ERR);
                    continue;
                }
                // Compat with pear style names. YAML library names do not include Horde_
                $name = $package['type'] == 'library' ? 'Horde_' . $package['name'] : $package['name'];
                $this->apps[] = $name;
                $this->_lower[] = Horde_String::lower($name);
                $this->dirs[] = realpath($dir);
            }
        }

        // Loop through installed PEAR packages.
        $registry = $pear->getRegistry();
        foreach (glob($pear->get('data_dir') . '/*/migration') as $dir) {
            $package = $registry->getPackage(
                basename(dirname($dir)), 'pear.horde.org');
            if ($package == false) {
                Horde::log("Ignoring package in directory $dir", Horde_Log::WARN);
                continue;
            }

            $app = $package->getName();
            if (!in_array($app, $this->apps)) {
                $this->apps[] = $app;
                $this->_lower[] = Horde_String::lower($app);
                $this->dirs[] = realpath($dir);
            }
        }
        error_reporting($old_error_reporting);
    }

    /**
     * Returns a migrator for a module.
     *
     * @param string $app               An application or library name.
     * @param Horde_Log_Logger $logger  A logger instance.
     *
     * @return Horde_Db_Migration_Migrator  A migrator for the specified module.
     */
    public function getMigrator($app, Horde_Log_Logger $logger = null)
    {
        $app = Horde_String::lower($app);
        $db = $GLOBALS['injector']->getInstance('Horde_Db_Adapter');
        return new Horde_Db_Migration_Migrator(
            $db,
            $logger,
            array(
                'migrationsPath' => $this->dirs[array_search($app, $this->_lower)],
                'schemaTableName' => $db->tableAliasFor($app . '_schema_info'))
            );
    }
}
