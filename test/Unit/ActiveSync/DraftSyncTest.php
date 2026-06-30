<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Torben Dannhauer
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL
 * @package  Core
 * @subpackage UnitTests
 */

namespace Horde\Core\Test\Unit\ActiveSync;

use Horde\Core\Test\Support\MockSkipConstructorTrait;
use Horde_ActiveSync;
use Horde_ActiveSync_Imap_Adapter;
use Horde_ActiveSync_Imap_Message;
use Horde_ActiveSync_Message_Mail;
use Horde_Core_ActiveSync_Mail_Draft;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Horde_ActiveSync_Message_AirSyncBaseBody;

/**
 * Unit tests for EAS 16.0 draft sync helpers in Horde_Core_ActiveSync_Mail_Draft.
 *
 * @author   Torben Dannhauer
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL
 * @package  Core
 * @subpackage UnitTests
 */
#[CoversClass(Horde_Core_ActiveSync_Mail_Draft::class)]
class DraftSyncTest extends TestCase
{
    use MockSkipConstructorTrait;

    public function testToRfc822StreamIncludesPlainTextDraftBody(): void
    {
        if (!class_exists('Horde_ActiveSync_Message_Mail')) {
            $this->markTestSkipped('horde/activesync not available');
        }

        $draft = $this->createDraftWithoutIdentity();
        $draft->setDraftMessage($this->createClientDraftMessage(
            'PHASE-A-ONLY',
            'EAS16-DRAFT-EDIT'
        ));

        $stream = $draft->toRfc822Stream();
        $this->assertIsResource($stream);
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        $this->assertStringContainsString('PHASE-A-ONLY', $contents);
        $this->assertStringContainsString('EAS16-DRAFT-EDIT', $contents);
        $this->assertStringContainsString('test@dannhauer.de', $contents);
    }

    public function testAppendReplacesExistingDraftByDeletingOldUid(): void
    {
        if (!class_exists('Horde_ActiveSync_Message_Mail')) {
            $this->markTestSkipped('horde/activesync not available');
        }

        $folder = 'INBOX/Drafts';
        $oldUid = 55317;
        $newUid = 55318;

        $imapMessage = $this->getMockSkipConstructor(
            Horde_ActiveSync_Imap_Message::class,
            ['getStructure']
        );
        $imapMessage->method('getStructure')->willReturn([]);

        $imap = $this->getMockSkipConstructor(
            Horde_ActiveSync_Imap_Adapter::class,
            ['getImapMessage', 'appendMessage', 'deleteMessages']
        );
        $imap->expects($this->once())
            ->method('getImapMessage')
            ->with($folder, $oldUid)
            ->willReturn([$oldUid => $imapMessage]);
        $imap->expects($this->once())
            ->method('appendMessage')
            ->with(
                $folder,
                $this->isType('resource'),
                ['\draft', '\seen']
            )
            ->willReturn($newUid);
        $imap->expects($this->once())
            ->method('deleteMessages')
            ->with([$oldUid], $folder);

        $draft = $this->createDraftWithoutIdentity($imap);
        $draft->getExistingDraftMessage($folder, $oldUid);
        $draft->setDraftMessage($this->createClientDraftMessage(
            'PHASE-A-ONLY PHASE-B-EDITED',
            'EAS16-DRAFT-EDIT'
        ));

        $result = $draft->append($folder);

        $this->assertSame($newUid, $result['uid']);
    }

    public function testImportMimeDraftBodyUsesEnvelopeFromRfc822(): void
    {
        if (!class_exists('Horde_ActiveSync_Message_Mail')) {
            $this->markTestSkipped('horde/activesync not available');
        }

        $mime = implode("\r\n", [
            'From: Sender <sender@example.com>',
            'To: test@dannhauer.de',
            'Subject: EAS16-DRAFT-EDIT',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            '',
            'PHASE-A-ONLY',
        ]);

        $message = new Horde_ActiveSync_Message_Mail([
            'protocolversion' => Horde_ActiveSync::VERSION_SIXTEEN,
        ]);
        $body = new Horde_ActiveSync_Message_AirSyncBaseBody([
            'protocolversion' => Horde_ActiveSync::VERSION_SIXTEEN,
        ]);
        $body->type = Horde_ActiveSync::BODYPREF_TYPE_MIME;
        $body->data = $mime;
        $message->airsyncbasebody = $body;

        $draft = $this->createDraftWithoutIdentity();
        $draft->setDraftMessage($message);

        $stream = $draft->toRfc822Stream();
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        $this->assertStringContainsString('PHASE-A-ONLY', $contents);
        $this->assertStringContainsString('EAS16-DRAFT-EDIT', $contents);
        $this->assertStringContainsString('test@dannhauer.de', $contents);
    }

    private function createDraftWithoutIdentity(
        ?Horde_ActiveSync_Imap_Adapter $imap = null
    ): Horde_Core_ActiveSync_Mail_Draft {
        $imap ??= $this->getMockSkipConstructor(Horde_ActiveSync_Imap_Adapter::class);

        return new class ($imap, 'torben@dannhauer.info', Horde_ActiveSync::VERSION_SIXTEEN) extends Horde_Core_ActiveSync_Mail_Draft {
            protected function _getIdentityFromAddress()
            {
                return null;
            }

            protected function _getReplyToAddress()
            {
                return null;
            }
        };
    }

    private function createClientDraftMessage(
        string $body,
        string $subject
    ): Horde_ActiveSync_Message_Mail {
        $message = new Horde_ActiveSync_Message_Mail([
            'protocolversion' => Horde_ActiveSync::VERSION_SIXTEEN,
        ]);
        $message->to = 'test@dannhauer.de';
        $message->subject = $subject;

        $airsyncBody = new Horde_ActiveSync_Message_AirSyncBaseBody([
            'protocolversion' => Horde_ActiveSync::VERSION_SIXTEEN,
        ]);
        $airsyncBody->type = Horde_ActiveSync::BODYPREF_TYPE_PLAIN;
        $airsyncBody->data = $body;
        $message->airsyncbasebody = $airsyncBody;

        return $message;
    }
}
