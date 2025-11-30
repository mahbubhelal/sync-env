<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use RectorLaravel\Set\LaravelLevelSetList;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        __DIR__ . '/bootstrap/cache',
    ])
    ->withSets([
        LevelSetList::UP_TO_PHP_85,
        SetList::PRIVATIZATION,
        SetList::EARLY_RETURN,
        SetList::TYPE_DECLARATION,
        SetList::DEAD_CODE,
        SetList::CODE_QUALITY,
        SetList::RECTOR_PRESET,
        LaravelLevelSetList::UP_TO_LARAVEL_120,
        LaravelSetList::LARAVEL_CODE_QUALITY,
    ]);
