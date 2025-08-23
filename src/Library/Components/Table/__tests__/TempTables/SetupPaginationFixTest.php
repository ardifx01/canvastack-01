<?php
/**
 * Setup Pagination Fix Test
 * 
 * This test verifies that setupPagination no longer throws "prepare() on null" 
 * errors when working with temp tables after our fix.
 * 
 * @category Table System Tests
 * @package  Incodiy\Codiy\Library\Components\Table
 * @author   Incodiy Team
 * @since    v2.3.1
 */

require_once 'd:\worksites\incodiy\mantra.smartfren.dev\vendor\autoload.php';

use Incodiy\Codiy\Models\Admin\System\UserActivity;
use Incodiy\Codiy\Library\Components\Table\Craft\Datatables;

// Bootstrap Laravel
$app = require_once 'd:\worksites\incodiy\mantra.smartfren.dev\bootstrap\app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 Setup Pagination Fix Test\n";
echo "=" . str_repeat("=", 50) . "\n\n";

echo "📋 Purpose: Verify setupPagination works with temp tables\n";
echo "📋 Critical Fix: No more 'prepare() on null' errors\n";
echo "📋 Version: v2.3.1\n\n";

try {
    // Step 1: Create temp table
    echo "📋 Creating temp table...\n";
    $userActivity = new UserActivity();
    $userActivity->user_never_login();
    echo "✅ Temp table created\n\n";
    
    // Step 2: Test the exact scenario that was failing
    echo "📋 Testing setupPagination scenario...\n";
    
    $datatables = new Datatables();
    $reflection = new ReflectionClass($datatables);
    
    // Get the fixed tryCreateSpecificModel
    $tryCreateMethod = $reflection->getMethod('tryCreateSpecificModel');
    $tryCreateMethod->setAccessible(true);
    
    // Get setupPagination method
    $setupPaginationMethod = $reflection->getMethod('setupPagination');
    $setupPaginationMethod->setAccessible(true);
    
    echo "🔍 Creating model for temp_user_never_login...\n";
    $modelData = $tryCreateMethod->invoke($datatables, 'temp_user_never_login');
    
    if (!$modelData) {
        echo "❌ No model created\n";
        exit(1);
    }
    
    echo "✅ Model created: " . get_class($modelData) . "\n";
    
    // Test connection
    $connection = $modelData->getConnection();
    echo "✅ Connection: " . ($connection ? get_class($connection) : 'NULL') . "\n";
    
    // This is the critical test - setupPagination calling count()
    echo "🔍 Testing setupPagination (this was failing before)...\n";
    
    $paginationConfig = $setupPaginationMethod->invoke($datatables, $modelData);
    
    echo "✅ setupPagination completed successfully!\n";
    echo "📊 Total records: " . $paginationConfig['total'] . "\n";
    echo "📊 Start: " . $paginationConfig['start'] . "\n";
    echo "📊 Length: " . $paginationConfig['length'] . "\n";
    
    echo "\n🎉 SUCCESS: No more 'prepare() on null' errors!\n";
    
    echo "\n📊 Test Results Summary:\n";
    echo "- Model creation: ✅ Query Builder with valid connection\n";
    echo "- Count operation: ✅ {$paginationConfig['total']} records\n";
    echo "- Pagination setup: ✅ No errors\n";
    echo "- Critical fix verified: ✅ No 'prepare() on null'\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    
    if (strpos($e->getMessage(), 'prepare') !== false) {
        echo "\n💥 CRITICAL: Still getting prepare() on null error!\n";
        echo "🔍 This means our fix didn't work completely.\n";
    }
}

echo "\n🏁 Verification completed!\n";
echo "=" . str_repeat("=", 50) . "\n";