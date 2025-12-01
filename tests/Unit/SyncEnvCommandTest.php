<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

use function Pest\Laravel\artisan;
use function Pest\Laravel\travelTo;

beforeEach(function (): void {
    travelTo('2024-06-01 00:00:00');

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
    $sourceContent = <<<'ENV_WRAP'
    APP_NAME=TestApp
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

it('creates target file if it does not exist', function (): void {
    $sourceContent = <<<'ENV'
    APP_NAME=TestApp
    ENV;

    File::put(base_path('.env.example'), 'APP_NAME=TestApp');
    $targetPath = base_path('.env');

    expect(File::exists($targetPath))->toBeFalse();
    artisan('sync-env:example-to-env')
        ->assertExitCode(0);

    expect(File::exists($targetPath))->toBeTrue();
    expect(File::get($targetPath))->toContain($sourceContent);
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

it('fails when invalid values are present in source', function ($lines, $expectedExitCodes): void {
    $targetPath = base_path('.env');

    foreach (explode("\n", $lines) as $index => $content) {
        File::put(base_path('.env.example'), $content);
        artisan('sync-env:example-to-env')
            ->expectsOutputToContain($expectedExitCodes[$index] !== 0)
            ->assertExitCode($expectedExitCodes[$index]);

        if ($expectedExitCodes[$index] === 0) {
            expect(File::get($targetPath))->toContain($content);
        } else {
            expect(File::get($targetPath))->toContain('');
        }
    }
})->with([[
    <<<'ENV'
    SINGLE_VALUE=single
    IN_SINGLE_QUOTES='single quotes'
    IN_DOUBLE_QUOTES="double quotes"
    NESTED_QUOTES="nested 'single' quotes"
    ESCAPED_QUOTES="escaped \"double\" quotes"
    NESTED_SINGLE_QUOTES='nested "double" quotes'
    ESCAPED_DOUBLE_QUOTES="escaped \"double\" quotes with 'single' quotes"
    ANOTHER_KEY_REFERENCE=${SINGLE_VALUE}_reference
    ANOTHER_KEY_IN_SINGLE_QUOTES='${SINGLE_VALUE}_in_quotes'
    MISSING="${_VALUE}_in_quotes"
    ANOTHER_KEY_IN_DOUBLE_QUOTES="${SINGLE_VALUE}_in_quotes"
    ANOTHER_KEY_WITH_ESCAPED_QUOTES="escaped \${SINGLE_VALUE}_in_quotes"
    ANOTHER_KEY_IN_DOUBLE_QUOTES_WITH_ESCAPED_QUOTES="escaped \${SINGLE_VALUE}_in_quotes with \"double\" quotes"
    INVALID_LEADING_SPACE= leadingSpace
    INVALID_SPACE_WITHIN_VALUE=leading Space
    INVALID_ESCAPED_SINGLE_QUOTES='escaped \'single\' quotes'
    INVALID_NESTED_SINGLE_QUOTES_ESCAPED='nested \'single\' quotes with "double" quotes'
    INVALID_UNCLOSED_SINGLE_QUOTES='unclosed single quotes
    INVALID_ESCAPED_BACKSLASH_IN_SINGLE_QUOTES='escaped backslash at end of line\\
    INVALID_QUOTES="mismatched 'quotes'""
    INVALID_QUOTES_IN_SINGLE_QUOTES='mismatched "quotes"''
    INVALID_VALUE_REFERENCE=${VAl UE}
    INVALID_A_VALUE_REFERENCE=${ VAlUE}
    INVALID_B_VALUE_REFERENCE=${VAlUE }
    ENV,
    [
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        0,
        1,
        1,
        1,
        1,
        1,
        1,
        1,
        1,
        1,
        1,
        1,
    ],
]]);

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

    artisan('sync-env:example-to-env')
        ->assertExitCode(0);

    $result = File::get(base_path('.env'));
    expect($result)->toBe($sourceContent);
});
