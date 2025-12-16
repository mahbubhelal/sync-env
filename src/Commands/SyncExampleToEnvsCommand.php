<?php

declare(strict_types=1);

namespace Mahbub\SyncEnv\Commands;

use Dotenv\Dotenv;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class SyncExampleToEnvsCommand extends Command
{
    protected $signature = 'sync-env:example-to-envs
                            {--N|no-backup : Do not create a backup of the target .env file before syncing}
                            {--r|remove-backups : Remove previously created backup files}';

    protected $description = 'Sync environment keys from one the .env.example file to other .env files, preserving existing values in the target files.';

    public function handle(): int
    {
        try {
            $this->process();

            $this->newline();
            $this->info('Environment files synchronized successfully.');

            return 0;
        } catch (Exception $e) {
            $this->error('An error occurred: ' . $e->getMessage());
            // dd($e->getMessage());

            return 1;
        }
    }

    public function process(): void
    {
        if (App::environment() === 'workbench') {
            App::setBasePath(getcwd()); // @codeCoverageIgnore
        }

        $exampleEnvPath = base_path('.env.example');
        $baseEnvPath = base_path('.env');

        if (!File::exists($exampleEnvPath)) {
            throw new Exception('The .env.example file does not exist in: ' . base_path());
        }

        if (!File::exists($baseEnvPath)) {
            $this->info('The .env file does not exist in: ' . base_path());

            File::put($baseEnvPath, '');

            $this->info('Created empty .env file in: ' . base_path());
        }

        $allEnvFilePaths = collect(File::glob(base_path('.env*')))
            ->reject(fn ($path) => Str::match('/^\.env.*?.backup\./', basename($path)));

        $additionalEnvFiles = $allEnvFilePaths
            ->map(fn ($path) => basename($path))
            ->reject(fn ($path) => in_array($path, ['.env', '.env.example']));
        $additionalEnvCount = $additionalEnvFiles->count();

        if ($additionalEnvCount > 0) {
            $this->info(sprintf(
                'Found %d .env.* %s to sync: %s',
                $additionalEnvCount,
                Str::plural('file', $additionalEnvCount),
                $additionalEnvFiles->implode(', ')
            ));
        } else {
            $this->info('No additional .env.* files found to sync.');
        }

        $sourceData = $this->parseEnvFile($exampleEnvPath);

        $this->checkForInvalidKeys($sourceData);
        $this->checkForDuplicateKeys($sourceData);
        $this->checkForInvalidValues($sourceData);

        $envFilesPathsToProcess = collect($allEnvFilePaths)->reject(fn ($path) => $path === $exampleEnvPath);

        foreach ($envFilesPathsToProcess as $envFilePath) {
            $this->processEnvFile($envFilePath, $sourceData);
        }
    }

    private function processEnvFile(string $targetPath, array $sourceData): void
    {
        $this->newline();
        $this->info('Processing file: ' . basename($targetPath));

        if ($this->option('remove-backups')) {
            $backupFiles = collect(File::glob($targetPath . '.backup.*'));

            if ($backupFiles->isNotEmpty()) {
                foreach ($backupFiles as $backupFile) {
                    File::delete($backupFile);
                }

                $this->info(
                    sprintf(
                        'Deleted %d backup %s for %s.',
                        $backupFiles->count(),
                        Str::plural('file', $backupFiles->count()),
                        basename($targetPath)
                    )
                );
            }
        }

        if (!$this->option('no-backup')) {
            $backupPath = $targetPath . '.backup.' . now()->format('Y-m-d_H-i-s');

            File::copy($targetPath, $backupPath);

            $this->info("Backup created: {$backupPath}");
        }

        $targetData = $this->parseEnvFile($targetPath);
        $targetKeyValue = collect($targetData)->keyBy('key');
        $targetContent = [];

        foreach ($sourceData as $lineNumber => $data) {
            if ($data['is_empty']) {
                $targetContent[] = '';

                continue;
            }

            if ($data['is_comment']) {
                if (!isset($targetData[$lineNumber]) || $targetData[$lineNumber]['raw'] !== $data['raw']) {
                    $this->warn(sprintf(
                        <<<'STR'
                            Comment differs at line %d:
                                Source: %s
                                Target: %s
                            STR,
                        $lineNumber,
                        $data['raw'],
                        $targetData[$lineNumber]['raw'] ?? 'N/A'
                    ));
                }

                $targetContent[] = $data['raw'];

                continue;
            }

            $key = $data['key'];
            $value = ($targetKeyValue[$key]['value'] ?? null) ?? $data['value'];

            if (!isset($targetData[$lineNumber]) || $targetData[$lineNumber]['key'] !== $key) {
                $this->warn(sprintf(
                    <<<'STR'
                        Key differs at line %d:
                            Source: %s=%s
                            Target: %s
                        STR,
                    $lineNumber,
                    $key,
                    $data['value'],
                    $targetData[$lineNumber]['key'] ?? 'N/A'
                ));
            }

            unset($targetKeyValue[$data['key']]);

            $targetContent[] = "{$key}={$value}";
        }

        $targetKeyValue = $targetKeyValue->reject(fn ($item, $key) => $key === '');

        if ($targetKeyValue->isNotEmpty()) {
            $this->warn('Additional keys found in target file that are not present in source file: ' . $targetKeyValue->keys()->implode(', '));
        }

        File::put($targetPath, implode("\n", $targetContent));
    }

    private function parseEnvFile(string $path): array
    {
        $content = File::get($path);
        $lines = preg_split("/(\r\n|\n|\r)/", $content);

        if ($lines === false) {
            return []; // @codeCoverageIgnore
        }

        $lineNumber = 1;
        $lineData = [];

        foreach ($lines as $line) {
            $key = null;
            $value = null;

            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
            }

            $lineData[$lineNumber] = [
                'line_number' => $lineNumber,
                'key' => $key,
                'value' => $value,
                'raw' => $line,
                'is_comment' => str_starts_with($line, '#'),
                'is_empty' => $line === '',
            ];

            $lineNumber++;
        }

        return $lineData;
    }

    private function checkForInvalidKeys(array $data): void
    {
        foreach ($data as $lineNumber => $entry) {
            if ($entry['is_empty']) {
                continue;
            }

            if ($entry['is_comment']) {
                continue;
            }

            $key = $entry['key'];

            if (Str::startsWith($key, ' ') || Str::endsWith($key, ' ')) {
                throw new Exception("Invalid key found in line {$lineNumber}: {$key}. Leading or trailing spaces are not allowed.");
            }

            if ($key === null || preg_match('/^[A-Z][A-Z0-9_]+$/', $key) !== 1) {
                throw new Exception("Invalid key found in line {$lineNumber}: {$key}. Keys must start with an uppercase letter and contain only uppercase letters, numbers, and underscores.");
            }
        }
    }

    private function checkForDuplicateKeys(array $data): void
    {
        $keys = [];

        foreach ($data as $lineNumber => $entry) {
            if ($entry['is_empty']) {
                continue;
            }

            if ($entry['is_comment']) {
                continue;
            }

            $key = $entry['key'];

            if (isset($keys[$key])) {
                throw new Exception("Duplicate key found in line {$keys[$key]} and {$lineNumber}: {$key}");
            }

            $keys[$key] = $lineNumber;
        }
    }

    private function checkForInvalidValues(array $data): void
    {
        foreach ($data as $lineNumber => $entry) {
            if ($entry['is_empty']) {
                continue;
            }

            if ($entry['is_comment']) {
                continue;
            }

            $value = $entry['value'];

            if (Str::startsWith($value, ' ') || Str::endsWith($value, ' ')) {
                throw new Exception("Invalid value found in line {$lineNumber}: {$value}. Error: Leading or trailing spaces are not allowed.");
            }

            try {
                Dotenv::parse($entry['raw']);
            } catch (Exception $e) {
                $message = str_replace('Failed to parse dotenv file. ', '', $e->getMessage());

                throw new Exception("Invalid value found in line {$lineNumber}: {$message}", $e->getCode(), $e);
            }
        }
    }
}
