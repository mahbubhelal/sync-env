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

final class ShowDiffsCommand extends Command
{
    use ResolvesBasePath;

    protected $signature = 'sync-env:show-diffs
                            {--a|all : Show all keys including identical ones}
                            {--b|include-backup : Include backup files in diff view}
                            {--only= : Only show these env files (comma-separated, e.g., .env,.env.staging)}
                            {--exclude= : Exclude these env files (comma-separated, e.g., .env.testing)}';

    protected $description = 'Show differences between .env.example and other .env files.';

    public function handle(EnvFileParser $parser): int
    {
        try {
            $this->process($parser);

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
            throw new Exception('The .env file does not exist in: ' . base_path());
        }

        /** @var Collection<int, string> */
        $allEnvFilePaths = collect(File::glob(base_path('.env*')));

        if ($this->option('include-backup') !== true) {
            $allEnvFilePaths = $allEnvFilePaths->reject(fn ($path): bool => (bool) Str::match('/^\.env.*.backup\./', basename($path)));
        }

        $allEnvFilePaths = $this->filterEnvFiles($allEnvFilePaths, $exampleEnvPath);

        if ($allEnvFilePaths->count() < 2) {
            throw new Exception('At least 2 env files are required to show differences. Found: ' . $allEnvFilePaths->map(fn ($path): string => basename($path))->implode(', '));
        }

        $sourceData = $parser->parseSource($exampleEnvPath);

        /** @var array<string, EnvLine> */
        $sourceDataKeyed = collect($sourceData)
            ->reject(fn (EnvLine $item): bool => !$item->isKeyValue() || $item->key === null || $item->key === '')
            ->keyBy('key')
            ->toArray();

        $includeExample = $allEnvFilePaths->contains($exampleEnvPath);
        $envFilesPathsToProcess = $allEnvFilePaths->reject(fn ($path): bool => $path === $exampleEnvPath);

        /** @var array<string, Collection<string, EnvLine>> */
        $processedEnvs = $this->parseTargetFiles($envFilesPathsToProcess, $parser);

        $headerRow = $this->buildHeaderRow($includeExample, $processedEnvs);
        $rows = $this->buildDiffRows($sourceDataKeyed, $processedEnvs, $includeExample);

        $this->table($headerRow, $rows);
    }

    /**
     * @param  Collection<int, string>  $envFilePaths
     * @return array<string, Collection<string, EnvLine>>
     */
    private function parseTargetFiles(Collection $envFilePaths, EnvFileParser $parser): array
    {
        $processedEnvs = [];

        foreach ($envFilePaths as $envFilePath) {
            $targetData = $parser->parse($envFilePath);
            $targetKeyValue = collect($targetData)
                ->reject(fn (EnvLine $item): bool => !$item->isKeyValue())
                ->keyBy('key');

            $processedEnvs[basename($envFilePath)] = $targetKeyValue;
        }

        return $processedEnvs;
    }

    /**
     * @param  array<string, Collection<string, EnvLine>>  $processedEnvs
     * @return list<string>
     */
    private function buildHeaderRow(bool $includeExample, array $processedEnvs): array
    {
        $headerRow = ['#', 'Key'];

        if ($includeExample) {
            $headerRow[] = '.env.example Value';
        }

        foreach (array_keys($processedEnvs) as $envFileName) {
            $headerRow[] = $envFileName . ' Value';
        }

        return $headerRow;
    }

    /**
     * @param  array<string, EnvLine>  $sourceDataKeyed
     * @param  array<string, Collection<string, EnvLine>>  $processedEnvs
     * @return list<list<string|int|null>>
     */
    private function buildDiffRows(array $sourceDataKeyed, array $processedEnvs, bool $includeExample): array
    {
        $sourceKeys = array_keys($sourceDataKeyed);
        $showAll = $this->option('all') === true;
        $rows = [];

        foreach ($sourceKeys as $key) {
            $sourceValue = $sourceDataKeyed[$key]->value;
            $targetValues = $this->collectTargetValues($key, $processedEnvs);

            if (!$showAll && !$this->hasDifferences($sourceValue, $targetValues, $includeExample)) {
                continue;
            }

            $rows[] = $this->buildRow($sourceDataKeyed[$key]->lineNumber, $key, $sourceValue, $targetValues, $includeExample);
        }

        foreach ($this->findExtraKeys($processedEnvs, $sourceKeys) as $extraKey) {
            $targetValues = $this->collectTargetValues($extraKey, $processedEnvs);

            if (!$showAll && !$this->hasDifferences(null, $targetValues, $includeExample)) {
                continue;
            }

            $rows[] = $this->buildRow('-', $extraKey, null, $targetValues, $includeExample);
        }

        return $rows;
    }

    /**
     * @param  array<string, Collection<string, EnvLine>>  $processedEnvs
     * @return list<string|null>
     */
    private function collectTargetValues(string $key, array $processedEnvs): array
    {
        $values = [];

        foreach ($processedEnvs as $targetKeyValue) {
            $values[] = $targetKeyValue->has($key) ? $targetKeyValue->get($key)?->value : 'N/A';
        }

        return $values;
    }

    /**
     * @param  list<string|null>  $targetValues
     */
    private function hasDifferences(?string $sourceValue, array $targetValues, bool $includeExample): bool
    {
        if ($includeExample) {
            foreach ($targetValues as $targetValue) {
                if ($sourceValue !== $targetValue) {
                    return true;
                }
            }

            return false;
        }

        return count(array_unique($targetValues)) > 1;
    }

    /**
     * @param  list<string|null>  $targetValues
     * @return list<string|int|null>
     */
    private function buildRow(string|int $lineNumber, string $key, ?string $sourceValue, array $targetValues, bool $includeExample): array
    {
        $column = [$lineNumber, $key];

        if ($includeExample) {
            $column[] = $sourceValue ?? 'N/A';
        }

        return [...$column, ...$targetValues];
    }

    /**
     * @param  array<string, Collection<string, EnvLine>>  $processedEnvs
     * @param  list<string>  $sourceKeys
     * @return list<string>
     */
    private function findExtraKeys(array $processedEnvs, array $sourceKeys): array
    {
        /** @var array<string, true> */
        $allTargetKeysMap = [];

        foreach ($processedEnvs as $targetKeyValue) {
            foreach ($targetKeyValue->keys() as $targetKey) {
                $allTargetKeysMap[$targetKey] = true;
            }
        }

        return array_values(array_diff(array_keys($allTargetKeysMap), $sourceKeys));
    }

    /**
     * @param  Collection<int, string>  $allEnvFilePaths
     * @return Collection<int, string>
     */
    private function filterEnvFiles(Collection $allEnvFilePaths, string $exampleEnvPath): Collection
    {
        /** @var ?string */
        $only = $this->option('only');
        /** @var ?string */
        $exclude = $this->option('exclude');

        if ($only !== null) {
            $onlyFiles = array_map(trim(...), explode(',', $only));

            $allEnvFilePaths = $allEnvFilePaths->filter(fn ($path): bool => in_array(basename($path), $onlyFiles, true));
        }

        if ($exclude !== null) {
            $excludeFiles = array_map(trim(...), explode(',', $exclude));

            $allEnvFilePaths = $allEnvFilePaths->reject(fn ($path): bool => in_array(basename($path), $excludeFiles, true));
        }

        if ($allEnvFilePaths->count() < 2 && !$allEnvFilePaths->contains($exampleEnvPath)) {
            $allEnvFilePaths->push($exampleEnvPath);
        }

        return $allEnvFilePaths;
    }
}
