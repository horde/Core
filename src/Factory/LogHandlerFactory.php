<?php
/**
 * Horde/Log PSR-3 Logger Factory
 *
 * @author Ralf Lang <lang@b1-systems.de>
 *
 */

namespace Horde\Core\Factory;

use Horde_Core_Factory_Injector;
use Horde\Log\Logger;
use Horde\Core\Config\State;
use Horde\Injector\Injector;
use Horde\Log\Formatter\Psr3Formatter;
use Horde\Log\Formatter\SimpleFormatter;
use Horde\Log\Formatter\XmlFormatter;
use Horde\Log\LogLevels;
use Horde\Log\Handler\NullHandler;
use Horde\Log\Handler\Options;
use Horde\Log\Handler\StreamHandler;
use Horde\Log\Handler\SyslogHandler;
use Horde\Log\Handler\SyslogOptions;
use Horde\Log\LogHandler;
use Horde\Log\LogException;

/**
 * LogHandlerFactory builds individual LogHandlers
 *
 */
class LogHandlerFactory extends Horde_Core_Factory_Injector
{
    private State $conf;

    /**
     * Constructor
     *
     *
     * @param State $config The conf.php values for the global log handler
     *
     */
    public function __construct(State $config)
    {
        $this->conf = $config;
    }

    /**
     * Create default LogHandler
     *
     * This creates a LogHandler with
     *   configuration from conf.php
     * - the PSR-3 formatter and depending on options, another formatter,
     * - a handler-level filter by loglevel as the config suggests
     *
     * TODO: Mechanism to add and expose custom handlers
     *
     * @param Injector $injector
     * @return LogHandler
     * @throws LogException
     */
    public function create(Injector $injector): LogHandler
    {
        $conf = $this->conf->toArray();
        $formatters = [new Psr3Formatter()];

        switch ($conf['log']['type']) {
        case 'file':
        case 'stream':
            // TODO: Default context?

            $append = ($conf['log']['type'] == 'file')
                ? ($conf['log']['params']['append'] ? 'a+' : 'w+')
                : null;
            $format = $conf['log']['params']['format']
                ?? 'default';

            switch ($format) {
            case 'custom':
                $formatters[] = new SimpleFormatter(['format' => $conf['log']['params']['template']]);
                break;

            case 'default':
            default:
                // Use Horde_Log defaults.
                break;

            case 'xml':
                $formatters[] = new XmlFormatter();
                break;
            }

            $options = new Options();
            $options->ident = (string) $conf['log']['ident'] ?? '';
            // Let's not try and catch. Let it fail, the caller should care
            $handler = new StreamHandler($conf['log']['name'], $append, $options, $formatters);
            break;

        case 'syslog':
            $options = new SyslogOptions();
            if (!empty($conf['log']['name'] && is_numeric($conf['log']['name']))) {
                $options->facility = (int) $conf['log']['name'];
            }
            if (!empty($conf['log']['ident'])) {
                $options->ident = (string) $conf['log']['ident'];
            }
            $handler = new SyslogHandler($options, $formatters, []);
            break;

        case 'null':
        default:
            // Use default null handler.
            return new NullHandler();
        }

        switch ($conf['log']['priority']) {
        case 'WARNING':
            // Bug #12109
            $priority = 'WARN';
            break;

        default:
            $priority = defined('Horde_Log::' . $conf['log']['priority'])
                ? $conf['log']['priority']
                : 'NOTICE';
            break;
        }
        $handler->addFilter(constant('Horde_Log::' . $priority));
        return $handler;
    }

    public function createNullHandler(): NullHandler
    {
        return new NullHandler();
    }

    public function createStreamHandler($streamOrUrl, string $mode = 'a+', array $formatters = null, array $filters = []): StreamHandler
    {
        $options = new Options();
        $handler = new StreamHandler($streamOrUrl, $mode, $options, $formatters);
        foreach ($filters as $filter) {
            $handler->addFilter($filter);
        }
        return $handler;
    }

    public function createSyslogHandler(array $formatters, array $filters = []): SyslogHandler
    {
        $handler = new SyslogHandler(new SyslogOptions(), $formatters, $filters);
        return $handler;
    }
}
