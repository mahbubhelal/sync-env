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
                            {--backup : Create a backup of the target file before syncing}';

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

    public function process(): int
    {
        // $sourcePath = base_path('.env.example');
        // $targetPath = base_path('.env');
        $sourcePath = __DIR__ . '/../../.env.example';
        $targetPath = __DIR__ . '/../../.env';

        if (! File::exists($sourcePath)) {
            throw new Exception("File does not exist: {$sourcePath}");

            return 1;
        }

        if (!File::exists($targetPath)) {
            $this->info("File does not exist, creating: {$targetPath}");

            File::put($targetPath, '');

            $this->info("Created empty file: {$targetPath}");
        }

        if ($this->option('backup')) {
            $backupPath = $targetPath . '.backup.' . date('Y-m-d_H-i-s');

            File::copy($targetPath, $backupPath);

            $this->info("Backup created: {$backupPath}");
        }

        $sourceData = $this->parseEnvFile($sourcePath);
        $targetData = $this->parseEnvFile($targetPath);

        foreach ($sourceData as $lineNumber => $data) {
            if ($data['is_comment']) {
                if (!isset($targetData[$lineNumber]) || $targetData[$lineNumber]['raw'] !== $data['raw']) {
                    $this->warn(
                        "Comment differs at line {$lineNumber}:\n" .
                        "Source: {$data['raw']}\n" .
                        "Target: " . ($targetData[$lineNumber]['raw'] ?? 'N/A')
                    );
                }

                continue;
            }

            if ($data['key']) {
                continue;
            }
        }

        return 0;
        $added = 0;
        $updated = 0;
        $skipped = 0;

        $newContent = [];
        $processedKeys = [];

        foreach ($targetLines as $line) {
            $trimmedLine = trim($line);

            // Skip empty lines and comments for now, we'll handle them separately
            if (empty($trimmedLine) || str_starts_with($trimmedLine, '#')) {
                $newContent[] = $line;

                continue;
            }

            // Parse key from line
            if (str_contains($line, '=')) {
                $key = trim(explode('=', $line, 2)[0]);
                $processedKeys[] = $key;

                if (array_key_exists($key, $sourceKeys)) {
                    if ($this->option('force') || ! array_key_exists($key, $targetKeys)) {
                        $newContent[] = $key . '=' . $sourceKeys[$key];
                        if (array_key_exists($key, $targetKeys)) {
                            $updated++;
                        } else {
                            $added++;
                        }
                    } else {
                        $newContent[] = $line; // Keep existing value
                        $skipped++;
                    }
                } else {
                    $newContent[] = $line; // Keep existing line even if key not in source
                }
            } else {
                $newContent[] = $line;
            }
        }

        // Add new keys that don't exist in target
        foreach ($sourceKeys as $key => $value) {
            if (! in_array($key, $processedKeys)) {
                $newContent[] = $key . '=' . $value;
                $added++;
            }
        }

        // Remove empty lines at the end and ensure single newline
        while (end($newContent) === '') {
            array_pop($newContent);
        }

        File::put($targetPath, implode("\n", $newContent) . "\n");

        // Display summary
        $this->info("\n" . str_repeat('=', 50));
        $this->info('Sync completed successfully!');
        $this->info("Added: {$added} keys");

        if ($updated > 0) {
            $this->info("Updated: {$updated} keys");
        }

        if ($skipped > 0) {
            $this->warn("Skipped: {$skipped} keys (use --force to overwrite)");
        }

        $this->info(str_repeat('=', 50));

        return self::SUCCESS;
    }

    private function parseEnvFile(string $path): array
    {
        if (!File::exists($path)) {
            return [];
        }

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
            ];

            $lineNumber++;
        }

        return $lineData;
    }

    function validateEnv(string $content): void
    {
        Dotenv::parse($content);
    }
}
