<?php
/**
 * Single Test Runner
 * 
 * Runs individual tests for the Table System.
 * 
 * @category Table System Tests
 * @package  Incodiy\Codiy\Library\Components\Table
 * @author   Incodiy Team
 * @since    v2.3.1
 */

if ($argc < 2) {
    echo "Usage: php run_single_test.php <test_file>\n";
    echo "Example: php run_single_test.php Relations/UserRelationTest.php\n";
    exit(1);
}

$testFile = $argv[1];
$fullPath = __DIR__ . '/' . $testFile;

echo "🧪 Single Test Runner\n";
echo "=" . str_repeat("=", 50) . "\n\n";

echo "📋 Running: {$testFile}\n";
echo "📋 Path: {$fullPath}\n\n";

if (!file_exists($fullPath)) {
    echo "❌ Test file not found: {$fullPath}\n";
    exit(1);
}

echo "🚀 Executing test...\n";
echo str_repeat("-", 50) . "\n\n";

// Execute the test file
include $fullPath;

echo "\n" . str_repeat("-", 50) . "\n";
echo "🏁 Test execution completed!\n";