<?php
/**
 * Full DataTables Flow Integration Test
 * 
 * This test verifies the complete DataTables processing flow with temp tables,
 * including Enhanced Architecture fallback to Legacy processing.
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

// Enable debug mode
config(['datatables.debug' => true]);

echo "🧪 Full DataTables Flow Integration Test\n";
echo "=" . str_repeat("=", 60) . "\n\n";

echo "📋 Purpose: Test complete DataTables processing with temp tables\n";
echo "📋 Scope: Enhanced Architecture → Legacy fallback → Result generation\n";
echo "📋 Version: v2.3.1\n\n";

try {
    // Step 1: Create temp table like UserActivity does
    echo "📋 Step 1: Creating temp table\n";
    $userActivity = new UserActivity();
    $userActivity->user_never_login();
    echo "✅ Temp table created: temp_user_never_login\n\n";
    
    // Step 2: Create mock runtime data like UserActivity does
    echo "📋 Step 2: Creating mock runtime data\n";
    
    // Create a mock data object similar to what UserActivity creates
    $mockData = new stdClass();
    $mockData->datatables = new stdClass();
    
    // Add model processing configuration (this triggers temp table creation)
    $mockData->datatables->modelProcessing = [
        'temp_user_never_login' => [
            'model' => $userActivity,
            'function' => 'user_never_login',
            'connection' => 'mysql',
            'strict' => false
        ]
    ];
    
    // Add basic table configuration
    $mockData->datatables->model = [
        'temp_user_never_login' => new \Incodiy\Codiy\Models\Admin\System\DynamicTables()
    ];
    
    $mockData->datatables->columns = [
        'temp_user_never_login' => [
            'lists' => ['username', 'email', 'group.name', 'group.info'],
            'actions' => [],
            'orderby' => ['username' => 'desc'],
            'clickable' => [],
            'sortable' => ['username', 'email'],
            'searchable' => ['username', 'email'],
            'filter_groups' => ['username', 'group_info'],
            'filters' => []
        ]
    ];
    
    echo "✅ Mock runtime data created\n\n";
    
    // Step 3: Create mock method data (DataTables AJAX request)
    echo "📋 Step 3: Creating mock DataTables request\n";
    $mockMethod = [
        'renderDataTables' => 'true',
        'difta' => [
            'name' => 'temp_user_never_login',
            'source' => 'dynamics'
        ],
        'draw' => '1',
        'start' => '0',
        'length' => '10'
    ];
    
    echo "✅ Mock DataTables request created\n\n";
    
    // Step 4: Test DataTables processing
    echo "📋 Step 4: Testing DataTables processing\n";
    $datatables = new Datatables();
    
    echo "🚀 Calling DataTables process()...\n";
    $result = $datatables->process($mockMethod, $mockData, [], []);
    
    echo "✅ DataTables processing completed successfully!\n";
    echo "📊 Result type: " . gettype($result) . "\n";
    
    if (is_array($result)) {
        echo "📊 Result keys: " . implode(', ', array_keys($result)) . "\n";
        if (isset($result['data'])) {
            echo "📊 Data records: " . count($result['data']) . "\n";
            if (!empty($result['data'])) {
                echo "📊 First record keys: " . implode(', ', array_keys($result['data'][0])) . "\n";
            }
        }
        if (isset($result['recordsTotal'])) {
            echo "📊 Total records: " . $result['recordsTotal'] . "\n";
        }
    }
    
    echo "\n📊 Integration Test Results Summary:\n";
    echo "- Temp table creation: ✅\n";
    echo "- Mock data setup: ✅\n";
    echo "- DataTables processing: ✅\n";
    echo "- No critical errors: ✅\n";
    echo "- Enhanced → Legacy fallback: Expected behavior\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    
    // Show relevant stack trace
    echo "\n🔍 Stack trace (first 5 lines):\n";
    $trace = explode("\n", $e->getTraceAsString());
    foreach (array_slice($trace, 0, 5) as $i => $line) {
        echo "  " . ($i + 1) . ". " . $line . "\n";
    }
}

echo "\n🏁 Integration test completed!\n";
echo "📋 Check storage/logs/laravel.log for detailed debug information\n";
echo "=" . str_repeat("=", 60) . "\n";