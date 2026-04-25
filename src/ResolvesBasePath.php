<?php

declare(strict_types=1);

namespace Mahbub\SyncEnv;

use Illuminate\Support\Facades\App;

trait ResolvesBasePath
{
    protected function resolveBasePath(): void
    {
        if (App::environment('workbench')) {
            App::setBasePath((string) getcwd()); // @codeCoverageIgnore
        }
    }
}
