<?php
namespace Horde\Core\Mock;
/**
 * Mock the IMP_Mailbox class
 *
 * Needs to return the value property
 */
class MockIMPMailbox
{
    protected $_name;

    public function __construct($mbox)
    {
        $this->_name = $mbox;
    }

    public function __get($property)
    {
        switch ($property) {
        case 'value':
            return $this->_name;
        }
    }
}