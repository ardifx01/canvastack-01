<?php

namespace Incodiy\Codiy\Library\Components\Table\Craft\Traits;

/**
 * ImageProcessor
 * - HTML generator and basic validation for image columns
 */
trait ImageProcessor
{
    protected function generateImageHtml(?string $url, array $attrs = []): string
    {
        if (empty($url)) {
            return '';
        }
        $attrStr = '';
        foreach ($attrs as $k => $v) {
            $attrStr .= ' ' . htmlspecialchars($k) . '="' . htmlspecialchars((string)$v) . '"';
        }
        return '<img src="' . htmlspecialchars($url) . '"' . $attrStr . ' />';
    }

    protected function checkValidImage(?string $url): bool
    {
        if (empty($url)) { return false; }
        $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));
        return in_array($ext, ['jpg','jpeg','png','gif'], true);
    }
}