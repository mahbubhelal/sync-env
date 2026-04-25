<?php

declare(strict_types=1);

namespace Mahbub\SyncEnv;

final readonly class EnvLine
{
    public function __construct(
        public int $lineNumber,
        public ?string $key,
        public ?string $value,
        public string $raw,
        public bool $isComment,
        public bool $isEmpty,
    ) {}

    public function isKeyValue(): bool
    {
        return !$this->isEmpty && !$this->isComment;
    }
}
