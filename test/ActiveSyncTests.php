<?php
/**
 * Copyright 2010-2017 Horde LLC (http://www.horde.org/)
 *
 * @author  Michael J Rubinsky <mrubinsk@horde.org>
 * @category   Horde
 * @package    Core
 * @subpackage UnitTests
 */

namespace Horde\Core\Test;

use Horde\Test\TestCase;
use Horde\Core\Mock\MockConnector;
use Horde\Core\Mock\MockIMPMailbox;

use Horde_Date;

 /**
 * Unit tests for ActiveSync functionality in Core.
 *
 * @author  Michael J Rubinsky <mrubinsk@horde.org>
 * @category   Horde
 * @package    Core
 * @subpackage UnitTests
 */
class ActiveSyncTests extends TestCase
{
    protected $_auth;
    protected $_state;
    protected $_mailboxes;
    protected $_special;

    public function setUp()
    {
        $this->_auth = $this->getMockSkipConstructor('Horde_Auth_Auto');
        $this->_state = $this->getMockSkipConstructor('Horde_ActiveSync_State_Sql');
    }

    public function _setupDeepStructure()
    {
        $this->_mailboxes = [
            'INBOX' => [
                'a' => 40,
                'd' => '.',
                'label' => 'Inbox',
                'level' => 0,
                'ob' => $this->getMockSkipConstructor('Horde_Imap_Client_Mailbox'), ],

            'INBOX.Drafts' => [
                'a' => 8,
                'd' => '.',
                'label' => 'Drafts',
                'level' => 0,
                'ob' =>$this->getMockSkipConstructor('Horde_Imap_Client_Mailbox'), ],

            'INBOX.Saved Emails' => [
                'a' => 8,
                'd' => '.',
                'label' => 'Saved Emails',
                'level' => 0,
                'ob' => $this->getMockSkipConstructor('Horde_Imap_Client_Mailbox'), ],

            'INBOX.Saved Emails.ACS' => [
                'a' => 8,
                'd' => '.',
                'label' => 'Saved Emails.ACS',
                'level' => 1,
                'ob' => $this->getMockSkipConstructor('Horde_Imap_Client_Mailbox'), ],

            'INBOX.Saved Emails.Amazon' => [
                'a' => 8,
                'd' => '.',
                'label' => 'Saved Emails.Amazon',
                'level' => 1,
                'ob' => $this->getMockSkipConstructor('Horde_Imap_Client_Mailbox'), ],

            'INBOX.Saved Emails.Computer Stuff' => [
                'a' => 8,
                'd' => '.',
                'label' => 'Saved Emails.Computer Stuff',
                'level' => 1,
                'ob' => $this->getMockSkipConstructor('Horde_Imap_Client_Mailbox'), ],

            'INBOX.Saved Emails.Computer Stuff.Mailing Lists' => [
                'a' => 8,
                'd' => '.',
                'label' => 'Saved Emails.Computer Stuff.Mailing Lists',
                'level' => 2,
                'ob' => $this->getMockSkipConstructor('Horde_Imap_Client_Mailbox'), ],

            'INBOX.Saved Emails.Computer Stuff.Mailing Lists.Horde' => [
                'a' => 40,
                'd' => '.',
                'label' => 'Saved Emails.Computer Stuff.Mailing Lists.Horde',
                'level' => 3,
                'ob' => $this->getMockSkipConstructor('Horde_Imap_Client_Mailbox'), ],

            'INBOX.Saved Emails.Computer Stuff.Mailing Lists.Horde.Archived Horde' => [
                'a' => 8,
                'd' => '.',
                'label' => 'Saved Emails.Computer Stuff.Mailing Lists.Horde.Archived Horde',
                'level' => 4,
                'ob' => $this->getMockSkipConstructor('Horde_Imap_Client_Mailbox'), ],

            'INBOX.Saved Emails.Computer Stuff.Mailing Lists.Horde.BugsHordeOrg' => [
                'a' => 8,
                'd' => '.',
                'label' => 'Saved Emails.Computer Stuff.Mailing Lists.Horde.BugsHordeOrg',
                'level' => 4,
                'ob' => $this->getMockSkipConstructor('Horde_Imap_Client_Mailbox'), ],

            'INBOX.Sent' => [
                'a' => 8,
                'd' => '.',
                'label' => 'Sent',
                'level' => 0,
                'ob' =>$this->getMockSkipConstructor('Horde_Imap_Client_Mailbox'), ],

            'INBOX.Spam' => [
                'a' => 8,
                'd' => '.',
                'label' => 'Spam',
                'level' => 0,
                'ob' => $this->getMockSkipConstructor('Horde_Imap_Client_Mailbox'), ],

            'INBOX.Trash' => [
                'a' => 8,
                'd' => '.',
                'label' => 'Trash',
                'level' => 0,
                'ob' => $this->getMockSkipConstructor('Horde_Imap_Client_Mailbox'), ],

            'user.benjamin' => [
                'a' => 8,
                'd' => '.',
                'label' => 'user.benjamin',
                'level' => 1,
                'ob' => $this->getMockSkipConstructor('Horde_Imap_Client_Mailbox'), ],

            'user.benjamin.Drafts' => [
                'a' => 8,
                'd' => '.',
                'label' => 'user.benjamin.Drafts',
                'level' => 2,
                'ob' => $this->getMockSkipConstructor('Horde_Imap_Client_Mailbox'), ],

            'user.benjamin.Saved Emails' => [
                'a' => 8,
                'd' => '.',
                'label' =>'user.benjamin.Saved Emails',
                'level' => 2,
                'ob' => $this->getMockSkipConstructor('Horde_Imap_Client_Mailbox'), ],

            'user.benjamin.Sent' => [
                'a' => 8,
                'd' => '.',
                'label' => 'user.benjamin.Sent',
                'level' => 2,
                'ob' => $this->getMockSkipConstructor('Horde_Imap_Client_Mailbox'), ],

            'user.benjamin.Spam' => [
                'a' => 8,
                'd' => '.',
                'label' => 'user.benjamin.Spam',
                'level' => 2,
                'ob' => $this->getMockSkipConstructor('Horde_Imap_Client_Mailbox'), ],

            'user.benjamin.Trash' => [
                'a' => 8,
                'd' => '.',
                'label' => 'user.benjamin.Trash',
                'level' => 2,
                'ob' => $this->getMockSkipConstructor('Horde_Imap_Client_Mailbox'), ],

            'user.chiarina' => [
                'a' => 8,
                'd' => '.',
                'label' => 'user.chiarina',
                'level' => 1,
                'ob' => $this->getMockSkipConstructor('Horde_Imap_Client_Mailbox'), ],

            'user.chiarina.Drafts' => [
                'a' => 8,
                'd' => '.',
                'label' => 'user.chiarina.Drafts',
                'level' => 2,
                'ob' => $this->getMockSkipConstructor('Horde_Imap_Client_Mailbox'), ],

            'user.chiarina.Saved Emails' => [
                'a' => 8,
                'd' => '.',
                'label' => 'user.chiarina.Saved Emails',
                'level' => 2,
                'ob' => $this->getMockSkipConstructor('Horde_Imap_Client_Mailbox'), ],

            'user.chiarina.Sent' => [
                'a' => 8,
                'd' => '.',
                'label' => 'user.chiarina.Sent',
                'level' => 2,
                'ob' => $this->getMockSkipConstructor('Horde_Imap_Client_Mailbox'), ],

            'user.chiarina.Spam' => [
                'a' => 8,
                'd' => '.',
                'label' => 'user.chiarina.Spam',
                'level' => 2,
                'ob' => $this->getMockSkipConstructor('Horde_Imap_Client_Mailbox'), ],

            'user.chiarina.Trash' => [
                'a' => 8,
                'd' => '.',
                'label' => 'user.chiarina.Trash',
                'level' => 2,
                'ob' => $this->getMockSkipConstructor('Horde_Imap_Client_Mailbox'), ],
        ];
        $this->_special = [
            'composetemplates' => new MockIMPMailbox('Templates'),
            'drafts' => new MockIMPMailbox('INBOX.Drafts'),
            'sent' => new MockIMPMailbox('INBOX.Sent'),
            'spam' => new MockIMPMailbox('INBOX.Spam'),
            'trash' => new MockIMPMailbox('INBOX.Trash'),
        ];
    }

    public function _setUpMailTest()
    {
        $this->_mailboxes = [
            'INBOX' => [
                'a' => 40,
                'd' => '/',
                'label' =>'Inbox',
                'level' => 0,
                'ob' => $this->getMockSkipConstructor('Horde_Imap_Client_Mailbox'), ],
            'sent-mail' => [
                'a'=> 8,
                'd' => '/',
                'label' => 'Sent',
                'level' => 0,
                'ob' => $this->getMockSkipConstructor('Horde_Imap_Client_Mailbox'), ],
            'Draft' => [
                'a' => 8,
                'd' => '/',
                'label' => 'Drafts',
                'level' => 0,
                'ob' => $this->getMockSkipConstructor('Horde_Imap_Client_Mailbox'), ],
            'spam_folder' => [
                'a' => 8,
                'd' => '/',
                'label' => 'Spam',
                'level' => 0,
                'ob' => $this->getMockSkipConstructor('Horde_Imap_Client_Mailbox'), ],
            'One' => [
                'a' => 12,
                'd' => '/',
                'label' => 'One',
                'level' => 0,
                'ob' => $this->getMockSkipConstructor('Horde_Imap_Client_Mailbox'), ],
            'One/Two' => [
                'a' => 12,
                'd' => '/',
                'label' => 'One/Two',
                'level' => 1,
                'ob' => $this->getMockSkipConstructor('Horde_Imap_Client_Mailbox'), ],
            'One/Two/Three' => [
                'a' => 8,
                'd' => '/',
                'label' => 'One/Two/Three',
                'level' => 2,
                'ob' => $this->getMockSkipConstructor('Horde_Imap_Client_Mailbox'), ],
        ];

        $this->_special = [
            'composetemplates' => new MockIMPMailbox('Templates'),
            'drafts' => new MockIMPMailbox('Draft'),
            'sent' => new MockIMPMailbox('sent-mail'),
            'spam' => new MockIMPMailbox('Spam'),
            'trash' => new MockIMPMailbox('Trash'),
            'userhook' => [],
        ];
    }

    public function testGetFolderWithDeepFolderStructureAndPeriodDelimiter()
    {
        $this->_setupDeepStructure();
        $adapter = $this->getMockSkipConstructor('Horde_ActiveSync_Imap_Adapter');
        $adapter->expects($this->once())->method('getMailboxes')->will($this->returnValue($this->_mailboxes));
        $adapter->expects($this->any())->method('getSpecialMailboxes')->will($this->returnValue($this->_special));
        $driver = new Horde_Core_ActiveSync_Driver([
            'state' => $this->_state,
            'connector' => new MockConnector(),
            'auth' => $this->_auth,
            'imap' => $adapter, ]);
        $folders = $driver->getFolders();

        // Test the EAS Type of each special folder
        foreach ($folders as $f) {
            // Save some nested folders for testing later.)
            if ($f->_serverid == 'INBOX.Saved Emails') {
                $one = $f;
            } elseif ($f->_serverid == 'INBOX.Saved Emails.Computer Stuff') {
                $two = $f;
            } elseif ($f->_serverid == 'INBOX.Saved Emails.Computer Stuff.Mailing Lists') {
                $three = $f;
            } elseif ($f->_serverid == 'INBOX.Saved Emails.Computer Stuff.Mailing Lists.Horde') {
                $four = $f;
            } elseif ($f->_serverid == 'INBOX.Saved Emails.Computer Stuff.Mailing Lists.Horde.Archived Horde') {
                $five = $f;
            } elseif ($f->_serverid == 'INBOX.Saved Emails.Computer Stuff.Mailing Lists.Horde.BugsHordeOrg') {
                $five_sibling = $f;
            }

            if ($f->_serverid == 'user.benjamin') {
                $b_root = $f;
            } elseif ($f->_serverid == 'user.benjamin.Drafts') {
                $b_drafts = $f;
            }

            $have[$f->_serverid] = true;
            switch ($f->_serverid) {
            case 'INBOX.Drafts':
                $this->assertEquals(3, $f->type);
                break;
            case 'INBOX':
                $this->assertEquals(2, $f->type);
                break;
            case 'INBOX.Sent':
                $this->assertEquals(5, $f->type);
                break;
            case 'INBOX.Spam':
                $this->assertEquals(12, $f->type);
                break;
            }
        }

        $this->assertEquals($five_sibling->parentid, $four->serverid);
        $this->assertEquals($five->parentid, $four->serverid);
        $this->assertEquals($four->parentid, $three->serverid);
        $this->assertEquals($three->parentid, $two->serverid);
        $this->assertEquals($two->parentid, $one->serverid);
        $this->assertEquals($one->parentid, 0);

        $this->assertEquals($b_root->parentid, 0);
        $this->assertEquals($b_drafts->parentid, $b_root->serverid);
    }

    public function testGetFoldersWhenEmailSupportDisabled()
    {
        $driver = new Horde_Core_ActiveSync_Driver([
            'state' => $this->_state,
            'connector' => new MockConnector(),
            'auth' => $this->_auth,
            'imap' => false, ]);

        $folders = $driver->getFolders();
        $have = [
            'Trash' => false,
            'Sent' => false,
            'INBOX' => false,
        ];
        foreach ($folders as $f) {
            $have[$f->_serverid] = true;
            switch ($f->_serverid) {
            case 'INBOX':
                $this->assertEquals(2, $f->type);
                break;
            case 'Sent':
                $this->assertEquals(5, $f->type);
                break;
            case 'Trash':
                $this->assertEquals(4, $f->type);
                break;
            }
        }

        // Make sure we have them all.
        foreach (['INBOX', 'Trash', 'Sent'] as $test) {
            if (!$have[$test]) {
                $this->fail('Missing ' . $test);
            }
        }
    }

    public function testGetFoldersWithForwardSlashDelimiter()
    {
        $this->_setUpMailTest();
        $adapter = $this->getMockSkipConstructor('Horde_ActiveSync_Imap_Adapter');
        $adapter->expects($this->once())->method('getMailboxes')->will($this->returnValue($this->_mailboxes));
        $adapter->expects($this->any())->method('getSpecialMailboxes')->will($this->returnValue($this->_special));
        $driver = new Horde_Core_ActiveSync_Driver([
            'state' => $this->_state,
            'connector' => new MockConnector(),
            'auth' => $this->_auth,
            'imap' => $adapter, ]);
        $folders = $driver->getFolders();
        $have = [
            'Draft' => false,
            'INBOX' => false,
            'sent-mail' => false,
            'spam_folder' => false, ];

        // Test the EAS Type of each special folder
        foreach ($folders as $f) {
            // Save the nested folder uids for testing later.
            if ($f->_serverid == 'One') {
                $one = $f;
            } elseif ($f->_serverid == 'One/Two') {
                $two = $f;
            } elseif ($f->_serverid == 'One/Two/Three') {
                $three = $f;
            }

            $have[$f->_serverid] = true;
            switch ($f->_serverid) {
            case 'Draft':
                $this->assertEquals(3, $f->type);
                break;
            case 'INBOX':
                $this->assertEquals(2, $f->type);
                break;
            case 'sent-mail':
                $this->assertEquals(5, $f->type);
                break;
            case 'spam_folder':
                $this->assertEquals(12, $f->type);
                break;
            }
        }

        // Make sure we have them all.
        foreach (['Draft', 'INBOX', 'sent-mail', 'spam_folder', 'One', 'One/Two', 'One/Two/Three'] as $test) {
            if (!$have[$test]) {
                $this->fail('Missing ' . $test);
            }
        }

        // Make sure the hierarchy looks right.
        $this->assertEquals($two->serverid, $three->parentid);
        $this->assertEquals($one->serverid, $two->parentid);
        $this->assertEquals(0, $one->parentid);
    }

    public function testFbGeneration()
    {
        $connector = new MockConnector();
        $driver = new Horde_Core_ActiveSync_Driver([
            'state' => $this->_state,
            'connector' => $connector,
            'auth' => $this->_auth,
            'imap' => null, ]);

        $fixture = new stdClass();
        $fixture->s = '20130529';
        $fixture->e = '20130628';
        $fixture->b = [
            '1369850400' => 1369854000,  // 5/29 2:00PM - 3:00PM EDT
            '1370721600' => 1370728800,
        ];

        // Times requested by the client in a RESOLVERECIPIENTS request.
        $start = new Horde_Date('2013-05-29T03:00:00.000Z'); // 5/28 11:00PM EDT
        $end = new Horde_Date('2013-05-30T03:00:00.000Z'); // 5/29 11:00 PM EDT
        $fb = $driver->buildFbString($fixture, $start, $end);
        $expected = '440000000000000000000000000000220000000000000000';
        $this->assertEquals($expected, $fb);
    }
}
