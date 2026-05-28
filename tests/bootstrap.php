<?php

/**
 * PHPUnit bootstrap
 *
 * Boots the LibreNMS Laravel application (the plugin lives composer-symlinked
 * inside vendor/) and registers a PSR-4 autoloader for this plugin's tests.
 *
 * Run from the plugin root with:
 *   DBTEST=1 /var/www/html/librenms/vendor/bin/phpunit
 *
 * DBTEST=1 is required by LibreNMS's DBTestCase; without it every test is
 * marked skipped.
 */

$librenms = getenv('LIBRENMS_FOLDER') ?: '/var/www/html/librenms';

chdir($librenms);
require_once $librenms . '/vendor/autoload.php';

// Boot the Laravel app for the duration of the test run.
$app = require_once $librenms . '/bootstrap/app.php';
$app->loadEnvironmentFrom('.env');
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Register PSR-4 for the plugin's tests namespace
spl_autoload_register(function ($class) {
    $prefix = 'DotMike\\NmsCustomFields\\Tests\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
