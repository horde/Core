<?php

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Ralf Lang <lang@b1-systems.de>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL
 * @package  Core
 */
declare(strict_types=1);

namespace Horde\Core\Config;

/**
 * Logger configuration service
 *
 * Parses conf.php log settings once and provides structured configuration
 * to both legacy and modern logger factories. This ensures consistent
 * configuration interpretation across both implementations.
 *
 * @author   Ralf Lang <lang@b1-systems.de>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL
 * @package  Core
 */
class LoggerConfig
{
    private State $conf;
    private ?array $parsed = null;

    /**
     * Constructor
     *
     * @param State $conf Configuration state object wrapping $GLOBALS['conf']
     */
    public function __construct(State $conf)
    {
        $this->conf = $conf;
    }

    /**
     * Parse configuration once and cache
     *
     * @return array Parsed configuration values
     */
    private function parse(): array
    {
        if ($this->parsed !== null) {
            return $this->parsed;
        }

        $conf = $this->conf->toArray();
        $logConf = $conf['log'] ?? [];

        $this->parsed = [
            'enabled' => $logConf['enabled'] ?? true,
            'type' => $logConf['type'] ?? 'null',
            'name' => $logConf['name'] ?? '',
            'ident' => $logConf['ident'] ?? '',
            'priority' => $this->normalizePriority($logConf['priority'] ?? 'NOTICE'),
            'format' => $logConf['params']['format'] ?? 'default',
            'template' => $logConf['params']['template'] ?? null,
            'append' => $logConf['params']['append'] ?? true,
            'facility' => is_numeric($logConf['name'] ?? null) ? (int) $logConf['name'] : null,
        ];

        return $this->parsed;
    }

    /**
     * Normalize priority level
     *
     * Handles special case for Bug #12109 where WARNING should map to WARN.
     * Also validates that the priority constant exists.
     *
     * @param string $priority Raw priority from configuration
     * @return string Normalized priority constant name
     */
    private function normalizePriority(string $priority): string
    {
        // Bug #12109: WARNING should be WARN
        if ($priority === 'WARNING') {
            return 'WARN';
        }

        // Validate constant exists, fallback to NOTICE
        return defined('Horde_Log::' . $priority) ? $priority : 'NOTICE';
    }

    /**
     * Is logging enabled?
     *
     * @return bool True if logging is enabled in configuration
     */
    public function isEnabled(): bool
    {
        return $this->parse()['enabled'];
    }

    /**
     * Get logger type
     *
     * @return string One of: 'file', 'stream', 'syslog', 'null'
     */
    public function getType(): string
    {
        return $this->parse()['type'];
    }

    /**
     * Get log destination name
     *
     * For file/stream handlers: file path or stream URL
     * For syslog handler: facility number (or empty for default)
     *
     * @return string Log destination
     */
    public function getName(): string
    {
        return $this->parse()['name'];
    }

    /**
     * Get log identifier
     *
     * Identifier prepended to log messages
     *
     * @return string Log identifier
     */
    public function getIdent(): string
    {
        return $this->parse()['ident'];
    }

    /**
     * Get normalized priority level
     *
     * Returns the constant name (e.g., 'NOTICE', 'WARN', 'DEBUG')
     *
     * @return string Priority constant name
     */
    public function getPriority(): string
    {
        return $this->parse()['priority'];
    }

    /**
     * Get priority as integer constant value
     *
     * @return int Priority constant value (e.g., Horde_Log::NOTICE)
     */
    public function getPriorityValue(): int
    {
        return constant('Horde_Log::' . $this->getPriority());
    }

    /**
     * Get format type
     *
     * @return string One of: 'default', 'custom', 'xml'
     */
    public function getFormat(): string
    {
        return $this->parse()['format'];
    }

    /**
     * Get custom format template
     *
     * Only applicable when format type is 'custom'
     *
     * @return string|null Custom format template or null
     */
    public function getTemplate(): ?string
    {
        return $this->parse()['template'];
    }

    /**
     * Get append mode for file handlers
     *
     * @return bool True to append, false to overwrite
     */
    public function getAppend(): bool
    {
        return $this->parse()['append'];
    }

    /**
     * Get append mode as fopen() mode string
     *
     * @return string 'a+' for append, 'w+' for overwrite
     */
    public function getAppendMode(): string
    {
        return $this->getAppend() ? 'a+' : 'w+';
    }

    /**
     * Get syslog facility
     *
     * @return int|null Syslog facility number or null if not specified
     */
    public function getFacility(): ?int
    {
        return $this->parse()['facility'];
    }

    /**
     * Get raw parsed configuration array
     *
     * Provided for backward compatibility and debugging.
     * Prefer using specific getter methods.
     *
     * @return array Parsed configuration
     */
    public function toArray(): array
    {
        return $this->parse();
    }
}
