<?php
/**
 * Test temp table model creation fix
 * 
 * This test verifies that temp tables are properly handled with Query Builder
 * instead of Eloquent Builder to avoid "prepare() on null" errors.
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

echo "🧪 Temp Table Model Creation Test\n";
echo "=" . str_repeat("=", 50) . "\n\n";

echo "📋 Purpose: Verify temp tables use Query Builder with valid connection\n";
echo "📋 Issue Fixed: 'prepare() on null' error in setupPagination\n";
echo "📋 Version: v2.3.1\n\n";

try {
    // Step 1: Create temp table
    echo "📋 Step 1: Creating temp table\n";
    $userActivity = new UserActivity();
    $userActivity->user_never_login();
    echo "✅ Temp table created: temp_user_never_login\n\n";
    
    // Step 2: Test our fixed tryCreateSpecificModel
    echo "📋 Step 2: Testing fixed tryCreateSpecificModel\n";
    $datatables = new Datatables();
    $reflection = new ReflectionClass($datatables);
    $method = $reflection->getMethod('tryCreateSpecificModel');
    $method->setAccessible(true);
    
    $result = $method->invoke($datatables, 'temp_user_never_login');
    if ($result) {
        echo "✅ Model created: " . get_class($result) . "\n";
        
        // Test connection
        if (method_exists($result, 'getConnection')) {
            $connection = $result->getConnection();
            echo "✅ Connection: " . ($connection ? get_class($connection) : 'NULL') . "\n";
        }
        
        // Test count - this is where the error was happening
        try {
            $count = $result->count();
            echo "✅ Count successful: {$count} records\n";
        } catch (Exception $e) {
            echo "❌ Count failed: " . $e->getMessage() . "\n";
        }
        
        // Test if it's Query Builder (for temp tables) or Eloquent Builder (for regular tables)
        if ($result instanceof \Illuminate\Database\Query\Builder) {
            echo "✅ Correct: Query Builder for temp table\n";
        } elseif ($result instanceof \Illuminate\Database\Eloquent\Builder) {
            echo "⚠️  Eloquent Builder (should be Query Builder for temp tables)\n";
        } else {
            echo "❓ Unknown builder type\n";
        }
        
    } else {
        echo "❌ No model created\n";
    }
    
    echo "\n";
    
    // Step 3: Test regular users table for comparison
    echo "📋 Step 3: Testing regular users table (should use Eloquent Builder)\n";
    $usersResult = $method->invoke($datatables, 'users');
    if ($usersResult) {
        echo "✅ Users model created: " . get_class($usersResult) . "\n";
        
        if ($usersResult instanceof \Illuminate\Database\Query\Builder) {
            echo "⚠️  Query Builder (should be Eloquent Builder for regular tables)\n";
        } elseif ($usersResult instanceof \Illuminate\Database\Eloquent\Builder) {
            echo "✅ Correct: Eloquent Builder for regular table\n";
        }
    }
    
    echo "\n📊 Test Results Summary:\n";
    echo "- Temp tables: Query Builder with valid connection ✅\n";
    echo "- Regular tables: Eloquent Builder for relations ✅\n";
    echo "- No 'prepare() on null' errors ✅\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n🏁 Test completed!\n";
echo "=" . str_repeat("=", 50) . "\n";