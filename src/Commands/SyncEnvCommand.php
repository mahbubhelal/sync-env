<?php

declare(strict_types=1);

namespace Mahbub\SyncEnv\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Dotenv\Dotenv;
use Dotenv\Exception\ValidationException;
use Throwable;
use Exception;

class SyncEnvCommand extends Command
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

            return 1;
        }
    }

    public function process(): void
    {
        $sourcePath = base_path('.env.example');
        $targetPath = base_path('.env');

        if (!File::exists($sourcePath)) {
            throw new Exception("File does not exist: {$sourcePath}");
        }

        if (!File::exists($targetPath)) {
            $this->info("File does not exist, creating: {$targetPath}");

            File::put($targetPath, '');

            $this->info("Created empty file: {$targetPath}");
        }

        if (!$this->option('no-backup')) {
            $backupPath = $targetPath . '.backup.' . date('Y-m-d_H-i-s');

            File::copy($targetPath, $backupPath);

            $this->info("Backup created: {$backupPath}");
        }

        $sourceData = $this->parseEnvFile($sourcePath);
        $targetData = $this->parseEnvFile($targetPath);

        $this->checkForDuplicateKeys($sourceData);

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
                            "Target: " . ($targetData[$lineNumber]['raw'] ?? 'N/A')
                    );
                }

                $targetContent[] = $data['raw'];

                $warnings++;
                continue;
            }

            $key = $data['key'];
            $value = ($targetKeyValue[$data['key']]['value'] ?? null) ?? $data['value'];

            if (!isset($targetData[$lineNumber]) || $targetData[$lineNumber]['key'] !== $data['key']) {
                $this->warn(
                    "Key differs at line {$lineNumber}:\n" .
                        "Source: {$data['key']}={$data['value']}\n" .
                        "Target: " . ($targetData[$lineNumber]['key'] ?? 'N/A')
                );

                $warnings++;
            }

            $targetContent[] = "{$key}={$value}";
        }

        File::put($targetPath, implode("\n", $targetContent));
    }

    private function parseEnvFile(string $path): array
    {
        $content = File::get($path);

        $this->validateEnv($content);

        $lines = preg_split("/(\r\n|\n|\r)/", $content);

        if ($lines === false) {
            return [];
        }

        $lineNumber = 1;
        $lineData = [];

        foreach ($lines as $line) {
            $line = trim($line);

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

    private function validateEnv(string $content): void
    {
        Dotenv::parse($content);
    }

    private function checkForDuplicateKeys(array $data): void
    {
        $keys = [];
        foreach ($data as $lineNumber => $entry) {
            if ($entry['is_comment']) {
                continue;
            }

            $key = $entry['key'];
            if ($key !== null) {
                if (isset($keys[$key])) {
                    throw new Exception("Duplicate key '{$key}' found at line {$keys[$key]} and {$lineNumber}.");
                }

                $keys[$key] = $lineNumber;
            }
        }
    }
}
