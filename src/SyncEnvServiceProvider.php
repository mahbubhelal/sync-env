<?php

declare(strict_types=1);

namespace Mahbub\SyncEnv;

use Illuminate\Support\ServiceProvider;
use Mahbub\SyncEnv\Commands\SyncEnvCommand;

class SyncEnvServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register() {}

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncEnvCommand::class,
            ]);
        }
    }
}
