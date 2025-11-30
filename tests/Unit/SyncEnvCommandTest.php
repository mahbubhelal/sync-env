<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Mahbub\SyncEnv\Commands\SyncEnvCommand;

use function Pest\Laravel\artisan;
use function Pest\Laravel\travelTo;

beforeEach(function (): void {
    travelTo('2024-06-01 00:00:00');

    $testEnvFiles = File::glob(base_path('.env.*'));

    foreach ($testEnvFiles as $file) {
        if (File::exists($file)) {
            File::delete($file);
        }
    }
});

afterEach(function (): void {
    $testEnvFiles = File::glob(base_path('.env.*'));

    foreach ($testEnvFiles as $file) {
        if (File::exists($file)) {
            File::delete($file);
        }
    }
});

it('can sync from source to target env file', function (): void {
    $sourceContent = <<<'ENV_WRAP'
    APP_NAME=Tes tApp
    APP_ENV=local
    APP_DEBUG=true
    DB_CONNECTION=mysql

    # Custom
    CUSTOM_KEY="custom value"
    ANOTHER_KEY='another_value'
    ENV_WRAP;

    $targetContent = <<<'ENV_WRAP'
    APP_NAME=OldApp
    EXISTING_KEY=existing_value
    APP_ENV=production
    ANOTHER_KEY="another value"
    ENV_WRAP;

    File::put(base_path('.env.example'), $sourceContent);
    File::put(base_path('.env'), $targetContent);

    artisan('sync-env:example-to-env')
        ->assertExitCode(0);

    $result = File::get(base_path('.env'));

    expect($result)->toContain('APP_NAME=OldApp')
        ->toContain('APP_ENV=production')
        ->toContain('APP_DEBUG=true')
        ->toContain('DB_CONNECTION=mysql')
        ->toContain('# Custom')
        ->toContain('CUSTOM_KEY="custom value"')
        ->toContain('ANOTHER_KEY="another value"');

    $backupContent = File::get(base_path('.env.backup.' . now()->format('Y-m-d_H-i-s')));
    expect($backupContent)->toBe($targetContent);
});

it('fails when source file does not exist', function (): void {
    artisan('sync-env:example-to-env')
        ->expectsOutputToContain('File does not exist: ' . base_path('.env.example'))
        ->assertExitCode(1);
});

it('fails when invalid keys are present in source', function (): void {
    $sourceContent = <<<'ENV'
    APP NAME=TestApp
    ENV;

    File::put(base_path('.env.example'), $sourceContent);

    artisan('sync-env:example-to-env')
        ->expectsOutputToContain('Invalid key found in line 1: APP NAME')
        ->assertExitCode(1);
});

it('fails when duplicate keys are present in source', function (): void {
    $sourceContent = <<<'ENV'
    APP_NAME=TestApp
    APP_NAME=Laravel
    ENV;

    File::put(base_path('.env.example'), $sourceContent);

    artisan('sync-env:example-to-env')
        ->expectsOutputToContain('Duplicate key found in line 1 and 2: APP_NAME')
        ->assertExitCode(1);
});

it('creates target file if it does not exist', function (): void {
    File::put(base_path('.env.example.test'), 'APP_NAME=TestApp');

    // Run the command
    artisan(SyncEnvCommand::class, [
        'source' => '.env.example.test',
        'target' => '.env.test',
    ])
        ->assertExitCode(0);

    // Check that target file was created
    expect(File::exists(base_path('.env.test')))->toBeTrue();

    $result = File::get(base_path('.env.test'));
    expect($result)->toContain('APP_NAME=TestApp');
});

it('preserves comments and empty lines', function (): void {
    // Create source file
    $sourceContent = <<<'ENV'
    # Application Configuration
    APP_NAME=TestApp

    # Database Configuration
    DB_CONNECTION=mysql
    ENV;

    // Create target file with comments
    $targetContent = <<<'ENV'
# My App Configuration
APP_NAME=OldApp

# Other settings
EXISTING_KEY=value
ENV;

    File::put(base_path('.env.example.test'), $sourceContent);
    File::put(base_path('.env.test'), $targetContent);

    artisan(SyncEnvCommand::class, [
        'source' => '.env.example.test',
        'target' => '.env.test',
    ])
        ->assertExitCode(0);

    $result = File::get(base_path('.env.test'));

    expect($result)->toContain('# My App Configuration')
        ->toContain('# Other settings')
        ->toContain('EXISTING_KEY=value')
        ->toContain('DB_CONNECTION=mysql');
});
