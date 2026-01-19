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
            ['Key', '.env.example Value', '.env Value'],
            [
                ['APP_NAME', 'TestApp', 'OldApp'],
                ['APP_ENV', 'local', 'production'],
                ['ANOTHER_KEY', '\'another_value\'', '"another value"'],
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
            ['Key', '.env.example Value', '.env Value'],
            [
                ['APP_NAME', 'TestApp', 'OldApp'],
                ['APP_ENV', 'local', 'production'],
                ['APP_DEBUG', 'true', 'N/A'],
                ['DB_CONNECTION', 'mysql', 'N/A'],
                ['CUSTOM_KEY', '"custom value"', 'N/A'],
                ['ANOTHER_KEY', '\'another_value\'', '"another value"'],
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
            ['Key', '.env.example Value', '.env Value', '.env.another Value'],
            [
                ['APP_NAME', 'TestApp', 'OldApp', 'App'],
                ['APP_ENV', 'local', 'production', 'staging'],
                ['ANOTHER_KEY', '\'another_value\'', '"another value"', 'N/A'],
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
            ['Key', '.env.example Value', '.env Value', '.env.backup.2025-12-25_00:00:00 Value'],
            [
                ['APP_NAME', 'TestApp', 'OldApp', 'OldApp'],
                ['APP_ENV', 'local', 'production', 'production'],
                ['ANOTHER_KEY', '\'another_value\'', '"another value"', '"another value"'],
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
})->with([
    [
        <<<'ENV'
            SINGLE_VALUE=single
            ENV,
        0,
    ],
    [
        <<<'ENV'
            IN_SINGLE_QUOTES='single quotes'
            ENV,
        0,
    ],
    [
        <<<'ENV'
            IN_DOUBLE_QUOTES="double quotes"
            ENV,
        0,
    ],
    [
        <<<'ENV'
            NESTED_QUOTES="nested 'single' quotes"
            ENV,
        0,
    ],
    [
        <<<'ENV'
            ESCAPED_QUOTES="escaped \"double\" quotes"
            ENV,
        0,
    ],
    [
        <<<'ENV'
            NESTED_SINGLE_QUOTES='nested "double" quotes'
            ENV,
        0,
    ],
    [
        <<<'ENV'
            ESCAPED_DOUBLE_QUOTES="escaped \"double\" quotes with 'single' quotes"
            ENV,
        0,
    ],
    [
        <<<'ENV'
            ANOTHER_KEY_REFERENCE=${SINGLE_VALUE}_reference
            ENV,
        0,
    ],
    [
        <<<'ENV'
            ANOTHER_KEY_IN_SINGLE_QUOTES='${SINGLE_VALUE}_in_quotes'
            ENV,
        0,
    ],
    [
        <<<'ENV'
            MISSING="${_VALUE}_in_quotes"
            ENV,
        0,
    ],
    [
        <<<'ENV'
            ANOTHER_KEY_IN_DOUBLE_QUOTES="${SINGLE_VALUE}_in_quotes"
            ENV,
        0,
    ],
    [
        <<<'ENV'
            ANOTHER_KEY_WITH_ESCAPED_QUOTES="escaped \${SINGLE_VALUE}_in_quotes"
            ENV,
        0,
    ],
    [
        <<<'ENV'
            ANOTHER_KEY_IN_DOUBLE_QUOTES_WITH_ESCAPED_QUOTES="escaped \${SINGLE_VALUE}_in_quotes with \"double\" quotes"
            ENV,
        0,
    ],
    [
        <<<'ENV'
            INVALID_LEADING_SPACE= leadingSpace
            ENV,
        1,
    ],
    [
        <<<'ENV'
            INVALID_SPACE_WITHIN_VALUE=leading Space
            ENV,
        1,
    ],
    [
        <<<'ENV'
            INVALID_ESCAPED_SINGLE_QUOTES='escaped \'single\' quotes'
            ENV,
        1,
    ],
    [
        <<<'ENV'
            INVALID_NESTED_SINGLE_QUOTES_ESCAPED='nested \'single\' quotes with "double" quotes'
            ENV,
        1,
    ],
    [
        <<<'ENV'
            INVALID_UNCLOSED_SINGLE_QUOTES='unclosed single quotes
            ENV,
        1,
    ],
    [
        <<<'ENV'
            INVALID_ESCAPED_BACKSLASH_IN_SINGLE_QUOTES='escaped backslash at end of line\\
            ENV,
        1,
    ],
    [
        <<<'ENV'
            INVALID_QUOTES="mismatched 'quotes'""
            ENV,
        1,
    ],
    [
        <<<'ENV'
            INVALID_QUOTES_IN_SINGLE_QUOTES='mismatched "quotes"''
            ENV,
        1,
    ],
    [
        <<<'ENV'
            INVALID_VALUE_REFERENCE=${VAl UE}
            ENV,
        1,
    ],
    [
        <<<'ENV'
            INVALID_A_VALUE_REFERENCE=${ VAlUE}
            ENV,
        1,
    ],
    [
        <<<'ENV'
            INVALID_B_VALUE_REFERENCE=${VAlUE }
            ENV,
        1,
    ],
]);

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
            ['Key', '.env.example Value', '.env Value', '.env.staging Value'],
            [
                ['APP_NAME', 'TestApp', 'ProdApp', 'StagingApp'],
                ['APP_ENV', 'local', 'production', 'staging'],
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
            ['Key', '.env.example Value', '.env Value'],
            [
                ['APP_NAME', 'TestApp', 'ProdApp'],
                ['APP_ENV', 'local', 'production'],
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
            ['Key', '.env Value', '.env.staging Value'],
            [
                ['APP_NAME', 'ProdApp', 'StagingApp'],
                ['APP_ENV', 'production', 'staging'],
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
            ['Key', '.env.example Value', '.env Value', '.env.staging Value'],
            [
                ['APP_NAME', 'TestApp', 'ProdApp', 'StagingApp'],
                ['APP_ENV', 'local', 'production', 'staging'],
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
            ['Key', '.env Value', '.env.staging Value'],
            [
                ['APP_NAME', 'ProdApp', 'StagingApp'],
                ['APP_ENV', 'production', 'staging'],
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
            ['Key', '.env.example Value', '.env Value'],
            [
                ['APP_NAME', 'TestApp', 'ProdApp'],
                ['APP_ENV', 'local', 'production'],
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
