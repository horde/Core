<?php
if (!class_exists(\Horde_Test_Bootstrap::class)) {
    require_once 'Horde/Test/Bootstrap.php';
}
Horde_Test_Bootstrap::bootstrap(dirname(__FILE__));
