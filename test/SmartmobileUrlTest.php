<?php

namespace Horde\Core\Test;

use Horde\Test\TestCase;

use Horde_Core_Smartmobile_Url as SmartmobileUrl;
use Horde_Url;
use InvalidArgumentException;

/**
 * @author     Jan Schneider <jan@horde.org>
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @category   Horde
 * @package    Core
 * @subpackage UnitTests
 */
class SmartmobileUrlTest extends TestCase
{
    public function testInvalidParamter()
    {
        $this->expectException(InvalidArgumentException::class);
        new SmartmobileUrl('test');
    }

    public function testWithoutAnchor()
    {
        $url = new SmartmobileUrl(new Horde_Url('test'));
        $url->add(['foo' => 1, 'bar' => 2]);
        $this->assertEquals('test?foo=1&amp;bar=2', (string)$url);
    }

    public function testWithAnchor()
    {
        $url = new SmartmobileUrl(new Horde_Url('test'));
        $url->add(['foo' => 1, 'bar' => 2]);
        $url->setAnchor('anchor');
        $this->assertEquals('test#anchor?foo=1&amp;bar=2', (string)$url);
    }

    public function testBaseUrlWithParameters()
    {
        $base = new Horde_Url('test');
        $base->add('foo', 0);
        $url = new SmartmobileUrl($base);
        $url->add(['foo' => 1, 'bar' => 2]);
        $url->setAnchor('anchor');
        $this->assertEquals('test?foo=0#anchor?foo=1&amp;bar=2', (string)$url);
    }

    public function testBaseUrlWithParametersWithoutAnchor()
    {
        $base = new Horde_Url('test');
        $base->add('foo', 0);
        $url = new SmartmobileUrl($base);
        $url->add(['foo' => 1, 'bar' => 2]);
        $this->assertEquals('test?foo=1&amp;bar=2', (string)$url);
    }
}
