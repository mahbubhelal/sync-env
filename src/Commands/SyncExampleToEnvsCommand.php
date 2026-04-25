<?php

declare(strict_types=1);

namespace Mahbub\SyncEnv\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Mahbub\SyncEnv\EnvFileParser;
use Mahbub\SyncEnv\EnvLine;
use Mahbub\SyncEnv\ResolvesBasePath;

final class SyncExampleToEnvsCommand extends Command
{
    use ResolvesBasePath;

    protected $signature = 'sync-env:example-to-envs
                            {--N|no-backup : Do not create a backup of the target .env file before syncing}
                            {--r|remove-backups : Remove previously created backup files}
                            {--d|dry-run : Preview changes without writing to files}';

    protected $description = 'Sync environment keys from the .env.example file to other .env files, preserving existing values in the target files.';

    public function handle(EnvFileParser $parser): int
    {
        try {
            $this->process($parser);

            $this->newLine();

            if ($this->option('dry-run') === true) {
                $this->info('[DRY RUN] No files were modified.');
            } else {
                $this->info('Environment files synchronized successfully.');
            }

            return 0;
        } catch (Exception $e) {
            $this->error('An error occurred: ' . $e->getMessage());

            return 1;
        }
    }

    private function process(EnvFileParser $parser): void
    {
        $this->resolveBasePath();

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

        /** @var Collection<int, string> */
        $allEnvFilePaths = collect(File::glob(base_path('.env*')));
        $allEnvFilePaths = $allEnvFilePaths->reject(fn ($path): bool => (bool) Str::match('/^\.env.*.backup\./', basename($path)));

        $additionalEnvFiles = $allEnvFilePaths
            ->map(fn ($path): string => basename($path))
            ->reject(fn ($path): bool => in_array($path, ['.env', '.env.example'], true));
        $additionalEnvCount = $additionalEnvFiles->count();

        if ($additionalEnvCount > 0) {
            $this->info(sprintf(
                'Found %d .env.* %s to sync: %s',
                $additionalEnvCount,
                Str::plural('file', $additionalEnvCount),
                $additionalEnvFiles->implode(', ')
            ));
        }

        $sourceData = $parser->parseSource($exampleEnvPath);

        $envFilesPathsToProcess = $allEnvFilePaths->reject(fn ($path): bool => $path === $exampleEnvPath);

        foreach ($envFilesPathsToProcess as $envFilePath) {
            $this->processEnvFile($envFilePath, $sourceData, $parser);
        }
    }

    /**
     * @param  array<int, EnvLine>  $sourceData
     */
    private function processEnvFile(string $targetPath, array $sourceData, EnvFileParser $parser): void
    {
        $isDryRun = $this->option('dry-run') === true;

        $this->newLine();
        $this->info(($isDryRun ? '[DRY RUN] ' : '') . 'Processing file: ' . basename($targetPath));

        if (!$isDryRun) {
            $this->handleBackups($targetPath);
        }

        $targetData = $parser->parse($targetPath);
        $targetKeyValue = collect($targetData)
            ->reject(fn (EnvLine $item): bool => !$item->isKeyValue())
            ->keyBy('key');

        $result = $this->buildSyncedContent($sourceData, $targetData, $targetKeyValue);

        if ($isDryRun) {
            $this->reportDryRun($result['addedKeys'], $result['removedKeys']);
        } else {
            $content = rtrim(implode("\n", $result['content']), "\n") . "\n";

            File::put($targetPath, $content);
        }
    }

    private function handleBackups(string $targetPath): void
    {
        if ($this->option('remove-backups') === true) {
            /** @var Collection<int, string> */
            $backupFiles = collect(File::glob($targetPath . '.backup.*'));

            if ($backupFiles->isNotEmpty()) {
                foreach ($backupFiles as $backupFile) {
                    File::delete($backupFile);
                }

                $this->info(
                    sprintf(
                        'Deleted %d backup %s for %s',
                        $backupFiles->count(),
                        Str::plural('file', $backupFiles->count()),
                        basename($targetPath)
                    )
                );
            }
        }

        if ($this->option('no-backup') !== true) {
            $backupPath = $targetPath . '.backup.' . now()->format('Y-m-d_H-i-s');

            File::copy($targetPath, $backupPath);

            $this->info("Backup created: {$backupPath}");
        }
    }

    /**
     * @param  array<int, EnvLine>  $sourceData
     * @param  array<int, EnvLine>  $targetData
     * @param  Collection<string, EnvLine>  $targetKeyValue
     * @return array{content: list<string>, addedKeys: list<string>, removedKeys: list<string>}
     */
    private function buildSyncedContent(array $sourceData, array $targetData, Collection $targetKeyValue): array
    {
        $targetContent = [];
        $addedKeys = [];

        foreach ($sourceData as $lineNumber => $data) {
            if ($data->isEmpty) {
                $targetContent[] = '';

                continue;
            }

            if ($data->isComment) {
                $this->warnCommentDiff($lineNumber, $data, $targetData);
                $targetContent[] = $data->raw;

                continue;
            }

            $key = (string) $data->key;
            $existingValue = $targetKeyValue->get($key)?->value;

            if ($existingValue === null) {
                $addedKeys[] = $key;
            }

            $this->warnKeyDiff($lineNumber, $data, $targetData);

            unset($targetKeyValue[$data->key]);

            $targetContent[] = "{$key}=" . ($existingValue ?? $data->value);
        }

        $removedKeys = array_values($targetKeyValue->keys()->all());

        if ($this->output->isVerbose() && $targetKeyValue->isNotEmpty()) {
            $this->warn('Additional keys found in target file that are not present in source file: ' . $targetKeyValue->keys()->implode(', '));
        }

        return ['content' => $targetContent, 'addedKeys' => $addedKeys, 'removedKeys' => $removedKeys];
    }

    /**
     * @param  array<int, EnvLine>  $targetData
     */
    private function warnCommentDiff(int $lineNumber, EnvLine $source, array $targetData): void
    {
        if (!$this->output->isVerbose()) {
            return;
        }

        if (isset($targetData[$lineNumber]) && $targetData[$lineNumber]->raw === $source->raw) {
            return;
        }

        $this->warn(sprintf(
            <<<'STR'
                Comment differs at line %d:
                    Source: %s
                    Target: %s
                STR,
            $lineNumber,
            $source->raw,
            $targetData[$lineNumber]->raw ?? 'N/A'
        ));
    }

    /**
     * @param  array<int, EnvLine>  $targetData
     */
    private function warnKeyDiff(int $lineNumber, EnvLine $source, array $targetData): void
    {
        if (!$this->output->isVerbose()) {
            return;
        }

        if (isset($targetData[$lineNumber]) && $targetData[$lineNumber]->key === $source->key) {
            return;
        }

        $this->warn(sprintf(
            <<<'STR'
                Key differs at line %d:
                    Source: %s=%s
                    Target: %s
                STR,
            $lineNumber,
            $source->key,
            $source->value,
            $targetData[$lineNumber]->key ?? 'N/A'
        ));
    }

    /**
     * @param  list<string>  $addedKeys
     * @param  list<string>  $removedKeys
     */
    private function reportDryRun(array $addedKeys, array $removedKeys): void
    {
        if (count($addedKeys) > 0) {
            $this->line('  Keys to add from source: ' . implode(', ', $addedKeys));
        }

        if (count($removedKeys) > 0) {
            $this->line('  Keys to remove (not in source): ' . implode(', ', $removedKeys));
        }

        if (count($addedKeys) === 0 && count($removedKeys) === 0) {
            $this->line('  No changes needed.');
        }
    }
}
