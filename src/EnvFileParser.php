<?php

declare(strict_types=1);

namespace Mahbub\SyncEnv;

use Dotenv\Dotenv;
use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class EnvFileParser
{
    /**
     * @return array<int, EnvLine>
     */
    public function parseSource(string $path): array
    {
        $data = $this->parse($path, checkForLeadingTrailingSpaces: true);

        $this->validateKeys($data);
        $this->validateDuplicateKeys($data);
        $this->validateValues($data);

        return $data;
    }

    /**
     * @return array<int, EnvLine>
     */
    public function parse(string $path, bool $checkForLeadingTrailingSpaces = false): array
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

            $lineData[$lineNumber] = new EnvLine(
                lineNumber: $lineNumber,
                key: $key,
                value: $value,
                raw: $line,
                isComment: $isComment,
                isEmpty: $line === '',
            );

            $lineNumber++;
        }

        return $lineData;
    }

    /**
     * @param  array<int, EnvLine>  $data
     */
    private function validateKeys(array $data): void
    {
        foreach ($data as $lineNumber => $entry) {
            if (!$entry->isKeyValue()) {
                continue;
            }
            $key = (string) $entry->key;

            if ($key === '' || preg_match('/^[A-Z][A-Z0-9_]+$/', $key) !== 1) {
                throw new Exception("Invalid key found in line {$lineNumber}: {$key}. Keys must start with an uppercase letter and contain only uppercase letters, numbers, and underscores.");
            }
        }
    }

    /**
     * @param  array<int, EnvLine>  $data
     */
    private function validateDuplicateKeys(array $data): void
    {
        $keys = [];

        foreach ($data as $lineNumber => $entry) {
            if (!$entry->isKeyValue()) {
                continue;
            }
            $key = (string) $entry->key;

            if (isset($keys[$key])) {
                throw new Exception("Duplicate key found in line {$keys[$key]} and {$lineNumber}: {$key}");
            }

            $keys[$key] = $lineNumber;
        }
    }

    /**
     * @param  array<int, EnvLine>  $data
     */
    private function validateValues(array $data): void
    {
        foreach ($data as $lineNumber => $entry) {
            if (!$entry->isKeyValue()) {
                continue;
            }
            $value = (string) $entry->value;

            if (Str::startsWith($value, ' ') || Str::endsWith($value, ' ')) {
                throw new Exception("Invalid value found in line {$lineNumber}: {$value}. Error: Leading or trailing spaces are not allowed.");
            }

            try {
                Dotenv::parse($entry->raw);
            } catch (Exception $e) {
                $message = str_replace('Failed to parse dotenv file. ', '', $e->getMessage());

                throw new Exception("Invalid value found in line {$lineNumber}: {$message}", $e->getCode(), $e);
            }
        }
    }
}
