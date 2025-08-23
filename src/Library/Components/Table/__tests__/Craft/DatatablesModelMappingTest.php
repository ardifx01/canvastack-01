<?php
/**
 * Datatables Model Mapping Test
 * 
 * This test verifies that the model mapping in tryCreateSpecificModel works
 * correctly for both temp tables and regular tables.
 * 
 * @category Table System Tests
 * @package  Incodiy\Codiy\Library\Components\Table\Craft
 * @author   Incodiy Team
 * @since    v2.3.1
 */

require_once 'd:\worksites\incodiy\mantra.smartfren.dev\vendor\autoload.php';

use Incodiy\Codiy\Library\Components\Table\Craft\Datatables;

// Bootstrap Laravel
$app = require_once 'd:\worksites\incodiy\mantra.smartfren.dev\bootstrap\app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 Datatables Model Mapping Test\n";
echo "=" . str_repeat("=", 50) . "\n\n";

echo "📋 Purpose: Test model mapping logic in tryCreateSpecificModel\n";
echo "📋 Focus: Temp tables vs Regular tables handling\n";
echo "📋 Version: v2.3.1\n\n";

try {
    $datatables = new Datatables();
    $reflection = new ReflectionClass($datatables);
    $method = $reflection->getMethod('tryCreateSpecificModel');
    $method->setAccessible(true);
    
    // Test cases for different table types
    $testCases = [
        // Temp tables (should use Query Builder)
        'temp_user_never_login' => [
            'expected_type' => 'Illuminate\Database\Query\Builder',
            'description' => 'Temp table - should use Query Builder'
        ],
        'temp_montly_activity' => [
            'expected_type' => 'Illuminate\Database\Query\Builder',
            'description' => 'Temp table - should use Query Builder'
        ],
        
        // Regular tables (should use Eloquent Builder)
        'users' => [
            'expected_type' => 'Illuminate\Database\Eloquent\Builder',
            'description' => 'Regular table - should use Eloquent Builder'
        ],
        'base_group' => [
            'expected_type' => 'Illuminate\Database\Eloquent\Builder',
            'description' => 'Regular table - should use Eloquent Builder'
        ],
        
        // Non-mapped tables (should return null)
        'unknown_table' => [
            'expected_type' => null,
            'description' => 'Unknown table - should return null'
        ]
    ];
    
    $passedTests = 0;
    $totalTests = count($testCases);
    
    foreach ($testCases as $tableName => $testCase) {
        echo "📋 Testing: {$tableName}\n";
        echo "   Expected: {$testCase['description']}\n";
        
        $result = $method->invoke($datatables, $tableName);
        
        if ($testCase['expected_type'] === null) {
            if ($result === null) {
                echo "   ✅ PASS: Correctly returned null\n";
                $passedTests++;
            } else {
                echo "   ❌ FAIL: Expected null, got " . get_class($result) . "\n";
            }
        } else {
            if ($result && $result instanceof $testCase['expected_type']) {
                echo "   ✅ PASS: Correct type - " . get_class($result) . "\n";
                
                // Additional checks for Query Builder (temp tables)
                if ($result instanceof \Illuminate\Database\Query\Builder) {
                    $connection = $result->getConnection();
                    if ($connection) {
                        echo "   ✅ PASS: Has valid connection - " . get_class($connection) . "\n";
                    } else {
                        echo "   ❌ FAIL: No database connection\n";
                        continue;
                    }
                }
                
                $passedTests++;
            } else {
                $actualType = $result ? get_class($result) : 'null';
                echo "   ❌ FAIL: Expected {$testCase['expected_type']}, got {$actualType}\n";
            }
        }
        
        echo "\n";
    }
    
    echo "📊 Test Results Summary:\n";
    echo "- Total tests: {$totalTests}\n";
    echo "- Passed: {$passedTests}\n";
    echo "- Failed: " . ($totalTests - $passedTests) . "\n";
    
    if ($passedTests === $totalTests) {
        echo "🎉 ALL TESTS PASSED!\n";
    } else {
        echo "⚠️  Some tests failed - review model mapping logic\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n🏁 Model mapping test completed!\n";
echo "=" . str_repeat("=", 50) . "\n";