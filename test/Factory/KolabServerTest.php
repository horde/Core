<?php
/**
 * Test the Kolab_Server factory.
 *
 * PHP version 5
 *
 * @category Horde
 * @package  Core
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Factory;

use PHPUnit\Framework\TestCase;
use Horde_Core_Factory_KolabServer;
use Horde_Injector;
use Horde_Exception;

/**
 * Test the Kolab_Server factory.
 *
 * Copyright 2009-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class KolabServerTest extends TestCase
{
    public function setUp(): void
    {
        $this->markTestIncomplete('Needs some love');
        $GLOBALS['conf']['kolab']['server']['basedn'] = 'test';
        $this->factory   = $this->getMock(
            'Horde_Core_Factory_KolabServer',
            [],
            [],
            '',
            false,
            false
        );
        $this->objects   = $this->getMock(
            'Horde_Kolab_Server_Objects_Interface'
        );
        $this->structure = $this->getMock(
            'Horde_Kolab_Server_Structure_Interface'
        );
        $this->search    = $this->getMock(
            'Horde_Kolab_Server_Search_Interface'
        );
        $this->schema    = $this->getMock(
            'Horde_Kolab_Server_Schema_Interface'
        );
    }

    private function _getFactory()
    {
        return new Horde_Core_Factory_KolabServer(
            new Horde_Injector(new Horde_Injector_TopLevel())
        );
    }

    public function testMethodGetserverReturnsServer()
    {
        $factory = $this->_getFactory();
        $this->assertInstanceOf('Horde_Kolab_Server_Interface', $factory->getServer());
    }

    public function testMethodGetconfigurationReturnsArrayConfiguration()
    {
        $factory = $this->_getFactory();
        $this->assertEquals(
            ['basedn' => 'test'],
            $factory->getConfiguration()
        );
    }

    public function testMethodGetconnectionHasResultConnection()
    {
        $factory = $this->_getFactory();
        $this->assertInstanceOf(
            'Horde_Kolab_Server_Connection_Interface',
            $factory->getConnection()
        );
    }

    public function testMethodConstructHasResultMockConnectionIfConfiguredThatWay()
    {
        $GLOBALS['conf']['kolab']['server']['mock'] = true;
        $factory = $this->_getFactory();
        $this->assertInstanceOf('Horde_Kolab_Server_Connection_Mock', $factory->getConnection());
    }

    public function testMethodGetconnectionHasResultMockConnectionWithDataIfConfiguredThatWay()
    {
        $GLOBALS['conf']['kolab']['server']['mock'] = true;
        $GLOBALS['conf']['kolab']['server']['data'] = [];
        $factory = $this->_getFactory();
        $this->assertInstanceOf('Horde_Kolab_Server_Connection_Mock', $factory->getConnection());
    }

    public function testMethodConstructHasResultSimpleConnectionByDefault()
    {
        $factory = $this->_getFactory();
        $this->assertInstanceOf('Horde_Kolab_Server_Connection_SimpleLdap', $factory->getConnection());
    }

    public function testMethodConstructHasResultSplittedLdapIfConfiguredThatWay()
    {
        $GLOBALS['conf']['kolab']['server']['host_master'] = 'master';
        $factory = $this->_getFactory();
        $this->assertInstanceOf('Horde_Kolab_Server_Connection_SplittedLdap', $factory->getConnection());
    }

    public function testMethodGetserverHasResultServerldapstandard()
    {
        $factory = $this->_getFactory();
        $this->assertInstanceOf(
            'Horde_Kolab_Server_Ldap_Standard',
            $factory->getServer()
        );
    }

    public function testMethodGetserverThrowsExceptionIfTheBaseDnIsMissingInTheConfiguration()
    {
        unset($GLOBALS['conf']);
        $factory = $this->_getFactory();
        try {
            $factory->getServer();
            $this->fail('No exception!');
        } catch (Horde_Exception $e) {
            $this->assertEquals(
                'The parameter \'basedn\' is missing in the Kolab server configuration!',
                $e->getMessage()
            );
        }
    }

    public function testMethodGetconnectionThrowsExceptionIfTheBaseDnIsMissingInTheConfiguration()
    {
        unset($GLOBALS['conf']);
        $factory = $this->_getFactory();
        try {
            $factory->getConnection();
            $this->fail('No exception!');
        } catch (Horde_Exception $e) {
            $this->assertEquals(
                'The parameter \'basedn\' is missing in the Kolab server configuration!',
                $e->getMessage()
            );
        }
    }

    public function testMethodGetserverHasResultServerldapFilteredIfAFilterWasSet()
    {
        $GLOBALS['conf']['kolab']['server']['filter'] = 'filter';
        $factory = $this->_getFactory();
        $this->assertInstanceOf(
            'Horde_Kolab_Server_Ldap_Filtered',
            $factory->getServer()
        );
    }

    public function testMethodGetobjectsHasResultObjects()
    {
        $factory = $this->_getFactory();
        $this->assertInstanceOf(
            'Horde_Kolab_Server_Objects_Interface',
            $factory->getObjects()
        );
    }

    public function testMethodGetstructureHasResultStructureKolab()
    {
        $factory = $this->_getFactory();
        $this->assertInstanceOf(
            'Horde_Kolab_Server_Structure_Kolab',
            $factory->getStructure()
        );
    }

    public function testMethodGetstructureHasResultStructureLdapIfConfiguredThatWay()
    {
        $GLOBALS['conf']['kolab']['server']['structure'] = [
            'driver' => 'Horde_Kolab_Server_Structure_Ldap',
        ];
        $factory = $this->_getFactory();
        $this->assertNotType(
            'Horde_Kolab_Server_Structure_Kolab',
            $factory->getStructure()
        );
    }

    public function testMethodGetsearchHasResultSearch()
    {
        $factory = $this->_getFactory();
        $this->assertInstanceOf(
            'Horde_Kolab_Server_Search_Interface',
            $factory->getSearch()
        );
    }

    public function testMethodGetschemaHasResultSchema()
    {
        $factory = $this->_getFactory();
        $this->assertInstanceOf(
            'Horde_Kolab_Server_Schema_Interface',
            $factory->getSchema()
        );
    }

    public function testMethodGetcompositeReturnsComposite()
    {
        $factory = $this->_getFactory();
        $this->assertInstanceOf(
            'Horde_Kolab_Server_Composite',
            $factory->getComposite()
        );
    }

    public function testMethodGetserverHasResultCountedServerIfCountingWasActivatedInTheConfiguration()
    {
        $GLOBALS['conf']['kolab']['server']['count'] = true;
        $factory = $this->_getFactory();
        $this->assertInstanceOf(
            'Horde_Kolab_Server_Decorator_Count',
            $factory->getServer()
        );
    }

    public function testMethodGetserverHasResultLoggedServerIfLoggingWasActivatedInTheConfiguration()
    {
        $GLOBALS['conf']['kolab']['server']['log'] = true;
        $factory = $this->_getFactory();
        $this->assertInstanceOf(
            'Horde_Kolab_Server_Decorator_Log',
            $factory->getServer()
        );
    }

    public function testMethodGetserverHasResultMappedServerIfAMappedWasProvidedInTheConfiguration()
    {
        $GLOBALS['conf']['kolab']['server']['map'] = [];
        $factory = $this->_getFactory();
        $this->assertInstanceOf(
            'Horde_Kolab_Server_Decorator_Map',
            $factory->getServer()
        );
    }

    public function testMethodGetserverHasResultCleanerServerIfACleanedWasProvidedInTheConfiguration()
    {
        $GLOBALS['conf']['kolab']['server']['cleanup'] = true;
        $factory = $this->_getFactory();
        $this->assertInstanceOf(
            'Horde_Kolab_Server_Decorator_Clean',
            $factory->getServer()
        );
    }

    public function testMethodGetconfigurationHasResultArray()
    {
        $factory = $this->_getFactory();
        $this->assertInternalType(
            'array',
            $factory->getConfiguration()
        );
    }

    public function testMethodGetconfigurationHasResultRewrittenServerParameter()
    {
        $GLOBALS['conf']['kolab']['server']['server'] = 'a';
        $factory = $this->_getFactory();
        $this->assertEquals(
            [
                'basedn' => 'test',
                'host' => 'a',
            ],
            $factory->getConfiguration()
        );
    }

    public function testMethodGetconfigurationHasResultRewrittenPhpdnParameter()
    {
        $GLOBALS['conf']['kolab']['server']['phpdn'] = 'a';
        $factory = $this->_getFactory();
        $this->assertEquals(
            [
                'basedn' => 'test',
                'binddn' => 'a',
            ],
            $factory->getConfiguration()
        );
    }

    public function testMethodGetconfigurationHasResultRewrittenPhppwParameter()
    {
        $GLOBALS['conf']['kolab']['server']['phppw'] = 'a';
        $factory = $this->_getFactory();
        $this->assertEquals(
            [
                'basedn' => 'test',
                'bindpw' => 'a',
            ],
            $factory->getConfiguration()
        );
    }

    public function testMethodGetconfigurationRewritesOldConfiguration()
    {
        unset($GLOBALS['conf']['kolab']['server']);
        $GLOBALS['conf']['kolab']['ldap']['basedn'] = 'test';
        $GLOBALS['conf']['kolab']['ldap']['phppw'] = 'a';
        $factory = $this->_getFactory();
        $this->assertEquals(
            [
                'basedn' => 'test',
                'bindpw' => 'a',
            ],
            $factory->getConfiguration()
        );
    }
}
