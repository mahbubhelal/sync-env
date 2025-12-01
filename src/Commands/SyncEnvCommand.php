<?php

declare(strict_types=1);

namespace Mahbub\SyncEnv\Commands;

use Dotenv\Dotenv;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class SyncEnvCommand extends Command
{
    protected $signature = 'sync-env:example-to-env
                            {--no-backup : Do not create a backup of the target .env file before syncing}';

    protected $description = 'Sync environment keys from one env file to another';

    public function handle(): int
    {
        try {
            $this->process();

            return 0;
        } catch (Exception $e) {
            $this->error('An error occurred: ' . $e->getMessage());
            // dd($e->getMessage());

            return 1;
        }
    }

    public function process(): void
    {
        $sourcePath = base_path('.env.example');
        $targetPath = base_path('.env');
        // $sourcePath = __DIR__ . '/../../.env';

        if (!File::exists($sourcePath)) {
            throw new Exception("File does not exist: {$sourcePath}");
        }

        if (!File::exists($targetPath)) {
            $this->info("File does not exist, creating: {$targetPath}");

            File::put($targetPath, '');

            $this->info("Created empty file: {$targetPath}");
        }

        $sourceData = $this->parseEnvFile($sourcePath);
        $targetData = $this->parseEnvFile($targetPath);

        $this->checkForInvalidKeys($sourceData);
        $this->checkForDuplicateKeys($sourceData);
        $this->checkForInvalidValues($sourceData);

        if (!$this->option('no-backup')) {
            $backupPath = $targetPath . '.backup.' . now()->format('Y-m-d_H-i-s');

            File::copy($targetPath, $backupPath);

            $this->info("Backup created: {$backupPath}");
        }

        $targetKeyValue = collect($targetData)->keyBy('key')->all();
        $warnings = 0;
        $targetContent = [];

        foreach ($sourceData as $lineNumber => $data) {
            if ($data['is_empty']) {
                $targetContent[] = '';

                continue;
            }

            if ($data['is_comment']) {
                if (!isset($targetData[$lineNumber]) || $targetData[$lineNumber]['raw'] !== $data['raw']) {
                    $this->warn(
                        "Comment differs at line {$lineNumber}:\n" .
                            "Source: {$data['raw']}\n" .
                            'Target: ' . ($targetData[$lineNumber]['raw'] ?? 'N/A')
                    );
                }

                $targetContent[] = $data['raw'];

                ++$warnings;

                continue;
            }

            $key = $data['key'];
            $value = ($targetKeyValue[$data['key']]['value'] ?? null) ?? $data['value'];

            if (!isset($targetData[$lineNumber]) || $targetData[$lineNumber]['key'] !== $data['key']) {
                $this->warn(
                    "Key differs at line {$lineNumber}:\n" .
                        "Source: {$data['key']}={$data['value']}\n" .
                        'Target: ' . ($targetData[$lineNumber]['key'] ?? 'N/A')
                );

                ++$warnings;
            }

            $targetContent[] = "{$key}={$value}";
        }

        File::put($targetPath, implode("\n", $targetContent));
    }

    private function parseEnvFile(string $path): array
    {
        $content = File::get($path);

        $lines = preg_split("/(\r\n|\n|\r)/", $content);

        if ($lines === false) {
            return [];
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

            ++$lineNumber;
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

            if ($entry['key'] === null) {
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

            if ($entry['value'] === null) {
                continue;
            }

            $value = $entry['value'];

            if (Str::startsWith($value, ' ') || Str::endsWith($value, ' ')) {
                throw new Exception("Invalid value  found in line {$lineNumber}: {$value}. Error: Leading or trailing spaces are not allowed.");
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
