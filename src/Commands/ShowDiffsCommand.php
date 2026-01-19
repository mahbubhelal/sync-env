<?php

declare(strict_types=1);

namespace Mahbub\SyncEnv\Commands;

use Dotenv\Dotenv;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * @phpstan-type envLineData array{line_number: int, key: ?string, value: ?string, raw: string, is_comment: bool, is_empty: bool}
 */
final class ShowDiffsCommand extends Command
{
    protected $signature = 'sync-env:show-diffs
                            {--a|all : Show all keys including identical ones}
                            {--b|include-backup : Include backup files in diff view}
                            {--only= : Only show these env files (comma-separated, e.g., .env,.env.staging)}
                            {--exclude= : Exclude these env files (comma-separated, e.g., .env.testing)}';

    protected $description = 'Show differences between .env.example and other .env files.';

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
        if (App::environment('workbench')) {
            App::setBasePath((string) getcwd()); // @codeCoverageIgnore
        }

        $exampleEnvPath = base_path('.env.example');
        $baseEnvPath = base_path('.env');

        if (!File::exists($exampleEnvPath)) {
            throw new Exception('The .env.example file does not exist in: ' . base_path());
        }

        if (!File::exists($baseEnvPath)) {
            throw new Exception('The .env file does not exist in: ' . base_path());
        }

        /** @var \Illuminate\Support\Collection<int, string> */
        $allEnvFilePaths = collect(File::glob(base_path('.env*')));

        if ($this->option('include-backup') !== true) {
            $allEnvFilePaths = $allEnvFilePaths->reject(fn ($path): bool => (bool) Str::match('/^\.env.*?.backup\./', basename($path)));
        }

        $allEnvFilePaths = $this->filterEnvFiles($allEnvFilePaths, $exampleEnvPath);

        if ($allEnvFilePaths->count() < 2) {
            throw new Exception('At least 2 env files are required to show differences. Found: ' . $allEnvFilePaths->map(fn ($path): string => basename($path))->implode(', '));
        }

        $sourceData = $this->parseEnvFile($exampleEnvPath, true);

        $this->checkForInvalidKeys($sourceData);
        $this->checkForDuplicateKeys($sourceData);
        $this->checkForInvalidValues($sourceData);

        /** @var array<string, envLineData> */
        $sourceDataKeyed = collect($sourceData)
            ->reject(fn ($item): bool => $item['is_comment'] || $item['is_empty'] || $item['key'] === null || $item['key'] === '')
            ->keyBy('key')
            ->toArray();

        $includeExample = $allEnvFilePaths->contains($exampleEnvPath);
        $envFilesPathsToProcess = $allEnvFilePaths->reject(fn ($path): bool => $path === $exampleEnvPath);

        $keys = array_keys($sourceDataKeyed);
        $processedEnvs = [];

        foreach ($envFilesPathsToProcess as $envFilePath) {
            $targetData = $this->parseEnvFile($envFilePath);
            $targetKeyValue = collect($targetData)->keyBy('key');

            $processedEnvs[basename($envFilePath)] = $targetKeyValue;
        }

        $headerRow = ['Key'];
        if ($includeExample) {
            $headerRow[] = '.env.example Value';
        }
        foreach (array_keys($processedEnvs) as $envFileName) {
            $headerRow[] = $envFileName . ' Value';
        }

        $rows = [];
        foreach ($keys as $key) {
            $column = [];
            $column[] = $key;

            $sourceValue = $sourceDataKeyed[$key]['value'];
            if ($includeExample) {
                $column[] = $sourceValue;
            }

            $targetValues = [];
            foreach ($processedEnvs as $targetKeyValue) {
                $targetValue = isset($targetKeyValue[$key]) ? $targetKeyValue[$key]['value'] : 'N/A';
                $column[] = $targetValue;
                $targetValues[] = $targetValue;
            }

            $isDifferent = false;
            if ($includeExample) {
                foreach ($targetValues as $targetValue) {
                    if ($targetValue !== 'N/A' && $sourceValue !== $targetValue) {
                        $isDifferent = true;
                        break;
                    }
                }
            } else {
                $uniqueValues = array_unique(array_filter($targetValues, fn ($v): bool => $v !== 'N/A'));
                $isDifferent = count($uniqueValues) > 1;
            }

            if ($this->option('all') === true || $isDifferent) {
                $rows[] = $column;
            }
        }

        $this->table($headerRow, $rows);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, string>  $allEnvFilePaths
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function filterEnvFiles(\Illuminate\Support\Collection $allEnvFilePaths, string $exampleEnvPath): \Illuminate\Support\Collection
    {
        /** @var ?string */
        $only = $this->option('only');
        /** @var ?string */
        $exclude = $this->option('exclude');

        if ($only !== null) {
            $onlyFiles = array_map('trim', explode(',', $only));

            $allEnvFilePaths = $allEnvFilePaths->filter(fn ($path): bool => in_array(basename($path), $onlyFiles, true));
        }

        if ($exclude !== null) {
            $excludeFiles = array_map('trim', explode(',', $exclude));

            $allEnvFilePaths = $allEnvFilePaths->reject(fn ($path): bool => in_array(basename($path), $excludeFiles, true));
        }

        if ($allEnvFilePaths->count() < 2 && !$allEnvFilePaths->contains($exampleEnvPath)) {
            $allEnvFilePaths->push($exampleEnvPath);
        }

        return $allEnvFilePaths;
    }

    /**
     * @return envLineData[]
     */
    private function parseEnvFile(string $path, bool $checkForLeadingTrailingSpaces = false): array
    {
        $content = File::get($path);
        $lines = preg_split("/(\r\n|\n|\r)/", $content);

        if ($lines === false) {
            return []; // @codeCoverageIgnore
        }

        $lineNumber = 1;
        $lineData = [];

        foreach ($lines as $line) {
            if ($checkForLeadingTrailingSpaces && (Str::startsWith($line, ' ') || Str::endsWith($line, ' '))) {
                throw new Exception("Invalid entry found in line {$lineNumber}: {$line}. Leading or trailing spaces are not allowed.");
            }

            $key = null;
            $value = null;
            $isComment = str_starts_with(trim($line), '#');

            if (!$isComment && str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
            }

            $lineData[$lineNumber] = [
                'line_number' => $lineNumber,
                'key' => $key,
                'value' => $value,
                'raw' => $line,
                'is_comment' => $isComment,
                'is_empty' => $line === '',
            ];

            $lineNumber++;
        }

        return $lineData;
    }

    /**
     * @param  envLineData[]  $data
     */
    private function checkForInvalidKeys(array $data): void
    {
        foreach ($data as $lineNumber => $entry) {
            if ($entry['is_empty']) {
                continue;
            }

            if ($entry['is_comment']) {
                continue;
            }

            $key = (string) $entry['key'];

            if ($key === '' || preg_match('/^[A-Z][A-Z0-9_]+$/', $key) !== 1) {
                throw new Exception("Invalid key found in line {$lineNumber}: {$key}. Keys must start with an uppercase letter and contain only uppercase letters, numbers, and underscores.");
            }
        }
    }

    /**
     * @param  envLineData[]  $data
     */
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

            $key = (string) $entry['key'];

            if (isset($keys[$key])) {
                throw new Exception("Duplicate key found in line {$keys[$key]} and {$lineNumber}: {$key}");
            }

            $keys[$key] = $lineNumber;
        }
    }

    /**
     * @param  envLineData[]  $data
     */
    private function checkForInvalidValues(array $data): void
    {
        foreach ($data as $lineNumber => $entry) {
            if ($entry['is_empty']) {
                continue;
            }

            if ($entry['is_comment']) {
                continue;
            }

            $value = (string) $entry['value'];

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
