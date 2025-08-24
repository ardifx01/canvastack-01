<?php

namespace Incodiy\Codiy\Library\Components\Table\Support;

/**
 * Centralized configuration access for Table System
 */
final class TableConfig
{
    /**
     * Safe config getter with graceful fallback
     */
    public static function get(string $key, $default = null)
    {
        try {
            if (function_exists('config')) {
                return config($key, $default);
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return $default;
    }

    public static function debugEnabled(): bool
    {
        return (bool) self::get('datatables.debug', false);
    }

    public static function imageExtensions(): array
    {
        return (array) self::get('datatables.image_extensions', ['jpg', 'jpeg', 'png', 'gif']);
    }

    public static function defaultPagination(): array
    {
        return (array) self::get('datatables.default_pagination', [
            'start'  => 0,
            'length' => 10,
            'total'  => 0,
        ]);
    }

    public static function defaultActions(): array
    {
        return (array) self::get('datatables.default_actions', ['view', 'insert', 'edit', 'delete']);
    }

    public static function blacklistedFields(): array
    {
        return (array) self::get('datatables.blacklisted_fields', ['password', 'action', 'no']);
    }

    public static function reservedParameters(): array
    {
        return (array) self::get('datatables.reserved_parameters', [
            'renderDataTables', 'draw', 'columns', 'order', 'start', 'length', 'search', 'difta', '_token', '_'
        ]);
    }
}