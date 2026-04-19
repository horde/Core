<?php

declare(strict_types=1);

/**
 * Tests for LoggerConfig
 *
 * @category   Horde
 * @license    http://www.horde.org/licenses/lgpl21 LGPL
 * @package    Core
 * @subpackage UnitTests
 */

namespace Horde\Core\Test\Unit\Config;

use Horde\Core\Config\LoggerConfig;
use Horde\Core\Config\State;
use Horde_Log;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoggerConfig::class)]
class LoggerConfigTest extends TestCase
{
    public function testConstructor(): void
    {
        $state = new State(['log' => []]);
        $config = new LoggerConfig($state);
        $this->assertInstanceOf(LoggerConfig::class, $config);
    }

    public function testDefaultValues(): void
    {
        $state = new State(['log' => []]);
        $config = new LoggerConfig($state);

        $this->assertTrue($config->isEnabled());
        $this->assertSame('null', $config->getType());
        $this->assertSame('', $config->getName());
        $this->assertSame('', $config->getIdent());
        $this->assertSame('NOTICE', $config->getPriority());
        $this->assertSame('default', $config->getFormat());
        $this->assertNull($config->getTemplate());
        $this->assertTrue($config->getAppend());
        $this->assertNull($config->getFacility());
    }

    public function testEnabledFalse(): void
    {
        $state = new State(['log' => ['enabled' => false]]);
        $config = new LoggerConfig($state);

        $this->assertFalse($config->isEnabled());
    }

    public function testFileType(): void
    {
        $state = new State([
            'log' => [
                'type' => 'file',
                'name' => '/var/log/horde.log',
                'ident' => 'HORDE',
            ],
        ]);
        $config = new LoggerConfig($state);

        $this->assertSame('file', $config->getType());
        $this->assertSame('/var/log/horde.log', $config->getName());
        $this->assertSame('HORDE', $config->getIdent());
    }

    public function testStreamType(): void
    {
        $state = new State([
            'log' => [
                'type' => 'stream',
                'name' => 'php://stderr',
            ],
        ]);
        $config = new LoggerConfig($state);

        $this->assertSame('stream', $config->getType());
        $this->assertSame('php://stderr', $config->getName());
    }

    public function testSyslogType(): void
    {
        $state = new State([
            'log' => [
                'type' => 'syslog',
                'name' => '24', // LOG_DAEMON facility
                'ident' => 'horde-app',
            ],
        ]);
        $config = new LoggerConfig($state);

        $this->assertSame('syslog', $config->getType());
        $this->assertSame('24', $config->getName());
        $this->assertSame('horde-app', $config->getIdent());
        $this->assertSame(24, $config->getFacility());
    }

    public function testSyslogTypeWithNonNumericName(): void
    {
        $state = new State([
            'log' => [
                'type' => 'syslog',
                'name' => 'daemon',
            ],
        ]);
        $config = new LoggerConfig($state);

        $this->assertNull($config->getFacility());
    }

    public function testPriorityNormalizationWarning(): void
    {
        // Bug #12109: WARNING should map to WARN
        $state = new State([
            'log' => ['priority' => 'WARNING'],
        ]);
        $config = new LoggerConfig($state);

        $this->assertSame('WARN', $config->getPriority());
    }

    public function testPriorityNormalizationValid(): void
    {
        $priorities = ['EMERG', 'ALERT', 'CRIT', 'ERR', 'WARN', 'NOTICE', 'INFO', 'DEBUG'];

        foreach ($priorities as $priority) {
            $state = new State(['log' => ['priority' => $priority]]);
            $config = new LoggerConfig($state);

            $this->assertSame($priority, $config->getPriority(), "Priority $priority should normalize to itself");
        }
    }

    public function testPriorityNormalizationInvalid(): void
    {
        $state = new State([
            'log' => ['priority' => 'INVALID_PRIORITY'],
        ]);
        $config = new LoggerConfig($state);

        // Invalid priority should fall back to NOTICE
        $this->assertSame('NOTICE', $config->getPriority());
    }

    public function testGetPriorityValue(): void
    {
        $state = new State([
            'log' => ['priority' => 'WARN'],
        ]);
        $config = new LoggerConfig($state);

        $this->assertSame(Horde_Log::WARN, $config->getPriorityValue());
    }

    public function testFormatDefault(): void
    {
        $state = new State(['log' => []]);
        $config = new LoggerConfig($state);

        $this->assertSame('default', $config->getFormat());
        $this->assertNull($config->getTemplate());
    }

    public function testFormatCustom(): void
    {
        $state = new State([
            'log' => [
                'params' => [
                    'format' => 'custom',
                    'template' => '%timestamp% [%levelName%] %message%',
                ],
            ],
        ]);
        $config = new LoggerConfig($state);

        $this->assertSame('custom', $config->getFormat());
        $this->assertSame('%timestamp% [%levelName%] %message%', $config->getTemplate());
    }

    public function testFormatXml(): void
    {
        $state = new State([
            'log' => [
                'params' => ['format' => 'xml'],
            ],
        ]);
        $config = new LoggerConfig($state);

        $this->assertSame('xml', $config->getFormat());
    }

    public function testAppendTrue(): void
    {
        $state = new State([
            'log' => [
                'params' => ['append' => true],
            ],
        ]);
        $config = new LoggerConfig($state);

        $this->assertTrue($config->getAppend());
        $this->assertSame('a+', $config->getAppendMode());
    }

    public function testAppendFalse(): void
    {
        $state = new State([
            'log' => [
                'params' => ['append' => false],
            ],
        ]);
        $config = new LoggerConfig($state);

        $this->assertFalse($config->getAppend());
        $this->assertSame('w+', $config->getAppendMode());
    }

    public function testToArray(): void
    {
        $state = new State([
            'log' => [
                'enabled' => true,
                'type' => 'file',
                'name' => '/var/log/test.log',
                'ident' => 'TEST',
                'priority' => 'INFO',
                'params' => [
                    'format' => 'custom',
                    'template' => '%message%',
                    'append' => false,
                ],
            ],
        ]);
        $config = new LoggerConfig($state);

        $array = $config->toArray();

        $this->assertIsArray($array);
        $this->assertTrue($array['enabled']);
        $this->assertSame('file', $array['type']);
        $this->assertSame('/var/log/test.log', $array['name']);
        $this->assertSame('TEST', $array['ident']);
        $this->assertSame('INFO', $array['priority']);
        $this->assertSame('custom', $array['format']);
        $this->assertSame('%message%', $array['template']);
        $this->assertFalse($array['append']);
    }

    public function testConfigurationCaching(): void
    {
        $state = new State([
            'log' => ['priority' => 'DEBUG'],
        ]);
        $config = new LoggerConfig($state);

        // First call parses
        $priority1 = $config->getPriority();

        // Second call should use cached value
        $priority2 = $config->getPriority();

        $this->assertSame($priority1, $priority2);
        $this->assertSame('DEBUG', $priority2);
    }

    public function testCompleteFileConfiguration(): void
    {
        $state = new State([
            'log' => [
                'enabled' => true,
                'type' => 'file',
                'name' => '/var/log/horde/horde.log',
                'ident' => 'HORDE',
                'priority' => 'NOTICE',
                'params' => [
                    'format' => 'default',
                    'append' => true,
                ],
            ],
        ]);
        $config = new LoggerConfig($state);

        $this->assertTrue($config->isEnabled());
        $this->assertSame('file', $config->getType());
        $this->assertSame('/var/log/horde/horde.log', $config->getName());
        $this->assertSame('HORDE', $config->getIdent());
        $this->assertSame('NOTICE', $config->getPriority());
        $this->assertSame(Horde_Log::NOTICE, $config->getPriorityValue());
        $this->assertSame('default', $config->getFormat());
        $this->assertTrue($config->getAppend());
        $this->assertSame('a+', $config->getAppendMode());
    }

    public function testCompleteSyslogConfiguration(): void
    {
        $state = new State([
            'log' => [
                'enabled' => true,
                'type' => 'syslog',
                'name' => '24',
                'ident' => 'horde-imp',
                'priority' => 'WARNING', // Bug #12109 test
            ],
        ]);
        $config = new LoggerConfig($state);

        $this->assertTrue($config->isEnabled());
        $this->assertSame('syslog', $config->getType());
        $this->assertSame('24', $config->getName());
        $this->assertSame(24, $config->getFacility());
        $this->assertSame('horde-imp', $config->getIdent());
        $this->assertSame('WARN', $config->getPriority()); // Normalized
        $this->assertSame(Horde_Log::WARN, $config->getPriorityValue());
    }

    public function testMissingLogConfiguration(): void
    {
        // State with no log key at all
        $state = new State([]);
        $config = new LoggerConfig($state);

        // Should use all defaults
        $this->assertTrue($config->isEnabled());
        $this->assertSame('null', $config->getType());
        $this->assertSame('NOTICE', $config->getPriority());
    }

    public function testNullHandlerConfiguration(): void
    {
        $state = new State([
            'log' => [
                'enabled' => false,
                'type' => 'null',
            ],
        ]);
        $config = new LoggerConfig($state);

        $this->assertFalse($config->isEnabled());
        $this->assertSame('null', $config->getType());
    }
}
