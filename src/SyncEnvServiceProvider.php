<?php

declare(strict_types=1);

namespace Mahbub\SyncEnv;

use Illuminate\Support\ServiceProvider;
use Mahbub\SyncEnv\Commands\SyncEnvCommand;

final class SyncEnvServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void {}

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncEnvCommand::class,
            ]);
        }
    }
}
