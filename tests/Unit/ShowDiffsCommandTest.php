<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

use function Pest\Laravel\artisan;
use function Pest\Laravel\travelTo;

beforeEach(function (): void {
    travelTo('2025-01-01 00:00:00');

    $testEnvFiles = File::glob(base_path('.env*'));

    foreach ($testEnvFiles as $file) {
        if (File::exists($file)) {
            File::delete($file);
        }
    }
});

afterAll(function (): void {
    $testEnvFiles = File::glob(base_path('.env*'));

    foreach ($testEnvFiles as $file) {
        if (File::exists($file)) {
            File::delete($file);
        }
    }
});

it('can show diff between source and target env file', function (): void {
    $sourceContent = <<<'ENV'
        APP_NAME=TestApp
        APP_ENV=local
        APP_DEBUG=true
        DB_CONNECTION=mysql

        # Custom
        CUSTOM_KEY="custom value"
        ANOTHER_KEY='another_value'
        ENV;

    $targetContent = <<<'ENV'
        APP_NAME=OldApp
        EXISTING_KEY=existing_value
        APP_ENV=production
        ANOTHER_KEY="another value"
        ENV;

    File::put(base_path('.env.example'), $sourceContent);
    File::put(base_path('.env'), $targetContent);

    artisan('sync-env:show-diffs')
        ->expectsTable(
            ['#', 'Key', '.env.example Value', '.env Value'],
            [
                [1, 'APP_NAME', 'TestApp', 'OldApp'],
                [2, 'APP_ENV', 'local', 'production'],
                [3, 'APP_DEBUG', 'true', 'N/A'],
                [4, 'DB_CONNECTION', 'mysql', 'N/A'],
                [7, 'CUSTOM_KEY', '"custom value"', 'N/A'],
                [8, 'ANOTHER_KEY', '\'another_value\'', '"another value"'],
                ['-', 'EXISTING_KEY', 'N/A', 'existing_value'],
            ],
        );
});

it('can show all diff between source and target env file when --all option is used', function (): void {
    $sourceContent = <<<'ENV'
        APP_NAME=TestApp
        APP_ENV=local
        APP_DEBUG=true
        DB_CONNECTION=mysql

        # Custom
        CUSTOM_KEY="custom value"
        ANOTHER_KEY='another_value'
        ENV;

    $targetContent = <<<'ENV'
        APP_NAME=OldApp
        EXISTING_KEY=existing_value
        APP_ENV=production
        ANOTHER_KEY="another value"
        ENV;

    File::put(base_path('.env.example'), $sourceContent);
    File::put(base_path('.env'), $targetContent);

    artisan('sync-env:show-diffs --all')
        ->expectsTable(
            ['#', 'Key', '.env.example Value', '.env Value'],
            [
                [1, 'APP_NAME', 'TestApp', 'OldApp'],
                [2, 'APP_ENV', 'local', 'production'],
                [3, 'APP_DEBUG', 'true', 'N/A'],
                [4, 'DB_CONNECTION', 'mysql', 'N/A'],
                [7, 'CUSTOM_KEY', '"custom value"', 'N/A'],
                [8, 'ANOTHER_KEY', '\'another_value\'', '"another value"'],
                ['-', 'EXISTING_KEY', 'N/A', 'existing_value'],
            ],
        );
});

it('can show diff between source and multiple target env files', function (): void {
    $sourceContent = <<<'ENV'
        APP_NAME=TestApp
        APP_ENV=local
        APP_DEBUG=true
        DB_CONNECTION=mysql

        # Custom
        CUSTOM_KEY="custom value"
        ANOTHER_KEY='another_value'
        ENV;

    $targetContent = <<<'ENV'
        APP_NAME=OldApp
        EXISTING_KEY=existing_value
        APP_ENV=production
        ANOTHER_KEY="another value"
        ENV;

    $anotherTargetContent = <<<'ENV'
        APP_NAME=App
        APP_ENV=staging
        ENV;

    File::put(base_path('.env.example'), $sourceContent);
    File::put(base_path('.env'), $targetContent);
    File::put(base_path('.env.another'), $anotherTargetContent);

    artisan('sync-env:show-diffs')
        ->expectsTable(
            ['#', 'Key', '.env.example Value', '.env Value', '.env.another Value'],
            [
                [1, 'APP_NAME', 'TestApp', 'OldApp', 'App'],
                [2, 'APP_ENV', 'local', 'production', 'staging'],
                [3, 'APP_DEBUG', 'true', 'N/A', 'N/A'],
                [4, 'DB_CONNECTION', 'mysql', 'N/A', 'N/A'],
                [7, 'CUSTOM_KEY', '"custom value"', 'N/A', 'N/A'],
                [8, 'ANOTHER_KEY', '\'another_value\'', '"another value"', 'N/A'],
                ['-', 'EXISTING_KEY', 'N/A', 'existing_value', 'N/A'],
            ],
        );
});

it('can show diff between source and multiple target env files including backups when --include-backup is used', function (): void {
    $sourceContent = <<<'ENV'
        APP_NAME=TestApp
        APP_ENV=local
        APP_DEBUG=true
        DB_CONNECTION=mysql

        # Custom
        CUSTOM_KEY="custom value"
        ANOTHER_KEY='another_value'
        ENV;

    $targetContent = <<<'ENV'
        APP_NAME=OldApp
        EXISTING_KEY=existing_value
        APP_ENV=production
        ANOTHER_KEY="another value"
        ENV;

    File::put(base_path('.env.example'), $sourceContent);
    File::put(base_path('.env'), $targetContent);
    File::put(base_path('.env.backup.2025-12-25_00:00:00'), $targetContent);

    artisan('sync-env:show-diffs --include-backup')
        ->expectsTable(
            ['#', 'Key', '.env.example Value', '.env Value', '.env.backup.2025-12-25_00:00:00 Value'],
            [
                [1, 'APP_NAME', 'TestApp', 'OldApp', 'OldApp'],
                [2, 'APP_ENV', 'local', 'production', 'production'],
                [3, 'APP_DEBUG', 'true', 'N/A', 'N/A'],
                [4, 'DB_CONNECTION', 'mysql', 'N/A', 'N/A'],
                [7, 'CUSTOM_KEY', '"custom value"', 'N/A', 'N/A'],
                [8, 'ANOTHER_KEY', '\'another_value\'', '"another value"', '"another value"'],
                ['-', 'EXISTING_KEY', 'N/A', 'existing_value', 'existing_value'],
            ],
        );
});

it('fails when source file does not exist', function (): void {
    artisan('sync-env:show-diffs')
        ->expectsOutputToContain('The .env.example file does not exist in: ' . base_path())
        ->assertExitCode(1);
});

it('fails when target file does not exist', function (): void {
    File::put(base_path('.env.example'), 'APP_NAME=TestApp');

    artisan('sync-env:show-diffs')
        ->expectsOutputToContain('The .env file does not exist in: ' . base_path())
        ->assertExitCode(1);
});

it('fails when a line begins or ends with a space', function (): void {
    $sourceContent = ' INVALID_LEADING_SPACE=';

    File::put(base_path('.env.example'), $sourceContent);
    File::put(base_path('.env'), '');

    artisan('sync-env:show-diffs')
        ->expectsOutputToContain('Invalid entry found in line 1:  INVALID_LEADING_SPACE=. Leading or trailing spaces are not allowed.')
        ->assertExitCode(1);

    $sourceContent = 'INVALID_TRAILING_SPACE= ';

    File::put(base_path('.env.example'), $sourceContent);

    artisan('sync-env:show-diffs')
        ->expectsOutputToContain('Invalid entry found in line 1: INVALID_TRAILING_SPACE= . Leading or trailing spaces are not allowed.')
        ->assertExitCode(1);
});

it('fails when invalid keys are present in source', function (): void {
    $sourceContent = <<<'ENV'
        APP NAME=TestApp
        ENV;

    File::put(base_path('.env.example'), $sourceContent);
    File::put(base_path('.env'), '');

    artisan('sync-env:show-diffs')
        ->expectsOutputToContain('Invalid key found in line 1: APP NAME. Keys must start with an uppercase letter and contain only uppercase letters, numbers, and underscores.')
        ->assertExitCode(1);

    $sourceContent = <<<'ENV'
        invalid
        ENV;

    File::put(base_path('.env.example'), $sourceContent);

    artisan('sync-env:show-diffs')
        ->expectsOutputToContain('Invalid key found in line 1: . Keys must start with an uppercase letter and contain only uppercase letters, numbers, and underscores.')
        ->assertExitCode(1);
});

it('fails when duplicate keys are present in source', function (): void {
    $sourceContent = <<<'ENV'
        APP_NAME=TestApp

        APP_NAME=Laravel
        ENV;

    File::put(base_path('.env.example'), $sourceContent);
    File::put(base_path('.env'), '');

    artisan('sync-env:show-diffs')
        ->expectsOutputToContain('Duplicate key found in line 1 and 3: APP_NAME')
        ->assertExitCode(1);
});

it('fails when invalid values are present in source', function ($line, $exitCode): void {
    File::put(base_path('.env.example'), $line);
    File::put(base_path('.env'), '');

    artisan('sync-env:show-diffs')
        ->expectsOutputToContain($exitCode === 1 ? 'Invalid value found in line 1:' : null)
        ->assertExitCode($exitCode);
})->with('env_values');

it('can filter env files with --only option', function (): void {
    $sourceContent = <<<'ENV'
        APP_NAME=TestApp
        APP_ENV=local
        ENV;

    $envContent = <<<'ENV'
        APP_NAME=ProdApp
        APP_ENV=production
        ENV;

    $stagingContent = <<<'ENV'
        APP_NAME=StagingApp
        APP_ENV=staging
        ENV;

    $testingContent = <<<'ENV'
        APP_NAME=TestingApp
        APP_ENV=testing
        ENV;

    File::put(base_path('.env.example'), $sourceContent);
    File::put(base_path('.env'), $envContent);
    File::put(base_path('.env.staging'), $stagingContent);
    File::put(base_path('.env.testing'), $testingContent);

    artisan('sync-env:show-diffs --only=.env.example,.env,.env.staging')
        ->expectsTable(
            ['#', 'Key', '.env.example Value', '.env Value', '.env.staging Value'],
            [
                [1, 'APP_NAME', 'TestApp', 'ProdApp', 'StagingApp'],
                [2, 'APP_ENV', 'local', 'production', 'staging'],
            ],
        );
});

it('includes .env.example by default when --only results in less than 2 files', function (): void {
    $sourceContent = <<<'ENV'
        APP_NAME=TestApp
        APP_ENV=local
        ENV;

    $envContent = <<<'ENV'
        APP_NAME=ProdApp
        APP_ENV=production
        ENV;

    $stagingContent = <<<'ENV'
        APP_NAME=StagingApp
        APP_ENV=staging
        ENV;

    File::put(base_path('.env.example'), $sourceContent);
    File::put(base_path('.env'), $envContent);
    File::put(base_path('.env.staging'), $stagingContent);

    artisan('sync-env:show-diffs --only=.env')
        ->expectsTable(
            ['#', 'Key', '.env.example Value', '.env Value'],
            [
                [1, 'APP_NAME', 'TestApp', 'ProdApp'],
                [2, 'APP_ENV', 'local', 'production'],
            ],
        );
});

it('does not include .env.example when --only has 2+ files', function (): void {
    $sourceContent = <<<'ENV'
        APP_NAME=TestApp
        APP_ENV=local
        ENV;

    $envContent = <<<'ENV'
        APP_NAME=ProdApp
        APP_ENV=production
        ENV;

    $stagingContent = <<<'ENV'
        APP_NAME=StagingApp
        APP_ENV=staging
        ENV;

    File::put(base_path('.env.example'), $sourceContent);
    File::put(base_path('.env'), $envContent);
    File::put(base_path('.env.staging'), $stagingContent);

    artisan('sync-env:show-diffs --only=.env,.env.staging')
        ->expectsTable(
            ['#', 'Key', '.env Value', '.env.staging Value'],
            [
                [1, 'APP_NAME', 'ProdApp', 'StagingApp'],
                [2, 'APP_ENV', 'production', 'staging'],
            ],
        );
});

it('can exclude env files with --exclude option', function (): void {
    $sourceContent = <<<'ENV'
        APP_NAME=TestApp
        APP_ENV=local
        ENV;

    $envContent = <<<'ENV'
        APP_NAME=ProdApp
        APP_ENV=production
        ENV;

    $stagingContent = <<<'ENV'
        APP_NAME=StagingApp
        APP_ENV=staging
        ENV;

    $testingContent = <<<'ENV'
        APP_NAME=TestingApp
        APP_ENV=testing
        ENV;

    File::put(base_path('.env.example'), $sourceContent);
    File::put(base_path('.env'), $envContent);
    File::put(base_path('.env.staging'), $stagingContent);
    File::put(base_path('.env.testing'), $testingContent);

    artisan('sync-env:show-diffs --exclude=.env.testing')
        ->expectsTable(
            ['#', 'Key', '.env.example Value', '.env Value', '.env.staging Value'],
            [
                [1, 'APP_NAME', 'TestApp', 'ProdApp', 'StagingApp'],
                [2, 'APP_ENV', 'local', 'production', 'staging'],
            ],
        );
});

it('can exclude .env.example with --exclude option if 2+ files remain', function (): void {
    $sourceContent = <<<'ENV'
        APP_NAME=TestApp
        APP_ENV=local
        ENV;

    $envContent = <<<'ENV'
        APP_NAME=ProdApp
        APP_ENV=production
        ENV;

    $stagingContent = <<<'ENV'
        APP_NAME=StagingApp
        APP_ENV=staging
        ENV;

    File::put(base_path('.env.example'), $sourceContent);
    File::put(base_path('.env'), $envContent);
    File::put(base_path('.env.staging'), $stagingContent);

    artisan('sync-env:show-diffs --exclude=.env.example')
        ->expectsTable(
            ['#', 'Key', '.env Value', '.env.staging Value'],
            [
                [1, 'APP_NAME', 'ProdApp', 'StagingApp'],
                [2, 'APP_ENV', 'production', 'staging'],
            ],
        );
});

it('includes .env.example by default when --exclude results in less than 2 files', function (): void {
    $sourceContent = <<<'ENV'
        APP_NAME=TestApp
        APP_ENV=local
        ENV;

    $envContent = <<<'ENV'
        APP_NAME=ProdApp
        APP_ENV=production
        ENV;

    File::put(base_path('.env.example'), $sourceContent);
    File::put(base_path('.env'), $envContent);

    artisan('sync-env:show-diffs --exclude=.env.example')
        ->expectsTable(
            ['#', 'Key', '.env.example Value', '.env Value'],
            [
                [1, 'APP_NAME', 'TestApp', 'ProdApp'],
                [2, 'APP_ENV', 'local', 'production'],
            ],
        );
});

it('fails when less than 2 env files are selected with --only', function (): void {
    $sourceContent = <<<'ENV'
        APP_NAME=TestApp
        ENV;

    File::put(base_path('.env.example'), $sourceContent);
    File::put(base_path('.env'), $sourceContent);

    artisan('sync-env:show-diffs --only=.env.nonexistent')
        ->expectsOutputToContain('At least 2 env files are required to show differences.')
        ->assertExitCode(1);
});

it('fails when less than 2 env files are selected with --exclude', function (): void {
    $sourceContent = <<<'ENV'
        APP_NAME=TestApp
        ENV;

    File::put(base_path('.env.example'), $sourceContent);
    File::put(base_path('.env'), $sourceContent);

    artisan('sync-env:show-diffs --exclude=.env')
        ->expectsOutputToContain('At least 2 env files are required to show differences.')
        ->assertExitCode(1);
});

it('hides identical source keys by default', function (): void {
    $sourceContent = <<<'ENV'
        APP_NAME=TestApp
        APP_ENV=local
        ENV;

    $targetContent = <<<'ENV'
        APP_NAME=TestApp
        APP_ENV=production
        ENV;

    File::put(base_path('.env.example'), $sourceContent);
    File::put(base_path('.env'), $targetContent);

    artisan('sync-env:show-diffs')
        ->expectsTable(
            ['#', 'Key', '.env.example Value', '.env Value'],
            [
                [2, 'APP_ENV', 'local', 'production'],
            ],
        );
});

it('hides identical extra keys when example is excluded', function (): void {
    $sourceContent = <<<'ENV'
        APP_NAME=TestApp
        ENV;

    $envContent = <<<'ENV'
        APP_NAME=ProdApp
        EXTRA_KEY=same
        ENV;

    $stagingContent = <<<'ENV'
        APP_NAME=StagingApp
        EXTRA_KEY=same
        ENV;

    File::put(base_path('.env.example'), $sourceContent);
    File::put(base_path('.env'), $envContent);
    File::put(base_path('.env.staging'), $stagingContent);

    artisan('sync-env:show-diffs --exclude=.env.example')
        ->expectsTable(
            ['#', 'Key', '.env Value', '.env.staging Value'],
            [
                [1, 'APP_NAME', 'ProdApp', 'StagingApp'],
            ],
        );
});
