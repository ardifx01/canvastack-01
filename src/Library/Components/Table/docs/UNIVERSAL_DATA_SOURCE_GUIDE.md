# 🚀 Universal Data Source Guide

## 🎯 **OVERVIEW**
Guide lengkap untuk menggunakan Enhanced Universal Data Source Support pada CoDIY Table System.

**Version:** 2.0 Enhanced  
**Status:** ✅ Ready for Production  
**Compatibility:** Backward Compatible dengan existing configurations

---

## 🔧 **SUPPORTED DATA SOURCE TYPES**

### **1. String Table Name**
```php
// Configuration
'datatables' => [
    'model' => [
        'users' => [
            'type' => 'string_table',
            'source' => 'users'
        ],
        'products' => [
            'type' => 'string_table', 
            'source' => 'products'
        ]
    ]
]

// Auto-detection (recommended)
'datatables' => [
    'model' => [
        'users' => [
            'type' => 'auto',
            'source' => 'users'  // Will auto-detect as string_table
        ]
    ]
]
```

### **2. Raw SQL Query**
```php
// Simple SQL
'datatables' => [
    'model' => [
        'active_users' => [
            'type' => 'raw_sql',
            'source' => 'SELECT * FROM users WHERE active = 1'
        ]
    ]
]

// Complex SQL with relationships
'datatables' => [
    'model' => [
        'user_with_groups' => [
            'type' => 'raw_sql',
            'source' => '
                SELECT 
                    u.*, 
                    g.group_name, 
                    g.group_alias,
                    g.group_info
                FROM users u 
                LEFT JOIN base_user_group bug ON u.id = bug.user_id
                LEFT JOIN base_group g ON bug.group_id = g.id
                WHERE u.active = 1
            '
        ]
    ]
]

// Auto-detection
'datatables' => [
    'model' => [
        'reports' => [
            'type' => 'auto',
            'source' => 'SELECT r.*, u.name FROM reports r JOIN users u ON r.user_id = u.id'
        ]
    ]
]
```

### **3. Laravel Query Builder**
```php
// String representation
'datatables' => [
    'model' => [
        'active_users' => [
            'type' => 'query_builder',
            'source' => 'DB::table("users")->where("active", 1)->select("*")'
        ]
    ]
]

// Complex Query Builder with joins
'datatables' => [
    'model' => [
        'user_groups' => [
            'type' => 'query_builder', 
            'source' => '
                DB::table("users")
                  ->leftJoin("base_user_group", "users.id", "=", "base_user_group.user_id")
                  ->leftJoin("base_group", "base_group.id", "=", "base_user_group.group_id") 
                  ->select("users.*", "base_group.group_name", "base_group.group_alias")
                  ->where("users.active", 1)
            '
        ]
    ]
]

// Direct Query Builder object (dalam controller)
$queryBuilder = DB::table('users')
    ->leftJoin('base_user_group', 'users.id', '=', 'base_user_group.user_id')
    ->leftJoin('base_group', 'base_group.id', '=', 'base_user_group.group_id')
    ->select('users.*', 'base_group.group_name');

$config['datatables']['model']['users'] = [
    'type' => 'query_builder',
    'source' => $queryBuilder
];
```

### **4. Laravel Eloquent**
```php
// Basic Eloquent
'datatables' => [
    'model' => [
        'users' => [
            'type' => 'eloquent',
            'source' => 'App\\Models\\User::all()'
        ]
    ]
]

// Eloquent with relationships
'datatables' => [
    'model' => [
        'users_with_groups' => [
            'type' => 'eloquent',
            'source' => 'App\\Models\\User::with("groups")->get()'
        ]
    ]
]

// Complex Eloquent queries
'datatables' => [
    'model' => [
        'active_users' => [
            'type' => 'eloquent',
            'source' => '
                App\\Models\\User::whereHas("groups", function($query) {
                    $query->where("active", 1);
                })->with(["groups" => function($query) {
                    $query->select("group_name", "group_alias", "group_info");
                }])->get()
            '
        ]
    ]
]

// Direct Eloquent object (dalam controller)
$users = \App\Models\User::with('groups')->where('active', 1)->get();

$config['datatables']['model']['users'] = [
    'type' => 'eloquent',
    'source' => $users
];
```

---

## 🔄 **AUTO-DETECTION SYSTEM**

System dapat secara otomatis mendeteksi jenis data source:

```php
'datatables' => [
    'model' => [
        'simple_table' => [
            'type' => 'auto',
            'source' => 'users'  // → Detected as: string_table
        ],
        'sql_query' => [
            'type' => 'auto', 
            'source' => 'SELECT * FROM products'  // → Detected as: raw_sql
        ],
        'query_builder' => [
            'type' => 'auto',
            'source' => 'DB::table("users")->where("active", 1)'  // → Detected as: query_builder
        ],
        'eloquent' => [
            'type' => 'auto',
            'source' => 'App\\User::with("groups")->get()'  // → Detected as: eloquent
        ]
    ]
]
```

---

## 🛠️ **MIGRATION FROM EXISTING SYSTEM**

### **Legacy Support**
Existing configurations akan tetap bekerja:

```php
// ✅ LEGACY - Tetap didukung
'datatables' => [
    'model' => [
        'users' => [
            'type' => 'model',
            'source' => $userModel  // → Converted to: eloquent_model
        ],
        'reports' => [
            'type' => 'sql',
            'source' => 'SELECT * FROM reports'  // → Converted to: raw_sql
        ]
    ]
]
```

### **Enhanced Configurations**
```php
// 🚀 NEW - Enhanced dengan universal support
'datatables' => [
    'model' => [
        'users' => [
            'type' => 'auto',  // Auto-detection
            'source' => 'users'
        ],
        'user_with_groups' => [
            'type' => 'raw_sql',
            'source' => 'SELECT u.*, g.group_name FROM users u LEFT JOIN base_user_group bug ON u.id = bug.user_id LEFT JOIN base_group g ON bug.group_id = g.id'
        ],
        'active_products' => [
            'type' => 'query_builder',
            'source' => 'DB::table("products")->where("active", 1)->leftJoin("categories", "products.category_id", "=", "categories.id")'
        ],
        'featured_users' => [
            'type' => 'eloquent',
            'source' => 'App\\Models\\User::with("groups", "profile")->where("featured", true)->get()'
        ]
    ]
]
```

---

## 🎛️ **ADVANCED USAGE**

### **Dynamic Configuration dalam Controller**

```php
<?php
namespace App\Http\Controllers;

class UserController extends Controller 
{
    public function index()
    {
        // Method 1: Direct configuration
        $config['datatables']['model']['users'] = [
            'type' => 'query_builder',
            'source' => DB::table('users')
                ->leftJoin('base_user_group', 'users.id', '=', 'base_user_group.user_id')
                ->leftJoin('base_group', 'base_group.id', '=', 'base_user_group.group_id')
                ->select('users.*', 'base_group.group_name', 'base_group.group_alias')
                ->where('users.active', 1)
        ];
        
        // Method 2: Conditional source
        if ($request->has('with_groups')) {
            $config['datatables']['model']['users'] = [
                'type' => 'raw_sql',
                'source' => '
                    SELECT u.*, g.group_name, g.group_alias 
                    FROM users u 
                    LEFT JOIN base_user_group bug ON u.id = bug.user_id
                    LEFT JOIN base_group g ON bug.group_id = g.id
                    WHERE u.active = 1
                '
            ];
        } else {
            $config['datatables']['model']['users'] = [
                'type' => 'string_table',
                'source' => 'users'
            ];
        }
        
        // Method 3: Complex Eloquent
        $users = User::with(['groups' => function($query) {
            $query->select('id', 'group_name', 'group_alias', 'group_info');
        }])->where('active', 1);
        
        $config['datatables']['model']['users'] = [
            'type' => 'eloquent',
            'source' => $users
        ];
        
        return view('users.index', compact('config'));
    }
}
```

### **Conditional Data Sources**

```php
// Berdasarkan user role
$userRole = auth()->user()->role;

switch($userRole) {
    case 'admin':
        $dataConfig = [
            'type' => 'raw_sql',
            'source' => 'SELECT u.*, g.group_name, p.profile_data FROM users u LEFT JOIN base_user_group bug ON u.id = bug.user_id LEFT JOIN base_group g ON bug.group_id = g.id LEFT JOIN profiles p ON u.id = p.user_id'
        ];
        break;
        
    case 'manager':
        $dataConfig = [
            'type' => 'query_builder', 
            'source' => 'DB::table("users")->where("department_id", ' . auth()->user()->department_id . ')'
        ];
        break;
        
    default:
        $dataConfig = [
            'type' => 'string_table',
            'source' => 'users'
        ];
}

$config['datatables']['model']['users'] = $dataConfig;
```

---

## 🔍 **DEBUGGING & TROUBLESHOOTING**

### **Enable Debug Logging**
Tambahkan di `config/logging.php`:

```php
'channels' => [
    'table_debug' => [
        'driver' => 'single',
        'path' => storage_path('logs/table-debug.log'),
        'level' => 'debug',
    ]
]
```

### **Debug Log Messages**
System akan menghasilkan log messages berikut:

```
🔍 Detected data source type: string_table for users
🔧 Creating model from table name: users
✅ Found getUserInfo method in User model - using model relationship  
🔍 Relationship Query SQL: select `users`.*, `base_user_group`.`group_id`, `base_group`.`group_name`...
🔍 Sample relationship data: [{"id":1,"name":"Admin","group_name":"Administrator"}]
```

### **Common Issues & Solutions**

**1. Relationship Data Still NULL**
```php
// ❌ Problem: Relationship not working
'source' => 'users'

// ✅ Solution: Use explicit relationship query
'type' => 'raw_sql',
'source' => 'SELECT u.*, g.group_name FROM users u LEFT JOIN base_user_group bug ON u.id = bug.user_id LEFT JOIN base_group g ON bug.group_id = g.id'
```

**2. Query Builder String Evaluation Error**
```php
// ❌ Problem: Syntax error in string
'source' => 'DB::table(users)->where(active, 1)'

// ✅ Solution: Proper syntax with quotes
'source' => 'DB::table("users")->where("active", 1)'
```

**3. Eloquent Method Not Found**
```php
// ❌ Problem: Class not found
'source' => 'User::all()'

// ✅ Solution: Full namespace
'source' => 'App\\Models\\User::all()'
```

---

## 📈 **PERFORMANCE OPTIMIZATION**

### **Best Practices**

**1. Use Appropriate Data Source Type**
- **String Table**: Untuk simple queries tanpa JOIN
- **Query Builder**: Untuk complex queries dengan control penuh
- **Raw SQL**: Untuk optimized custom queries  
- **Eloquent**: Untuk relationship-heavy data dengan ORM benefits

**2. Query Optimization**
```php
// ✅ Good: Specific fields selection
'source' => 'DB::table("users")->select("id", "name", "email", "active")->where("active", 1)'

// ❌ Avoid: Select all with unnecessary data
'source' => 'DB::table("users")->select("*")'
```

**3. Relationship Loading**
```php
// ✅ Good: Eager loading
'source' => 'App\\Models\\User::with("groups:id,group_name")->get()'

// ❌ Avoid: N+1 queries
'source' => 'App\\Models\\User::all()'  // Will cause N+1 when accessing relationships
```

---

## ✅ **TESTING CHECKLIST**

- [ ] ✅ Legacy configurations (`type: 'model'`, `type: 'sql'`) still work
- [ ] ✅ String table names load correctly
- [ ] ✅ Raw SQL queries execute and display data
- [ ] ✅ Query Builder strings evaluate properly
- [ ] ✅ Eloquent queries load with relationships
- [ ] ✅ Auto-detection works for all source types
- [ ] ✅ Error handling prevents system crashes
- [ ] ✅ Debug logging provides useful information
- [ ] ✅ Performance is acceptable for large datasets
- [ ] ✅ Security considerations addressed (eval() usage)

---

## 🚨 **SECURITY CONSIDERATIONS**

**⚠️ Important:** String evaluation menggunakan `eval()` untuk Query Builder dan Eloquent strings. Pastikan:

1. **Input Validation**: Hanya accept trusted configuration sources
2. **Sanitization**: Validate syntax sebelum evaluation  
3. **Environment**: Consider disabling string evaluation di production
4. **Alternative**: Use object instances instead of strings when possible

```php
// 🚨 Security Risk (string evaluation)
'source' => 'DB::table("users")->where("id", $_GET["id"])'

// ✅ Secure (object instance)
$query = DB::table('users')->where('id', $validatedId);
'source' => $query
```

---

**📝 Updated:** December 2024  
**👨‍💻 Enhanced by:** CoDIY Development Team