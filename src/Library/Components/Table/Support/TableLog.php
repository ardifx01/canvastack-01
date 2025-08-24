<?php

namespace Incodiy\Codiy\Library\Components\Table\Support;

use Illuminate\Support\Facades\Log;

/**
 * Thin logging wrapper with built-in debug guard for Table System
 */
final class TableLog
{
    private static function shouldLog(): bool
    {
        return TableConfig::debugEnabled();
    }

    public static function debug(string $message, array $context = []): void
    {
        if (self::shouldLog()) {
            Log::debug($message, $context);
        }
    }

    public static function info(string $message, array $context = []): void
    {
        if (self::shouldLog()) {
            Log::info($message, $context);
        }
    }

    public static function warning(string $message, array $context = []): void
    {
        if (self::shouldLog()) {
            Log::warning($message, $context);
        }
    }

    public static function error(string $message, array $context = []): void
    {
        // errors sebaiknya tetap tercatat walau debug off
        Log::error($message, $context);
    }
}