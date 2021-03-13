<?php
if (!class_exists(Horde_Test_AllTests::class)) {
    require_once 'Horde/Test/AllTests.php';
}
Horde_Test_AllTests::init(__FILE__)->run();
