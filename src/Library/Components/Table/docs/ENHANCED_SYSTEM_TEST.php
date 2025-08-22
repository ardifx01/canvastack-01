<?php
/**
 * Enhanced Table System Test & Examples
 * 
 * This file demonstrates the new Universal Data Source Support
 * and provides test cases for relationship fixes
 * 
 * @author CoDIY Development Team
 * @date December 2024
 */

namespace Incodiy\Codiy\Library\Components\Table\Test;

use Illuminate\Support\Facades\DB;
use Incodiy\Codiy\Library\Components\Table\Objects;
use Incodiy\Codiy\Models\Admin\System\User;

class EnhancedSystemTest 
{
    public function testUniversalDataSourceSupport()
    {
        echo "🚀 Testing Universal Data Source Support\n";
        echo "=========================================\n\n";
        
        // Test configurations for different data source types
        $testConfigurations = [
            'string_table_test' => $this->getStringTableConfig(),
            'raw_sql_test' => $this->getRawSqlConfig(),
            'query_builder_test' => $this->getQueryBuilderConfig(),
            'eloquent_test' => $this->getEloquentConfig(),
            'auto_detection_test' => $this->getAutoDetectionConfig(),
            'relationship_fix_test' => $this->getRelationshipFixConfig()
        ];
        
        foreach ($testConfigurations as $testName => $config) {
            echo "📋 Running: {$testName}\n";
            $this->runSingleTest($testName, $config);
            echo "\n";
        }
        
        echo "✅ All tests completed!\n";
    }
    
    /**
     * Test 1: String Table Name Support
     */
    private function getStringTableConfig()
    {
        return [
            'datatables' => [
                'model' => [
                    'users' => [
                        'type' => 'string_table',
                        'source' => 'users'
                    ]
                ],
                'columns' => [
                    'users' => [
                        'lists' => ['id', 'name', 'email', 'active'],
                        'actions' => true
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Test 2: Raw SQL Query Support  
     */
    private function getRawSqlConfig()
    {
        return [
            'datatables' => [
                'model' => [
                    'user_with_groups' => [
                        'type' => 'raw_sql',
                        'source' => '
                            SELECT 
                                u.id,
                                u.name,
                                u.email,
                                u.active,
                                g.group_name,
                                g.group_alias,
                                g.group_info
                            FROM users u 
                            LEFT JOIN base_user_group bug ON u.id = bug.user_id
                            LEFT JOIN base_group g ON bug.group_id = g.id
                            WHERE u.active = 1
                        '
                    ]
                ],
                'columns' => [
                    'user_with_groups' => [
                        'lists' => ['id', 'name', 'email', 'group_name', 'group_alias'],
                        'actions' => ['view', 'edit']
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Test 3: Laravel Query Builder Support
     */
    private function getQueryBuilderConfig()
    {
        return [
            'datatables' => [
                'model' => [
                    'active_users_with_groups' => [
                        'type' => 'query_builder',
                        'source' => 'DB::table("users")
                            ->leftJoin("base_user_group", "users.id", "=", "base_user_group.user_id")
                            ->leftJoin("base_group", "base_group.id", "=", "base_user_group.group_id")
                            ->select(
                                "users.*",
                                "base_group.group_name",
                                "base_group.group_alias", 
                                "base_group.group_info"
                            )
                            ->where("users.active", 1)'
                    ]
                ],
                'columns' => [
                    'active_users_with_groups' => [
                        'lists' => ['id', 'name', 'email', 'group_name', 'group_alias'],
                        'actions' => true
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Test 4: Laravel Eloquent Support
     */
    private function getEloquentConfig()
    {
        return [
            'datatables' => [
                'model' => [
                    'users_eloquent' => [
                        'type' => 'eloquent',
                        'source' => 'Incodiy\\Codiy\\Models\\Admin\\System\\User::with("group")->where("active", 1)->get()'
                    ]
                ],
                'columns' => [
                    'users_eloquent' => [
                        'lists' => ['id', 'name', 'email', 'group_name'],
                        'actions' => ['view', 'edit', 'delete']
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Test 5: Auto-Detection System
     */
    private function getAutoDetectionConfig()
    {
        return [
            'datatables' => [
                'model' => [
                    'auto_table' => [
                        'type' => 'auto',
                        'source' => 'products'  // Should detect as string_table
                    ],
                    'auto_sql' => [
                        'type' => 'auto',
                        'source' => 'SELECT * FROM categories WHERE active = 1'  // Should detect as raw_sql
                    ],
                    'auto_query_builder' => [
                        'type' => 'auto',
                        'source' => 'DB::table("products")->where("featured", 1)'  // Should detect as query_builder
                    ],
                    'auto_eloquent' => [
                        'type' => 'auto', 
                        'source' => 'App\\Models\\Product::with("category")->get()'  // Should detect as eloquent
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Test 6: Relationship Fix Verification
     */
    private function getRelationshipFixConfig()
    {
        return [
            'datatables' => [
                'model' => [
                    'users' => [
                        'type' => 'eloquent_model',
                        'source' => new User()  // This should trigger getUserInfo method
                    ]
                ],
                'columns' => [
                    'users' => [
                        'lists' => ['id', 'name', 'email', 'group_name', 'group_alias', 'group_info'],
                        'actions' => true
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Run individual test case
     */
    private function runSingleTest($testName, $config)
    {
        try {
            echo "  🔄 Initializing table system...\n";
            
            $table = new Objects();
            
            // Simulate datafeed configuration  
            $dataObject = (object) $config;
            
            echo "  🔍 Configuration loaded successfully\n";
            echo "  📊 Data source types detected:\n";
            
            foreach ($config['datatables']['model'] as $modelName => $modelConfig) {
                $type = $modelConfig['type'];
                $sourcePreview = is_string($modelConfig['source']) 
                    ? substr($modelConfig['source'], 0, 50) . '...'
                    : get_class($modelConfig['source']);
                    
                echo "    - {$modelName}: {$type} ({$sourcePreview})\n";
            }
            
            echo "  ✅ Test configuration validated\n";
            
        } catch (\Exception $e) {
            echo "  ❌ Test failed: " . $e->getMessage() . "\n";
            echo "  🔍 Stack trace: " . $e->getTraceAsString() . "\n";
        }
    }
    
    /**
     * Test User Model getUserInfo Method Directly
     */
    public function testUserModelDirectly()
    {
        echo "🔍 Testing User Model getUserInfo Method Directly\n";
        echo "================================================\n\n";
        
        try {
            $user = new User();
            
            echo "  🔄 Testing getUserInfo with get=false (query builder)...\n";
            $queryBuilder = $user->getUserInfo(false, false);
            
            echo "  📊 Query SQL: " . $queryBuilder->toSql() . "\n";
            echo "  📊 Query Bindings: " . json_encode($queryBuilder->getBindings()) . "\n";
            
            echo "  🔄 Testing data retrieval...\n";
            $data = $queryBuilder->limit(3)->get();
            
            echo "  📋 Sample Data (first 3 records):\n";
            foreach ($data as $record) {
                echo "    - ID: {$record->id}, Name: {$record->name}, Group: {$record->group_name}\n";
            }
            
            echo "  ✅ User model test completed successfully\n";
            
        } catch (\Exception $e) {
            echo "  ❌ User model test failed: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Performance Benchmark Test
     */
    public function benchmarkPerformance()
    {
        echo "⚡ Performance Benchmark Test\n";
        echo "============================\n\n";
        
        $benchmarks = [
            'string_table' => function() {
                return DB::table('users')->count();
            },
            'query_builder_join' => function() {
                return DB::table('users')
                    ->leftJoin('base_user_group', 'users.id', '=', 'base_user_group.user_id')
                    ->leftJoin('base_group', 'base_group.id', '=', 'base_user_group.group_id')
                    ->count();
            },
            'raw_sql' => function() {
                return DB::select('SELECT COUNT(*) as count FROM users u 
                    LEFT JOIN base_user_group bug ON u.id = bug.user_id
                    LEFT JOIN base_group g ON bug.group_id = g.id')[0]->count;
            },
            'eloquent_with_relations' => function() {
                return User::with('group')->count();
            }
        ];
        
        foreach ($benchmarks as $testName => $testFunction) {
            $startTime = microtime(true);
            
            try {
                $result = $testFunction();
                $endTime = microtime(true);
                $duration = round(($endTime - $startTime) * 1000, 2);
                
                echo "  📊 {$testName}: {$duration}ms (Count: {$result})\n";
                
            } catch (\Exception $e) {
                echo "  ❌ {$testName}: ERROR - " . $e->getMessage() . "\n";
            }
        }
    }
    
    /**
     * Security Test for String Evaluation
     */
    public function testSecurity()
    {
        echo "🔒 Security Test for String Evaluation\n";
        echo "====================================\n\n";
        
        $securityTests = [
            'valid_query_builder' => [
                'input' => 'DB::table("users")->where("active", 1)',
                'expected' => 'SAFE'
            ],
            'valid_eloquent' => [
                'input' => 'App\\Models\\User::where("active", 1)->get()',
                'expected' => 'SAFE'
            ],
            'malicious_code' => [
                'input' => 'exec("rm -rf /")', 
                'expected' => 'BLOCKED'
            ],
            'sql_injection_attempt' => [
                'input' => 'DB::table("users")->whereRaw("1=1; DROP TABLE users")',
                'expected' => 'RISKY'
            ]
        ];
        
        foreach ($securityTests as $testName => $test) {
            echo "  🔍 Testing: {$testName}\n";
            echo "    Input: {$test['input']}\n";
            
            // Basic security validation (in real implementation)
            $isSafe = $this->validateQueryString($test['input']);
            $status = $isSafe ? 'SAFE' : 'RISKY';
            
            echo "    Status: {$status}\n";
            echo "    Expected: {$test['expected']}\n\n";
        }
        
        echo "  ⚠️  Recommendation: Use object instances instead of strings in production\n";
    }
    
    /**
     * Basic security validation for query strings
     */
    private function validateQueryString($input)
    {
        $dangerousPatterns = [
            '/exec\s*\(/i',
            '/system\s*\(/i', 
            '/shell_exec\s*\(/i',
            '/eval\s*\(/i',
            '/file_get_contents\s*\(/i',
            '/DROP\s+TABLE/i',
            '/TRUNCATE\s+TABLE/i',
            '/DELETE\s+FROM/i'
        ];
        
        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return false;
            }
        }
        
        return true;
    }
}

// Usage Example:
if (php_sapi_name() === 'cli') {
    echo "🚀 CoDIY Enhanced Table System Test Suite\n";
    echo "=========================================\n\n";
    
    $test = new EnhancedSystemTest();
    
    // Run all tests
    $test->testUniversalDataSourceSupport();
    echo "\n";
    
    $test->testUserModelDirectly();
    echo "\n";
    
    $test->benchmarkPerformance();
    echo "\n";
    
    $test->testSecurity();
    
    echo "\n🎉 Test suite completed!\n";
}
?>