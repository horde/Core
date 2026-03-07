<?php
/**
 * Minimal bootstrap for running ResponsiveAssets and ResponsiveTemplateView tests
 */

// Autoload Horde_Registry mock (we'll mock it in tests)
class Horde_Registry {
    public function get($key, $app) { return null; }
    public function getApp() { return 'horde'; }
}

class Horde_Prefs {
    public function getValue($key) { return null; }
}

// Load our classes
require_once __DIR__ . '/../src/Assets/ResponsiveAssetsFilesystem.php';
require_once __DIR__ . '/../src/Assets/ResponsiveAssetsFilesystemImpl.php';
require_once __DIR__ . '/../src/Assets/ResponsiveAssets.php';
require_once __DIR__ . '/../src/View/ResponsiveTemplateView.php';
