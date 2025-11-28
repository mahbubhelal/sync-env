<?php

declare(strict_types=1);

namespace Mahbub\SyncEnv\Tests;

use Mahbub\SyncEnv\SyncEnvServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    public function getEnvironmentSetUp($app): void
    {
        $app->useEnvironmentPath(__DIR__ . '/fixtures');
        // $app->useBasePath(__DIR__ . '/fixtures');
        $app->setBasePath(__DIR__ . '/fixtures');
    }

    #[\Override]
    protected function getPackageProviders($app)
    {
        return [
            SyncEnvServiceProvider::class,
        ];
    }
}
