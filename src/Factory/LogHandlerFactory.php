<?php

/**
 * Horde/Log PSR-3 Logger Factory
 *
 * @author Ralf Lang <ralf.lang@ralf-lang.de>
 *
 */

namespace Horde\Core\Factory;

use Horde_Core_Factory_Injector;
use Horde\Log\Logger;
use Horde\Core\Config\LoggerConfig;
use Horde\Injector\Injector;
use Horde\Log\Filter\MaximumLevelFilter;
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
    private LoggerConfig $config;

    /**
     * Constructor
     *
     * @param LoggerConfig $config Logger configuration service
     */
    public function __construct(LoggerConfig $config)
    {
        $this->config = $config;
    }

    /**
     * Create default LogHandler
     *
     * This creates a LogHandler with configuration from conf.php via
     * LoggerConfig service:
     * - the PSR-3 formatter and depending on options, another formatter,
     * - a handler-level filter by loglevel as the config suggests
     *
     * @param Injector $injector
     * @return LogHandler
     * @throws LogException
     */
    public function create(Injector $injector): LogHandler
    {
        $formatters = [new Psr3Formatter()];

        switch ($this->config->getType()) {
            case 'file':
            case 'stream':
                $append = ($this->config->getType() === 'file')
                    ? $this->config->getAppendMode()
                    : null;

                switch ($this->config->getFormat()) {
                    case 'custom':
                        if ($this->config->getTemplate() !== null) {
                            $formatters[] = new SimpleFormatter(['format' => $this->config->getTemplate()]);
                        }
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
                $options->ident = $this->config->getIdent();
                $handler = new StreamHandler(
                    streamOrUrl: $this->config->getName(),
                    mode: $append,
                    options: $options,
                    formatters: $formatters
                );
                break;

            case 'syslog':
                $options = new SyslogOptions();
                $facility = $this->config->getFacility();
                if ($facility !== null) {
                    $options->facility = $facility;
                }
                $ident = $this->config->getIdent();
                if (!empty($ident)) {
                    $options->ident = $ident;
                }
                $handler = new SyslogHandler(
                    options: $options,
                    formatters: $formatters,
                    filters: []
                );
                break;

            case 'null':
            default:
                // Use default null handler.
                return new NullHandler();
        }

        $handler->addFilter(new MaximumLevelFilter($this->config->getPriorityValue()));
        return $handler;
    }

    public function createNullHandler(): NullHandler
    {
        return new NullHandler();
    }

    public function createStreamHandler($streamOrUrl, string $mode = 'a+', ?array $formatters = null, array $filters = []): StreamHandler
    {
        $options = new Options();
        $handler = new StreamHandler(
            streamOrUrl: $streamOrUrl,
            mode: $mode,
            options: $options,
            formatters: $formatters
        );
        foreach ($filters as $filter) {
            $handler->addFilter($filter);
        }
        return $handler;
    }

    public function createSyslogHandler(array $formatters, array $filters = []): SyslogHandler
    {
        $handler = new SyslogHandler(
            options: new SyslogOptions(),
            formatters: $formatters,
            filters: $filters
        );
        return $handler;
    }
}
