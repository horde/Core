<?php

$candidates = [
    dirname(__FILE__, 2) . '/vendor/autoload.php',
    dirname(__FILE__, 4) . '/autoload.php',
    $_SERVER['HOME'] . '/running/horde/vendor/autoload.php',
];
// Cover root case and library case
foreach ($candidates as $candidate) {
    if (file_exists($candidate)) {
        require_once $candidate;
        break;
    }
}
Horde_Test_Bootstrap::bootstrap(dirname(__FILE__));
