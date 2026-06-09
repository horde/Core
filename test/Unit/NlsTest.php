<?php

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Tests the Nls API contract used by Core (FlagImage, VarRenderer, Datejs).
 *
 * - Horde_Nls::getLangInfo(D_FMT) for Datejs translateFormat
 * - Horde_Nls::getLocaleInfo() for VarRenderer number formatting
 * - Horde_Nls::getCountryByHost() for FlagImage (returns array or false)
 */
#[CoversNothing]
class Horde_Core_Unit_NlsTest extends PHPUnit\Framework\TestCase
{
    /**
     * Test Datejs::translateFormat() which uses getLangInfo(D_FMT).
     */
    public function testDatejsTranslateFormat()
    {
        $result = Horde_Core_Script_Package_Datejs::translateFormat('%d/%m/%Y');

        $this->assertIsString($result);
        $this->assertSame('dd/MM/yyyy', $result);
    }

    /**
     * Test that getLangInfo(D_FMT) returns a format string with %.
     */
    public function testGetLangInfoDfmt()
    {
        $format = Horde_Nls::getLangInfo(D_FMT);

        $this->assertIsString($format);
        $this->assertNotEmpty($format);
        $this->assertStringContainsString('%', $format);
    }

    /**
     * Test that getLocaleInfo returns array with mon_decimal_point.
     * Used by VarRenderer_Html for number display.
     */
    public function testGetLocaleInfoReturnsArray()
    {
        $linfo = Horde_Nls::getLocaleInfo();

        $this->assertIsArray($linfo);
        $this->assertArrayHasKey('mon_decimal_point', $linfo);
    }

    /**
     * Test that getCountryByHost with a .de hostname returns country info.
     * FlagImage uses this to look up flags by TLD.
     */
    public function testGetCountryByHostReturnsByTld()
    {
        Horde_Nls::$dnsResolver = null;

        $result = Horde_Nls::getCountryByHost('www.example.de');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('code', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertSame('de', $result['code']);
    }

    /**
     * Test that getCountryByHost with a generic TLD returns false.
     */
    public function testGetCountryByHostGenericTldReturnsFalse()
    {
        Horde_Nls::$dnsResolver = null;

        // The path is intentionally invalid — Geoip emits an fopen()
        // warning for missing databases, which we silence here so the
        // test asserts only the documented return value.
        $result = @Horde_Nls::getCountryByHost('www.example.com', '/nonexistent/path');

        $this->assertFalse($result);
    }
}
