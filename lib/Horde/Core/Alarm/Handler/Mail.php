<?php

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL
 * @package  Core
 */

use Horde\Injector\Injector;

/**
 * A Horde_Alarm handler that notifies of active alarms by e-mail, deferring
 * the Horde_Mail_Transport resolution until the first notify() call.
 *
 * Replaces the eager-mail-resolution pattern of {@see Horde_Alarm_Handler_Mail},
 * whose constructor validates `$params['mail'] instanceof Horde_Mail_Transport`
 * and forces every alarm-aware request (including the portal's
 * Horde_Notification_Handler_Decorator_Alarm path) to instantiate Horde_Mail.
 * That eager resolution dereferences the encrypted IMAP credentials slot,
 * which turns any latent decrypt failure (stale per-session key, evicted
 * cookie, post-regenerate mismatch) into a request-killing
 * Horde\Crypt\Blowfish\EncryptionException ("Invalid PKCS#7 padding byte").
 *
 * This subclass deliberately extends Horde_Alarm_Handler directly rather
 * than Horde_Alarm_Handler_Mail: extending the latter would inherit the
 * constructor we are trying to bypass. The body of notify() is otherwise
 * a direct port of the parent's implementation, with the transport
 * resolved through the injector on first invocation and cached for the
 * rest of the request.
 *
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL
 * @package  Core
 */
class Horde_Core_Alarm_Handler_Mail extends Horde_Alarm_Handler
{
    /**
     * Injector used to lazily resolve the Horde_Mail_Transport.
     *
     * @var Injector
     */
    protected $_injector;

    /**
     * An identity factory.
     *
     * @var Horde_Core_Factory_Identity
     */
    protected $_identity;

    /**
     * Cached transport, resolved on first notify().
     *
     * @var ?Horde_Mail_Transport
     */
    protected $_mail = null;

    /**
     * Constructor.
     *
     * @param array $params  Required parameters:
     *                       - injector: A Horde\Injector\Injector instance
     *                         the handler uses to lazily resolve the
     *                         'Horde_Mail' binding when notify() runs.
     *                       - identity: An identity factory that implements
     *                                   create().
     *
     * @throws Horde_Alarm_Exception
     */
    public function __construct(?array $params = null)
    {
        foreach (['injector', 'identity'] as $param) {
            if (!isset($params[$param])) {
                throw new Horde_Alarm_Exception('Parameter \'' . $param . '\' missing.');
            }
        }
        if (!($params['injector'] instanceof Injector)) {
            throw new Horde_Alarm_Exception('Parameter \'injector\' is not a Horde\\Injector\\Injector instance.');
        }
        if (!method_exists($params['identity'], 'create')) {
            throw new Horde_Alarm_Exception('Parameter \'identity\' does not have a method create().');
        }
        $this->_injector = $params['injector'];
        $this->_identity = $params['identity'];
    }

    /**
     * Notifies about an alarm by e-mail.
     *
     * Lazily resolves Horde_Mail on first call; subsequent calls reuse
     * the cached transport.
     *
     * @param array $alarm  An alarm hash.
     *
     * @throws Horde_Alarm_Exception
     */
    public function notify(array $alarm)
    {
        if (!empty($alarm['internal']['mail']['sent'])) {
            return;
        }

        if (empty($alarm['params']['mail']['email'])) {
            if (empty($alarm['user'])) {
                return;
            }
            $email = $this->_identity
                ->create($alarm['user'])
                ->getDefaultFromAddress(true);
        } else {
            $email = $alarm['params']['mail']['email'];
        }

        if ($this->_mail === null) {
            $this->_mail = $this->_injector->getInstance('Horde_Mail');
        }

        try {
            $mail = new Horde_Mime_Mail([
                'Subject' => $alarm['title'],
                'To' => $email,
                'From' => $email,
                'Auto-Submitted' => 'auto-generated',
                'X-Horde-Alarm' => $alarm['title']]);
            if (isset($alarm['params']['mail']['mimepart'])) {
                $mail->setBasePart($alarm['params']['mail']['mimepart']);
            } elseif (empty($alarm['params']['mail']['body'])) {
                $mail->setBody($alarm['text']);
            } else {
                $mail->setBody($alarm['params']['mail']['body']);
            }

            $mail->send($this->_mail);
        } catch (Horde_Mime_Exception $e) {
            throw new Horde_Alarm_Exception($e);
        }

        $alarm['internal']['mail']['sent'] = true;
        $this->alarm->internal($alarm['id'], $alarm['user'], $alarm['internal']);
    }

    /**
     * Resets the internal status of the handler, so that alarm notifications
     * are sent again.
     *
     * @param array $alarm  An alarm hash.
     */
    public function reset(array $alarm)
    {
        $alarm['internal']['mail']['sent'] = false;
        $this->alarm->internal($alarm['id'], $alarm['user'], $alarm['internal']);
    }

    /**
     * Returns a human readable description of the handler.
     *
     * @return string
     */
    public function getDescription()
    {
        return Horde_Alarm_Translation::t("Email");
    }

    /**
     * Returns a hash of user-configurable parameters for the handler.
     *
     * @return array
     */
    public function getParameters()
    {
        return [
            'email' => [
                'type' => 'text',
                'desc' => Horde_Alarm_Translation::t("Email address (optional)"),
                'required' => false]];
    }
}
