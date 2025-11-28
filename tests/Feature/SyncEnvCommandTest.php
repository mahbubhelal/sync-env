<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Mahbub\SyncEnv\Commands\SyncEnvCommand;

beforeEach(function () {
    // Clean up any test files
    $testFiles = [
        base_path('.env.test'),
        base_path('.env.example.test'),
    ];

    foreach ($testFiles as $file) {
        if (File::exists($file)) {
            File::delete($file);
        }
    }
});

afterEach(function () {
    // Clean up test files
    $testFiles = [
        base_path('.env.test'),
        base_path('.env.example.test'),
    ];

    foreach ($testFiles as $file) {
        if (File::exists($file)) {
            File::delete($file);
        }
    }

    // Clean up backup files
    $backupFiles = File::glob(base_path('.env.test.backup.*'));
    foreach ($backupFiles as $file) {
        File::delete($file);
    }
});

it('can sync keys from source to target env file', function () {
    // Create source file
    $sourceContent = <<<'ENV'
APP_NAME=TestApp
APP_ENV=local
APP_DEBUG=true
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
NEW_KEY=new_value
ENV;

    // Create target file with some existing content
    $targetContent = <<<'ENV'
APP_NAME=OldApp
APP_ENV=production
EXISTING_KEY=existing_value
ENV;

    File::put(base_path('.env.example.test'), $sourceContent);
    File::put(base_path('.env.test'), $targetContent);

    // Run the command
    $this->artisan(SyncEnvCommand::class, [
        'source' => '.env.example.test',
        'target' => '.env.test',
    ])
        ->assertExitCode(0);

    // Check that target file was updated
    $result = File::get(base_path('.env.test'));

    expect($result)->toContain('APP_NAME=OldApp') // Existing values preserved
        ->toContain('APP_ENV=production')
        ->toContain('EXISTING_KEY=existing_value')
        ->toContain('APP_DEBUG=true') // New keys added
        ->toContain('DB_CONNECTION=mysql')
        ->toContain('DB_HOST=127.0.0.1')
        ->toContain('NEW_KEY=new_value');
});

it('can force overwrite existing values', function () {
    // Create source file
    $sourceContent = <<<'ENV'
APP_NAME=NewApp
APP_ENV=local
NEW_KEY=new_value
ENV;

    // Create target file
    $targetContent = <<<'ENV'
APP_NAME=OldApp
APP_ENV=production
EXISTING_KEY=existing_value
ENV;

    File::put(base_path('.env.example.test'), $sourceContent);
    File::put(base_path('.env.test'), $targetContent);

    // Run the command with force option
    $this->artisan(SyncEnvCommand::class, [
        'source' => '.env.example.test',
        'target' => '.env.test',
        '--force' => true,
    ])
        ->assertExitCode(0);

    // Check that values were overwritten
    $result = File::get(base_path('.env.test'));

    expect($result)->toContain('APP_NAME=NewApp') // Values overwritten
        ->toContain('APP_ENV=local')
        ->toContain('EXISTING_KEY=existing_value') // Existing key preserved
        ->toContain('NEW_KEY=new_value'); // New key added
});

it('creates backup when requested', function () {
    // Create source and target files
    File::put(base_path('.env.example.test'), 'APP_NAME=TestApp');
    File::put(base_path('.env.test'), 'APP_NAME=OldApp');

    // Run command with backup option
    $this->artisan(SyncEnvCommand::class, [
        'source' => '.env.example.test',
        'target' => '.env.test',
        '--backup' => true,
    ])
        ->assertExitCode(0);

    // Check that backup was created
    $backupFiles = File::glob(base_path('.env.test.backup.*'));
    expect($backupFiles)->toHaveCount(1);

    $backupContent = File::get($backupFiles[0]);
    expect($backupContent)->toBe('APP_NAME=OldApp');
});

it('creates target file if it does not exist', function () {
    // Create only source file
    File::put(base_path('.env.example.test'), 'APP_NAME=TestApp');

    // Run the command
    $this->artisan(SyncEnvCommand::class, [
        'source' => '.env.example.test',
        'target' => '.env.test',
    ])
        ->assertExitCode(0);

    // Check that target file was created
    expect(File::exists(base_path('.env.test')))->toBeTrue();

    $result = File::get(base_path('.env.test'));
    expect($result)->toContain('APP_NAME=TestApp');
});

it('fails when source file does not exist', function () {
    $this->artisan(SyncEnvCommand::class, [
        'source' => '.env.nonexistent',
        'target' => '.env.test',
    ])
        ->assertExitCode(1);
});

it('preserves comments and empty lines', function () {
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

    $this->artisan(SyncEnvCommand::class, [
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
