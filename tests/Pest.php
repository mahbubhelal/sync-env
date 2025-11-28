<?php

declare(strict_types=1);

use Mahbub\SyncEnv\SyncEnvServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

uses(Orchestra::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

function getPackageProviders($app): array
{
    return [
        SyncEnvServiceProvider::class,
    ];
}
