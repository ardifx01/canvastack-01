<?php
namespace Incodiy\Codiy\Library\Components\Table\Craft;

final class DatatableRuntime {
    // In-memory registry with session fallback
    private static $runtime = [];

    // Trait instances and contexts per table for modular architecture
    private static array $traitInstances = [];
    private static array $traitContext = [];
    private static array $traitMetrics = [];

    /**
     * Prepare a lightweight, serializable snapshot of context
     * - Replace Eloquent model objects with their class names
     */
    private static function snapshot($context) {
        try {
            $copy = clone $context; // shallow clone
        } catch (\Throwable $e) {
            $copy = $context; // fallback
        }

        // Ensure expected structure exists
        if (!isset($copy->datatables) || !is_object($copy->datatables)) {
            return $copy;
        }

        if (isset($copy->datatables->model) && is_array($copy->datatables->model)) {
            foreach ($copy->datatables->model as $key => $info) {
                if (is_array($info) && ($info['type'] ?? null) === 'model') {
                    // Replace object with class name
                    if (isset($info['source']) && is_object($info['source'])) {
                        $class = get_class($info['source']);
                        $copy->datatables->model[$key]['source_class'] = $class;
                        $copy->datatables->model[$key]['source'] = null; // drop unserializable object
                    }
                }
            }
        }
        return $copy;
    }

    /**
     * Rehydrate snapshot into a usable context
     */
    private static function rehydrate($context) {
        if (!isset($context->datatables) || !is_object($context->datatables)) {
            return $context;
        }

        if (isset($context->datatables->model) && is_array($context->datatables->model)) {
            foreach ($context->datatables->model as $key => $info) {
                if (is_array($info) && ($info['type'] ?? null) === 'model') {
                    if (!isset($info['source']) || !is_object($info['source'])) {
                        $cls = $info['source_class'] ?? null;
                        if (is_string($cls) && class_exists($cls)) {
                            try {
                                $context->datatables->model[$key]['source'] = new $cls();
                            } catch (\Throwable $e) {
                                // leave as null if cannot instantiate
                                $context->datatables->model[$key]['source'] = null;
                            }
                        }
                    }
                }
            }
        }
        return $context;
    }

    /**
     * Store runtime context in memory and session for cross-request availability
     */
    public static function set(string $tableName, $context): void {
        if (!empty($tableName) && is_object($context)) {
            // Keep full context in memory for current request
            self::$runtime[$tableName] = $context;

            // Persist lightweight snapshot to session for next requests
            try {
                if (function_exists('session')) {
                    $snapshot = self::snapshot($context);
                    session()->put("datatable_runtime." . $tableName, serialize($snapshot));
                }
            } catch (\Throwable $e) {
                // Ignore session persistence failures silently
            }
        }
    }

    /**
     * Retrieve runtime context; prefer memory, fallback to session
     */
    public static function get(string $tableName) {
        if (isset(self::$runtime[$tableName])) {
            return self::$runtime[$tableName];
        }
        try {
            if (function_exists('session')) {
                $stored = session()->get("datatable_runtime." . $tableName);
                if (!empty($stored)) {
                    $snapshot = @unserialize($stored);
                    if (is_object($snapshot)) {
                        $context = self::rehydrate($snapshot);
                        // Cache in memory for this request
                        self::$runtime[$tableName] = $context;
                        return $context;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Ignore session retrieval failures
        }
        return null;
    }

    // ===== Trait Context & Metrics (Phase 1) =====

    public static function loadTraitInstance(string $tableName, string $class): object {
        if (!isset(self::$traitInstances[$tableName])) {
            self::$traitInstances[$tableName] = [];
        }
        if (!isset(self::$traitInstances[$tableName][$class])) {
            self::$traitInstances[$tableName][$class] = new $class();
        }
        return self::$traitInstances[$tableName][$class];
    }

    public static function setTraitContext(string $tableName, string $trait, array $ctx): void {
        if (!isset(self::$traitContext[$tableName])) {
            self::$traitContext[$tableName] = [];
        }
        self::$traitContext[$tableName][$trait] = $ctx;
    }

    public static function getTraitContext(string $tableName, string $trait): ?array {
        return self::$traitContext[$tableName][$trait] ?? null;
    }

    public static function startTimer(string $tableName, string $trait): void {
        if (!isset(self::$traitMetrics[$tableName])) {
            self::$traitMetrics[$tableName] = [];
        }
        self::$traitMetrics[$tableName][$trait]['start'] = microtime(true);
    }

    public static function endTimer(string $tableName, string $trait): void {
        $start = self::$traitMetrics[$tableName][$trait]['start'] ?? null;
        if ($start) {
            $elapsed = microtime(true) - $start;
            self::$traitMetrics[$tableName][$trait]['time'] = ($elapsed);
            unset(self::$traitMetrics[$tableName][$trait]['start']);
        }
    }

    public static function metrics(string $tableName): array {
        return self::$traitMetrics[$tableName] ?? [];
    }
}