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

it('can sync from source to target env file', function (): void {
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

    artisan('sync-env:example-to-envs')
        ->assertExitCode(0);

    expect(File::get(base_path('.env')))->toBe(
        <<<'ENV'
            APP_NAME=OldApp
            APP_ENV=production
            APP_DEBUG=true
            DB_CONNECTION=mysql

            # Custom
            CUSTOM_KEY="custom value"
            ANOTHER_KEY="another value"
            ENV
    );

    expect(File::get(base_path('.env.backup.') . now()->format('Y-m-d_H-i-s')))->toBe($targetContent);
});

it('can sync from source to multiple target env files', function (): void {
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

    $envFiles = [
        '.env',
        '.env.testing',
        '.env.staging',
        '.env.procution',
    ];

    File::put(base_path('.env.example'), $sourceContent);
    foreach ($envFiles as $envFile) {
        File::put(base_path($envFile), $targetContent);
    }

    artisan('sync-env:example-to-envs')
        ->assertExitCode(0);

    foreach ($envFiles as $envFile) {
        expect(File::get(base_path($envFile)))->toBe(
            <<<'ENV'
                APP_NAME=OldApp
                APP_ENV=production
                APP_DEBUG=true
                DB_CONNECTION=mysql

                # Custom
                CUSTOM_KEY="custom value"
                ANOTHER_KEY="another value"
                ENV
        );

        expect(File::get(base_path($envFile . '.backup.') . now()->format('Y-m-d_H-i-s')))->toBe($targetContent);
    }
});

it('fails when source file does not exist', function (): void {
    artisan('sync-env:example-to-envs')
        ->expectsOutputToContain('The .env.example file does not exist in: ' . base_path())
        ->assertExitCode(1);
});

it('creates target file if it does not exist', function (): void {
    $sourceContent = <<<'ENV'
        APP_NAME=TestApp
        ENV;

    File::put(base_path('.env.example'), 'APP_NAME=TestApp');
    $targetPath = base_path('.env');

    expect(File::exists($targetPath))->toBeFalse();
    artisan('sync-env:example-to-envs')
        ->assertExitCode(0);

    expect(File::exists($targetPath))->toBeTrue();
    expect(File::get($targetPath))->toContain($sourceContent);
});

it('fails when invalid keys are present in source', function (): void {
    $sourceContent = <<<'ENV'
        APP NAME=TestApp
        ENV;

    File::put(base_path('.env.example'), $sourceContent);

    artisan('sync-env:example-to-envs')
        ->expectsOutputToContain('Invalid key found in line 1: APP NAME. Keys must start with an uppercase letter and contain only uppercase letters, numbers, and underscores.')
        ->assertExitCode(1);

    $sourceContent = <<<'ENV'
         APPNAME=TestApp
        ENV;

    File::put(base_path('.env.example'), $sourceContent);

    artisan('sync-env:example-to-envs')
        ->expectsOutputToContain('Invalid key found in line 1:  APPNAME. Leading or trailing spaces are not allowed')
        ->assertExitCode(1);

    $sourceContent = <<<'ENV'
        invalid
        ENV;

    File::put(base_path('.env.example'), $sourceContent);

    artisan('sync-env:example-to-envs')
        ->expectsOutputToContain('Invalid key found in line 1: . Keys must start with an uppercase letter and contain only uppercase letters, numbers, and underscores.')
        ->assertExitCode(1);
});

it('fails when duplicate keys are present in source', function (): void {
    $sourceContent = <<<'ENV'
        APP_NAME=TestApp

        APP_NAME=Laravel
        ENV;

    File::put(base_path('.env.example'), $sourceContent);

    artisan('sync-env:example-to-envs')
        ->expectsOutputToContain('Duplicate key found in line 1 and 3: APP_NAME')
        ->assertExitCode(1);
});

it('fails when invalid values are present in source', function ($line, $exitCode): void {
    File::put(base_path('.env.example'), $line);

    artisan('sync-env:example-to-envs')
        ->expectsOutputToContain($exitCode === 1 ? 'Invalid value found in line 1:' : null)
        ->assertExitCode($exitCode);
    if ($exitCode === 0) {
        expect(File::get(base_path('.env')))->toBe($line);
    } else {
        expect(File::get(base_path('.env')))->toBe('');
    }
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

it('creates a backup of the target file before syncing', function (): void {
    $sourceContent = <<<'ENV'
        APP_NAME=TestApp
        ENV;

    $targetContent = <<<'ENV'
        APP_VERSION=1.0
        APP_NAME=OldApp
        ENV;

    File::put(base_path('.env.example'), $sourceContent);
    File::put(base_path('.env'), $targetContent);
    $backupPath = base_path('.env.backup.' . now()->format('Y-m-d_H-i-s'));

    expect(File::exists($backupPath))->toBeFalse();

    artisan('sync-env:example-to-envs')
        ->expectsOutputToContain("Backup created: {$backupPath}")
        ->assertExitCode(0);

    expect(File::exists($backupPath))->toBeTrue();
    expect(File::get($backupPath))->toBe($targetContent);
});

it('does not create a backup of the target file before syncing if --no-backup option is used', function (): void {
    $sourceContent = <<<'ENV'
        APP_NAME=TestApp
        ENV;

    $targetContent = <<<'ENV'
        APP_VERSION=1.0
        APP_NAME=OldApp
        ENV;

    File::put(base_path('.env.example'), $sourceContent);
    File::put(base_path('.env'), $targetContent);
    $backupPath = base_path('.env.backup.' . now()->format('Y-m-d_H-i-s'));

    expect(File::exists($backupPath))->toBeFalse();

    artisan('sync-env:example-to-envs --no-backup')
        ->assertExitCode(0);

    expect(File::exists($backupPath))->toBeFalse();
});

it('removes previous backups if --remove-backups option is used', function (): void {
    $sourceContent = <<<'ENV'
        APP_NAME=TestApp
        ENV;

    $targetContent = <<<'ENV'
        APP_VERSION=1.0
        APP_NAME=OldApp
        ENV;

    File::put(base_path('.env.example'), $sourceContent);
    File::put(base_path('.env'), $targetContent);
    $backupPath = base_path('.env.backup.' . now()->format('Y-m-d_H-i-s'));
    File::put($backupPath, $targetContent);

    expect(File::exists($backupPath))->toBeTrue();

    artisan('sync-env:example-to-envs --remove-backups --no-backup')
        ->assertExitCode(0);

    expect(File::exists($backupPath))->toBeFalse();
});

it('preserves comments and empty lines', function (): void {
    $sourceContent = <<<'ENV'
        # Application Configuration
        #APP_NAME=TestApp

        # Database Configuration
        DB_CONNECTION=mysql
        ENV;

    $targetContent = <<<'ENV'
        # My App Configuration
        APP_NAME=OldApp

        # Other settings
        EXISTING_KEY=value
        ENV;

    File::put(base_path('.env.example'), $sourceContent);
    File::put(base_path('.env'), $targetContent);

    artisan('sync-env:example-to-envs')
        ->assertExitCode(0);

    $result = File::get(base_path('.env'));
    expect($result)->toBe($sourceContent);
});

it('outputs message when additional .env.* files are found', function (): void {
    $sourceContent = <<<'ENV'
        APP_NAME=TestApp
        ENV;

    File::put(base_path('.env.example'), $sourceContent);
    File::put(base_path('.env'), $sourceContent);
    File::put(base_path('.env.testing'), $sourceContent);

    artisan('sync-env:example-to-envs')
        ->expectsOutputToContain('Found 1 .env.* file to sync: .env.testing')
        ->assertExitCode(0);

    File::put(base_path('.env.staging'), $sourceContent);
    artisan('sync-env:example-to-envs')
        ->expectsOutputToContain('Found 2 .env.* files to sync: .env.staging, .env.testing')
        ->assertExitCode(0);
});

it('outputs warning for comment and key differences', function (): void {
    $sourceContent = <<<'ENV'
        # Comment 1
        APP_NAME=TestApp
        ENV;
    $targetContent = <<<'ENV'
        ENV;

    File::put(base_path('.env.example'), $sourceContent);
    File::put(base_path('.env'), $targetContent);

    artisan('sync-env:example-to-envs')
        ->expectsOutputToContain(<<<'STR'
            Comment differs at line 1:
                Source: # Comment 1
                Target:
            STR)
        ->expectsOutputToContain(<<<'STR'
            Key differs at line 2:
                Source: APP_NAME=TestApp
                Target: N/A
            STR)
        ->assertExitCode(0);
});

it('outputs warning for additional keys in target', function (): void {
    $sourceContent = <<<'ENV'
        APP_NAME=TestApp
        ENV;
    $targetContent = <<<'ENV'
        APP_NAME=TestApp
        EXTRA_KEY=extra
        ENV;

    File::put(base_path('.env.example'), $sourceContent);
    File::put(base_path('.env'), $targetContent);

    artisan('sync-env:example-to-envs')
        ->expectsOutputToContain('Additional keys found in target file that are not present in source file: EXTRA_KEY')
        ->assertExitCode(0);
});
