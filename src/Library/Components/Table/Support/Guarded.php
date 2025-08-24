<?php

namespace Incodiy\Codiy\Library\Components\Table\Support;

use Throwable;

/**
 * Simple guarded executor to centralize try/catch + logging + fallback handling
 */
final class Guarded
{
    /**
     * Execute a callable safely. On failure, log and run optional onError, then return fallback.
     *
     * @param string        $label     Context label for logs
     * @param callable      $fn        Main execution
     * @param callable|null $onError   Optional callback(Throwable $e)
     * @param mixed         $fallback  Value or callable(Throwable $e) to return when failed
     * @return mixed
     */
    public static function run(string $label, callable $fn, ?callable $onError = null, $fallback = null)
    {
        try {
            return $fn();
        } catch (Throwable $e) {
            // Log a concise message (stack trace omitted to keep logs readable unless debug needed)
            TableLog::warning("Guarded run failed: {$label}", [
                'exception' => get_class($e),
                'message'   => $e->getMessage(),
            ]);

            if ($onError) {
                try {
                    $onError($e);
                } catch (Throwable $ignored) {
                    // swallow any onError exceptions
                }
            }

            if (is_callable($fallback)) {
                try {
                    return $fallback($e);
                } catch (Throwable $ignored) {
                    return null;
                }
            }

            return $fallback;
        }
    }
}