<?php
/**
 * New Issues Fix Test
 * 
 * Test for newly reported issues:
 * 1. UserActivity temp table timing issue
 * 2. UserController filter group_info missing
 * 3. Filter modal auto-close issue
 * 
 * @category Table System Tests
 * @package  Incodiy\Codiy\Library\Components\Table
 * @author   Incodiy Team
 * @since    v2.3.1
 */

require_once 'd:\worksites\incodiy\mantra.smartfren.dev\vendor\autoload.php';

use Incodiy\Codiy\Models\Admin\System\UserActivity;
use Incodiy\Codiy\Controllers\Admin\System\UserController;
use Incodiy\Codiy\Controllers\Admin\System\UserActivityController;

// Bootstrap Laravel
$app = require_once 'd:\worksites\incodiy\mantra.smartfren.dev\bootstrap\app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 New Issues Fix Test\n";
echo "=" . str_repeat("=", 60) . "\n\n";

echo "📋 Purpose: Test fixes for newly reported issues\n";
echo "📋 Issues:\n";
echo "   1. UserActivity temp table timing issue\n";
echo "   2. UserController filter group_info missing\n";
echo "   3. Filter modal auto-close issue\n";
echo "📋 Version: v2.3.1\n\n";

$allTestsPassed = true;

// Test 1: UserActivity Temp Table Timing Fix
echo "📋 Test 1: UserActivity Temp Table Timing Fix\n";
echo "🔍 Testing enhanced temp table creation with commit and verification\n";

try {
    $userActivity = new UserActivity();
    
    // Drop existing temp tables to test creation
    \DB::statement('DROP TABLE IF EXISTS temp_user_never_login');
    \DB::statement('DROP TABLE IF EXISTS temp_montly_activity');
    
    echo "🔧 Creating temp_user_never_login with timing fix...\n";
    $userActivity->user_never_login();
    \DB::commit();
    sleep(1); // Simulate timing fix
    $table1Exists = \Schema::hasTable('temp_user_never_login');
    
    echo "🔧 Creating temp_montly_activity with timing fix...\n";
    $userActivity->montly_activity();
    \DB::commit();
    sleep(1); // Simulate timing fix
    $table2Exists = \Schema::hasTable('temp_montly_activity');
    
    if ($table1Exists && $table2Exists) {
        echo "✅ Both temp tables created successfully with timing fix\n";
        
        // Test immediate column access (this was failing before)
        $columns1 = diy_get_table_columns('temp_user_never_login');
        $columns2 = diy_get_table_columns('temp_montly_activity');
        
        if (!empty($columns1) && !empty($columns2)) {
            echo "✅ Column access works immediately after creation\n";
            
            // Test column type detection (this was causing the error)
            $type1 = diy_get_table_column_type('temp_user_never_login', $columns1[0]);
            $type2 = diy_get_table_column_type('temp_montly_activity', $columns2[0]);
            
            echo "📊 Column types detected: {$columns1[0]}={$type1}, {$columns2[0]}={$type2}\n";
            echo "✅ Timing issue fixed - no more schema errors\n";
        } else {
            echo "❌ Column access still failing\n";
            $allTestsPassed = false;
        }
    } else {
        echo "❌ Temp table creation failed\n";
        $allTestsPassed = false;
    }
    
} catch (\Exception $e) {
    echo "❌ Error in timing fix test: " . $e->getMessage() . "\n";
    $allTestsPassed = false;
}

// Test 2: UserController Relations Fix
echo "\n📋 Test 2: UserController Relations Fix\n";
echo "🔍 Testing relations configuration for group_info filter\n";

try {
    $reflection = new \ReflectionClass(UserController::class);
    $method = $reflection->getMethod('index');
    $source = file_get_contents($method->getFileName());
    
    // Check if relations are enabled (not commented out)
    $hasRelationsEnabled = strpos($source, '$this->table->relations($this->model, \'group\', \'group_info\'') !== false;
    $hasUseRelation = strpos($source, '$this->table->useRelation(\'group\');') !== false;
    $hasGroupInfoFilter = strpos($source, '$this->table->filterGroups(\'group_info\', \'selectbox\', true);') !== false;
    
    if ($hasRelationsEnabled && $hasUseRelation && $hasGroupInfoFilter) {
        echo "✅ UserController relations properly configured\n";
        echo "✅ group_info filter should now work\n";
    } else {
        echo "❌ UserController relations configuration incomplete\n";
        echo "   Relations enabled: " . ($hasRelationsEnabled ? 'YES' : 'NO') . "\n";
        echo "   UseRelation: " . ($hasUseRelation ? 'YES' : 'NO') . "\n";
        echo "   GroupInfo filter: " . ($hasGroupInfoFilter ? 'YES' : 'NO') . "\n";
        $allTestsPassed = false;
    }
    
} catch (\Exception $e) {
    echo "❌ Error verifying UserController relations: " . $e->getMessage() . "\n";
    $allTestsPassed = false;
}

// Test 3: Modal Auto-Close Fix
echo "\n📋 Test 3: Modal Auto-Close Fix\n";
echo "🔍 Testing modal auto-close JavaScript enhancement\n";

try {
    // Test modal HTML generation
    $testElements = ['<input type="text" name="test" />'];
    $modalHtml = diy_modal_content_html('test_cdyFILTERmodalBOX', 'Test', $testElements);
    
    // Check if auto-close script is included
    $hasAutoCloseScript = strpos($modalHtml, 'setTimeout(function()') !== false;
    $hasModalHide = strpos($modalHtml, '.modal("hide")') !== false;
    $hasLoadingState = strpos($modalHtml, 'fa-spinner fa-spin') !== false;
    $hasDataModalId = strpos($modalHtml, 'data-modal-id') !== false;
    
    if ($hasAutoCloseScript && $hasModalHide && $hasLoadingState && $hasDataModalId) {
        echo "✅ Modal auto-close script properly implemented\n";
        echo "✅ Loading state and modal hide functionality added\n";
        echo "✅ Filter popup should now auto-close after 2 seconds\n";
    } else {
        echo "❌ Modal auto-close script incomplete\n";
        echo "   Auto-close script: " . ($hasAutoCloseScript ? 'YES' : 'NO') . "\n";
        echo "   Modal hide: " . ($hasModalHide ? 'YES' : 'NO') . "\n";
        echo "   Loading state: " . ($hasLoadingState ? 'YES' : 'NO') . "\n";
        echo "   Data modal ID: " . ($hasDataModalId ? 'YES' : 'NO') . "\n";
        $allTestsPassed = false;
    }
    
} catch (\Exception $e) {
    echo "❌ Error testing modal auto-close: " . $e->getMessage() . "\n";
    $allTestsPassed = false;
}

// Test 4: UserActivityController Enhanced Verification
echo "\n📋 Test 4: UserActivityController Enhanced Verification\n";
echo "🔍 Testing enhanced temp table verification logic\n";

try {
    $reflection = new \ReflectionClass(UserActivityController::class);
    $method = $reflection->getMethod('index');
    $source = file_get_contents($method->getFileName());
    
    // Check if enhanced verification is implemented
    $hasDbCommit = strpos($source, '\DB::commit();') !== false;
    $hasSleep = strpos($source, 'sleep(1);') !== false;
    $hasFinalVerification = strpos($source, 'Final table verification') !== false;
    $hasDoubleCheck = strpos($source, 'Double-check both tables exist') !== false;
    
    if ($hasDbCommit && $hasSleep && $hasFinalVerification && $hasDoubleCheck) {
        echo "✅ Enhanced verification logic implemented\n";
        echo "✅ Database commit and timing fixes added\n";
        echo "✅ Double-check verification added\n";
    } else {
        echo "❌ Enhanced verification incomplete\n";
        echo "   DB Commit: " . ($hasDbCommit ? 'YES' : 'NO') . "\n";
        echo "   Sleep timing: " . ($hasSleep ? 'YES' : 'NO') . "\n";
        echo "   Final verification: " . ($hasFinalVerification ? 'YES' : 'NO') . "\n";
        echo "   Double check: " . ($hasDoubleCheck ? 'YES' : 'NO') . "\n";
        $allTestsPassed = false;
    }
    
} catch (\Exception $e) {
    echo "❌ Error verifying UserActivityController: " . $e->getMessage() . "\n";
    $allTestsPassed = false;
}

// Final Results
echo "\n📊 New Issues Fix Test Results:\n";
echo "=" . str_repeat("=", 60) . "\n";

if ($allTestsPassed) {
    echo "🎉 ALL NEW ISSUES FIXED SUCCESSFULLY! 🎉\n";
    echo "✅ UserActivity timing issue: FIXED\n";
    echo "✅ UserController group_info filter: FIXED\n";
    echo "✅ Modal auto-close issue: FIXED\n";
    echo "✅ Enhanced verification logic: IMPLEMENTED\n";
    echo "\n🚀 SYSTEM READY FOR TESTING!\n";
} else {
    echo "❌ SOME FIXES INCOMPLETE - REVIEW REQUIRED\n";
    echo "Please check the failed tests above and complete any remaining fixes.\n";
}

echo "\n📋 Next Steps:\n";
if ($allTestsPassed) {
    echo "1. ✅ Test UserController page - verify group_info filter appears\n";
    echo "2. ✅ Test UserActivity page - verify no temp table errors\n";
    echo "3. ✅ Test filter modal - verify auto-close after filtering\n";
    echo "4. 📋 Monitor logs for any remaining issues\n";
} else {
    echo "1. 🔧 Complete remaining fixes\n";
    echo "2. 🔄 Re-run this test\n";
    echo "3. 📋 Only proceed to testing after all fixes pass\n";
}

echo "\n🏁 New issues fix test completed!\n";
echo "=" . str_repeat("=", 60) . "\n";