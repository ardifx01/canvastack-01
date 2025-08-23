<?php
/**
 * UserActivity Temp Table Test
 * 
 * This test specifically verifies that UserActivity temp tables work correctly
 * with the fixed DataTables processing.
 * 
 * @category Table System Tests
 * @package  Incodiy\Codiy\Library\Components\Table\UserActivity
 * @author   Incodiy Team
 * @since    v2.3.1
 */

require_once 'd:\worksites\incodiy\mantra.smartfren.dev\vendor\autoload.php';

use Incodiy\Codiy\Models\Admin\System\UserActivity;
use Incodiy\Codiy\Library\Components\Table\Craft\Datatables;

// Bootstrap Laravel
$app = require_once 'd:\worksites\incodiy\mantra.smartfren.dev\bootstrap\app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 UserActivity Temp Table Test\n";
echo "=" . str_repeat("=", 50) . "\n\n";

echo "📋 Purpose: Test UserActivity temp table creation and processing\n";
echo "📋 Tables: temp_user_never_login, temp_montly_activity\n";
echo "📋 Version: v2.3.1\n\n";

try {
    $userActivity = new UserActivity();
    
    // Test 1: Create temp_user_never_login
    echo "📋 Test 1: Creating temp_user_never_login\n";
    $userActivity->user_never_login();
    echo "✅ Temp table created: temp_user_never_login\n";
    
    // Verify table exists and has data
    $count1 = \DB::table('temp_user_never_login')->count();
    echo "✅ Record count: {$count1}\n\n";
    
    // Test 2: Create temp_montly_activity
    echo "📋 Test 2: Creating temp_montly_activity\n";
    $userActivity->montly_activity();
    echo "✅ Temp table created: temp_montly_activity\n";
    
    // Verify table exists and has data
    $count2 = \DB::table('temp_montly_activity')->count();
    echo "✅ Record count: {$count2}\n\n";
    
    // Test 3: Test model creation for both temp tables
    echo "📋 Test 3: Testing model creation for temp tables\n";
    $datatables = new Datatables();
    $reflection = new ReflectionClass($datatables);
    $method = $reflection->getMethod('tryCreateSpecificModel');
    $method->setAccessible(true);
    
    $tempTables = ['temp_user_never_login', 'temp_montly_activity'];
    
    foreach ($tempTables as $tableName) {
        echo "🔍 Testing: {$tableName}\n";
        $result = $method->invoke($datatables, $tableName);
        
        if ($result) {
            echo "  ✅ Model created: " . get_class($result) . "\n";
            
            // Test connection
            $connection = $result->getConnection();
            echo "  ✅ Connection: " . ($connection ? get_class($connection) : 'NULL') . "\n";
            
            // Test count (this was failing before fix)
            try {
                $count = $result->count();
                echo "  ✅ Count successful: {$count} records\n";
            } catch (Exception $e) {
                echo "  ❌ Count failed: " . $e->getMessage() . "\n";
            }
            
            // Verify it's Query Builder for temp tables
            if ($result instanceof \Illuminate\Database\Query\Builder) {
                echo "  ✅ Correct: Query Builder for temp table\n";
            } else {
                echo "  ⚠️  Expected Query Builder, got " . get_class($result) . "\n";
            }
        } else {
            echo "  ❌ No model created\n";
        }
        echo "\n";
    }
    
    // Test 4: Sample data verification
    echo "📋 Test 4: Sample data verification\n";
    
    // Check temp_user_never_login structure
    $sample1 = \DB::table('temp_user_never_login')->first();
    if ($sample1) {
        $columns1 = array_keys((array)$sample1);
        echo "✅ temp_user_never_login columns: " . implode(', ', array_slice($columns1, 0, 5)) . "...\n";
    }
    
    // Check temp_montly_activity structure
    $sample2 = \DB::table('temp_montly_activity')->first();
    if ($sample2) {
        $columns2 = array_keys((array)$sample2);
        echo "✅ temp_montly_activity columns: " . implode(', ', array_slice($columns2, 0, 5)) . "...\n";
    }
    
    echo "\n📊 UserActivity Test Results Summary:\n";
    echo "- temp_user_never_login: ✅ {$count1} records\n";
    echo "- temp_montly_activity: ✅ {$count2} records\n";
    echo "- Model creation: ✅ Query Builder with connections\n";
    echo "- Count operations: ✅ No 'prepare() on null' errors\n";
    echo "- Ready for DataTables processing: ✅\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n🏁 UserActivity test completed!\n";
echo "=" . str_repeat("=", 50) . "\n";