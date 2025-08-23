<?php
/**
 * Table System Test Runner
 * 
 * Runs all tests in the Table System test suite and provides a comprehensive report.
 * 
 * @category Table System Tests
 * @package  Incodiy\Codiy\Library\Components\Table
 * @author   Incodiy Team
 * @since    v2.3.1
 */

echo "🧪 Table System Test Suite Runner\n";
echo "=" . str_repeat("=", 60) . "\n\n";

echo "📋 Purpose: Run all Table System tests and generate report\n";
echo "📋 Version: v2.3.1\n";
echo "📋 Focus: Temp table fixes and DataTables processing\n\n";

// Define test files in execution order
$testFiles = [
    // Relations Tests (Foundation)
    'Relations/UserRelationTest.php' => 'User Relation Verification',
    
    // Temp Table Tests (Core fixes)
    'TempTables/TempTableModelCreationTest.php' => 'Temp Table Model Creation',
    'TempTables/SetupPaginationFixTest.php' => 'Setup Pagination Fix',
    
    // UserActivity Tests (Specific functionality)
    'UserActivity/UserActivityTempTableTest.php' => 'UserActivity Temp Tables',
    
    // Craft Tests (Component logic)
    'Craft/DatatablesModelMappingTest.php' => 'Model Mapping Logic',
    
    // Integration Tests (End-to-end)
    'Integration/FullDataTablesFlowTest.php' => 'Full DataTables Flow'
];

$results = [];
$totalTests = count($testFiles);
$passedTests = 0;

echo "🚀 Starting test execution...\n\n";

foreach ($testFiles as $testFile => $testName) {
    echo "📋 Running: {$testName}\n";
    echo "   File: {$testFile}\n";
    echo "   " . str_repeat("-", 50) . "\n";
    
    $fullPath = __DIR__ . '/' . $testFile;
    
    if (!file_exists($fullPath)) {
        echo "   ❌ SKIP: Test file not found\n\n";
        $results[$testName] = 'SKIP - File not found';
        continue;
    }
    
    // Capture output and execution
    ob_start();
    $startTime = microtime(true);
    
    try {
        // Execute test file
        include $fullPath;
        $output = ob_get_contents();
        $endTime = microtime(true);
        $executionTime = round(($endTime - $startTime) * 1000, 2);
        
        // Analyze output for success/failure indicators
        if (strpos($output, '❌') !== false || strpos($output, 'FAIL') !== false) {
            $results[$testName] = "FAIL ({$executionTime}ms)";
            echo "   ❌ FAILED in {$executionTime}ms\n";
        } elseif (strpos($output, '✅') !== false || strpos($output, 'SUCCESS') !== false) {
            $results[$testName] = "PASS ({$executionTime}ms)";
            $passedTests++;
            echo "   ✅ PASSED in {$executionTime}ms\n";
        } else {
            $results[$testName] = "UNKNOWN ({$executionTime}ms)";
            echo "   ❓ UNKNOWN RESULT in {$executionTime}ms\n";
        }
        
    } catch (Exception $e) {
        $endTime = microtime(true);
        $executionTime = round(($endTime - $startTime) * 1000, 2);
        $results[$testName] = "ERROR ({$executionTime}ms) - " . $e->getMessage();
        echo "   ❌ ERROR in {$executionTime}ms: " . $e->getMessage() . "\n";
    } catch (ParseError $e) {
        $endTime = microtime(true);
        $executionTime = round(($endTime - $startTime) * 1000, 2);
        $results[$testName] = "PARSE ERROR ({$executionTime}ms) - " . $e->getMessage();
        echo "   ❌ PARSE ERROR in {$executionTime}ms: " . $e->getMessage() . "\n";
    }
    
    ob_end_clean();
    echo "\n";
}

// Generate comprehensive report
echo "📊 TEST SUITE RESULTS SUMMARY\n";
echo "=" . str_repeat("=", 60) . "\n\n";

echo "📈 Overall Statistics:\n";
echo "- Total tests: {$totalTests}\n";
echo "- Passed: {$passedTests}\n";
echo "- Failed: " . ($totalTests - $passedTests) . "\n";
echo "- Success rate: " . round(($passedTests / $totalTests) * 100, 1) . "%\n\n";

echo "📋 Detailed Results:\n";
foreach ($results as $testName => $result) {
    $status = strpos($result, 'PASS') !== false ? '✅' : 
              (strpos($result, 'SKIP') !== false ? '⏭️' : '❌');
    echo "   {$status} {$testName}: {$result}\n";
}

echo "\n";

// Critical fix verification
echo "🔍 Critical Fix Verification (v2.3.1):\n";
$criticalTests = [
    'User Relation Verification' => isset($results['User Relation Verification']) ? $results['User Relation Verification'] : 'NOT RUN',
    'Temp Table Model Creation' => isset($results['Temp Table Model Creation']) ? $results['Temp Table Model Creation'] : 'NOT RUN',
    'Setup Pagination Fix' => isset($results['Setup Pagination Fix']) ? $results['Setup Pagination Fix'] : 'NOT RUN',
    'UserActivity Temp Tables' => isset($results['UserActivity Temp Tables']) ? $results['UserActivity Temp Tables'] : 'NOT RUN'
];

$criticalPassed = 0;
foreach ($criticalTests as $testName => $result) {
    $status = strpos($result, 'PASS') !== false ? '✅' : '❌';
    echo "   {$status} {$testName}: {$result}\n";
    if (strpos($result, 'PASS') !== false) $criticalPassed++;
}

echo "\n";

if ($criticalPassed === count($criticalTests)) {
    echo "🎉 CRITICAL FIXES VERIFIED: All temp table fixes are working!\n";
    echo "   - No more 'prepare() on null' errors\n";
    echo "   - Temp tables use Query Builder with valid connections\n";
    echo "   - Regular tables still use Eloquent Builder for relations\n";
} else {
    echo "⚠️  CRITICAL FIXES INCOMPLETE: Some temp table fixes may not be working\n";
    echo "   - Review failed tests above\n";
    echo "   - Check storage/logs/laravel.log for detailed error information\n";
}

echo "\n";

// Recommendations
echo "📋 Recommendations:\n";
if ($passedTests === $totalTests) {
    echo "   ✅ All tests passed - Table System is stable\n";
    echo "   ✅ Ready for production deployment\n";
    echo "   ✅ UserActivity page should work without crashes\n";
} else {
    echo "   ⚠️  Some tests failed - investigate before deployment\n";
    echo "   ⚠️  Check individual test outputs for specific issues\n";
    echo "   ⚠️  Review storage/logs/laravel.log for detailed debugging\n";
}

echo "\n🏁 Test suite execution completed!\n";
echo "=" . str_repeat("=", 60) . "\n";