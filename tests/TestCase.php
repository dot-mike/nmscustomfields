<?php

namespace DotMike\NmsCustomFields\Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Base for plugin DB-aware tests. DatabaseTransactions rolls back every test
 * so they don't leak rows into the dev DB.
 *
 * Requires DBTEST=1 if you want the LibreNMS-style gate; we don't gate on it
 * here because the plugin's tests always need a real DB.
 */
abstract class TestCase extends BaseTestCase
{
    use DatabaseTransactions;

    public function createApplication()
    {
        $app = require getenv('LIBRENMS_FOLDER') . '/bootstrap/app.php';
        $app->loadEnvironmentFrom('.env');
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }
}
