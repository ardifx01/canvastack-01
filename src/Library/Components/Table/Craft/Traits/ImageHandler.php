<?php

namespace Incodiy\Codiy\Library\Components\Table\Craft\Traits;

/**
 * ImageHandler
 * - Detect image-like fields and provide basic processing hooks
 */
trait ImageHandler
{
    protected function detectImageFields(array $columns, array $extensions = ['jpg','jpeg','png','gif']): array
    {
        $imgCols = [];
        foreach ($columns as $name) {
            if (!is_string($name)) { continue; }
            // simple heuristic
            if (preg_match('/(image|img|photo|avatar|logo|pic)/i', $name)) {
                $imgCols[] = $name;
            }
        }
        return $imgCols;
    }

    protected function isValidImagePath(string $path, array $extensions = ['jpg','jpeg','png','gif']): bool
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($ext, $extensions, true);
    }
}