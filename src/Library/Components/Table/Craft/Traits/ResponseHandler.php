<?php

namespace Incodiy\Codiy\Library\Components\Table\Craft\Traits;

/**
 * ResponseHandler
 * - Formatting helpers (kept minimal to avoid coupling)
 */
trait ResponseHandler
{
    protected function formatSuccessResponse(array $payload, int $status = 200)
    {
        if (function_exists('response')) { return response()->json($payload, $status); }
        return $payload;
    }

    protected function formatErrorResponse(string $message, array $meta = [], int $status = 400)
    {
        $payload = ['error' => $message, 'meta' => $meta];
        if (function_exists('response')) { return response()->json($payload, $status); }
        return $payload;
    }
}