# Data Providers Configuration Guide

## 📋 Overview

The `config/data-providers.php` configuration file is an **optional** configuration file that provides advanced customization capabilities for the Incodiy Table Component. This file allows you to override auto-discovery behavior and fine-tune table configurations for specific use cases.

---

## 🎯 Purpose & Function

### What is config/data-providers.php?

This configuration file serves as an **override system** for the table component's intelligent auto-discovery mechanism. While the system can automatically detect and configure most table setups, this file allows you to:

1. **Override auto-discovery results** when defaults don't meet your needs
2. **Define complex relationships** that can't be auto-detected  
3. **Specify custom column operators** beyond the standard LIKE/whereIn logic
4. **Configure advanced table behaviors** for edge cases
5. **Optimize performance** for high-volume tables with specific requirements

### Is This File Required?

**❌ NO** - This file is **completely optional**!

The system follows this priority logic:
```
1. Check config/data-providers.php (if exists and has entry for table)
2. If no config → Use Auto-Discovery (90%+ of cases work perfectly)
3. Auto-Discovery: Model + Database Schema = Zero Configuration Required
```

---

## 🚀 Zero-Configuration vs Manual Configuration

### 🟢 Zero-Configuration (Recommended for 90%+ cases)

**What you need:** Only a Model class
```php
// File: App/Models/YourModel.php
class TrikomWireless extends Model {
    protected $connection = 'mysql_mantra_etl';
    protected $table = 'view_report_data_summary_trikom_wireless';
    // That's it! Auto-discovery handles everything else
}
```

**Auto-discovery provides:**
- ✅ Connection detection from model
- ✅ Schema detection from database  
- ✅ Primary key detection (or null for views)
- ✅ Intelligent column selection
- ✅ Smart ordering (date/period columns, not 'id')
- ✅ Pattern-based searchable columns
- ✅ Automatic relationship detection

### 🟡 Manual Configuration (for 10% edge cases)

**When you might need manual config:**
- Complex custom relationships
- Specific column operator requirements (exact match vs LIKE)
- Performance optimization for massive datasets
- Custom formatting or validation rules
- Multi-table joins with specific aliasing

---

## 🛠️ Technical Implementation

### File Location & Structure

```php
// File: config/data-providers.php
<?php

return [
    // Table name => Configuration
    'your_table_name' => [
        'class' => 'App\\Models\\YourModel',
        'connection' => 'mysql_connection',
        'primary_key' => 'id', // or null for views
        'default_columns' => ['col1', 'col2', 'col3'],
        'searchable_columns' => ['col1', 'col2'],
        'sortable_columns' => ['col1', 'col3'],
        'default_order' => ['created_at', 'desc'],
        'relationships' => [
            // Complex relationship definitions
        ],
        'column_operators' => [
            'name' => 'LIKE',           // Partial matching
            'email' => 'exact',         // Exact matching  
            'created_at' => 'range',    // Date range
            'category_id' => 'IN',      // Multiple values
        ],
        'formatters' => [
            // Custom column formatters
        ],
        'export_options' => [
            // Export-specific configurations
        ],
    ],
];
```

### Configuration Options Explained

#### Basic Configuration
```php
'table_name' => [
    // Model class to use for this table
    'class' => 'App\\Models\\YourModel',
    
    // Database connection (should match your .env)
    'connection' => 'mysql_connection_name',
    
    // Primary key column (use null for views without primary keys)
    'primary_key' => 'id', // or null
    
    // Default columns to display (auto-detected if not specified)
    'default_columns' => ['id', 'name', 'email', 'created_at'],
    
    // Which columns are searchable (auto-detected based on patterns)
    'searchable_columns' => ['name', 'email'],
    
    // Which columns can be sorted (auto-detected if not specified)
    'sortable_columns' => ['id', 'name', 'created_at'],
    
    // Default ordering [column, direction]
    'default_order' => ['created_at', 'desc'],
],
```

#### Advanced Relationship Configuration
```php
'users' => [
    'class' => 'App\\Models\\User',
    'relationships' => [
        // Join with user groups
        [
            'table' => 'base_user_group',
            'local_key' => 'id',
            'foreign_key' => 'user_id',
            'join_type' => 'left',
        ],
        [
            'table' => 'base_group',
            'local_key' => 'base_user_group.group_id', 
            'foreign_key' => 'id',
            'join_type' => 'left',
            'select' => ['group_name', 'group_alias', 'group_info'],
        ],
    ],
],
```

#### Column Operator Configuration
```php
'products' => [
    'class' => 'App\\Models\\Product',
    'column_operators' => [
        'name' => 'LIKE',              // Partial search: WHERE name LIKE '%search%'
        'sku' => 'exact',              // Exact match: WHERE sku = 'search'
        'price' => 'range',            // Range: WHERE price BETWEEN min AND max  
        'category_id' => 'IN',         // Multiple: WHERE category_id IN (1,2,3)
        'created_at' => 'date_range',  // Date range with proper formatting
        'description' => 'fulltext',   // Full-text search (if supported)
    ],
],
```

---

## 📝 Use Cases & Examples

### Use Case 1: View Tables Without Primary Keys
**Problem:** Database views don't have primary keys, causing "Unknown column 'id'" errors.

**Solution with Auto-Discovery:**
```php
// Model only - no config needed!
class ReportSummary extends Model {
    protected $table = 'view_report_summary';
    protected $connection = 'mysql_reports';
    // Auto-discovery detects no primary key and uses appropriate ordering
}
```

**Solution with Manual Config (if auto-discovery doesn't work):**
```php
// config/data-providers.php
'view_report_summary' => [
    'class' => 'App\\Models\\ReportSummary',
    'primary_key' => null, // Explicitly set to null
    'default_order' => ['report_date', 'desc'], // Use date instead of id
],
```

### Use Case 2: Complex Multi-Table Relationships
**Problem:** Need to join 3+ tables with specific aliasing.

**Manual Config Required:**
```php
'user_permissions' => [
    'class' => 'App\\Models\\User',
    'relationships' => [
        [
            'table' => 'user_roles',
            'local_key' => 'id',
            'foreign_key' => 'user_id',
            'alias' => 'ur',
        ],
        [
            'table' => 'roles',
            'local_key' => 'ur.role_id',
            'foreign_key' => 'id', 
            'alias' => 'r',
            'select' => ['r.name as role_name'],
        ],
        [
            'table' => 'role_permissions',
            'local_key' => 'r.id',
            'foreign_key' => 'role_id',
            'alias' => 'rp',
        ],
        [
            'table' => 'permissions',
            'local_key' => 'rp.permission_id',
            'foreign_key' => 'id',
            'alias' => 'p',
            'select' => ['p.name as permission_name'],
        ],
    ],
],
```

### Use Case 3: Performance Optimization
**Problem:** Large table with specific indexing requirements.

**Optimized Config:**
```php
'large_transactions' => [
    'class' => 'App\\Models\\Transaction',
    'primary_key' => 'transaction_id',
    'default_columns' => [
        'transaction_id', 'amount', 'created_at' // Limit columns for performance
    ],
    'searchable_columns' => [
        'transaction_id', // Indexed column only
    ],
    'column_operators' => [
        'transaction_id' => 'exact',    // Exact match for indexed column
        'amount' => 'range',            // Range for numeric queries
        'created_at' => 'date_range',   // Proper date range handling
    ],
    'default_order' => ['created_at', 'desc'], // Use indexed timestamp
    'cache_duration' => 300, // 5-minute cache for heavy queries
],
```

### Use Case 4: Custom Column Operators
**Problem:** Need different search behaviors for different columns.

**Custom Operators:**
```php
'products' => [
    'class' => 'App\\Models\\Product',
    'column_operators' => [
        'sku' => 'exact',              // SKU must match exactly
        'name' => 'LIKE',              // Product name partial search
        'description' => 'fulltext',   // Full-text search capability
        'price' => 'range',            // Price range filtering  
        'category_id' => 'IN',         // Multiple category selection
        'is_active' => 'boolean',      // Boolean true/false
        'created_at' => 'date_range',  // Date range with formatting
    ],
],
```

---

## ⚡ Performance Considerations

### When Auto-Discovery Might Be Slower
1. **Very Large Tables** (1M+ rows): Manual column selection can improve performance
2. **Complex Schemas** (50+ columns): Specify only needed columns
3. **Heavy Relationships** (5+ joins): Manual relationship optimization
4. **Real-time Applications**: Cache duration configuration

### Performance Optimization Tips
```php
'optimized_table' => [
    'class' => 'App\\Models\\OptimizedModel',
    
    // Limit columns to reduce memory usage
    'default_columns' => ['id', 'name', 'status', 'created_at'],
    
    // Index-based searchable columns only
    'searchable_columns' => ['name'], // Only if 'name' has index
    
    // Use exact matches for indexed columns
    'column_operators' => [
        'id' => 'exact',
        'status' => 'exact',
        'name' => 'LIKE', // Only if needed
    ],
    
    // Cache heavy queries
    'cache_duration' => 600, // 10 minutes
    
    // Optimize ordering
    'default_order' => ['id', 'desc'], // Use primary key when possible
],
```

---

## 🔧 Integration with .env Configuration

### Database Connection Configuration
Your `config/data-providers.php` should reference the same connection names defined in your `.env`:

**.env file:**
```bash
# Main application database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=main_app

# ETL/Reports database  
DB_CONNECTION_MANTRA_ETL=mysql
DB_HOST_MANTRA_ETL=127.0.0.1
DB_DATABASE_MANTRA_ETL=mantra_etl
```

**config/data-providers.php:**
```php
'report_tables' => [
    'class' => 'App\\Models\\Report',
    'connection' => 'mysql_mantra_etl', // Must match .env name
],
```

### Environment-Specific Configurations
```php
// Different configs per environment
'users' => [
    'class' => 'App\\Models\\User',
    'cache_duration' => env('APP_ENV') === 'production' ? 300 : 0,
    'debug_sql' => env('APP_DEBUG', false),
],
```

---

## 🚨 Common Issues & Solutions

### Issue 1: "Unknown column 'id'" Error
**Cause:** Table/view doesn't have 'id' column, but system defaulting to 'id' ordering.

**Solutions:**
1. **Auto-Discovery Fix:** Usually handles this automatically in v2.3.0+
2. **Manual Fix:** Set `'primary_key' => null` and specify `'default_order'`

```php
'problematic_view' => [
    'primary_key' => null,
    'default_order' => ['date_column', 'desc'],
],
```

### Issue 2: Connection Not Found
**Cause:** Connection name mismatch between model/config and database config.

**Solution:** Verify connection names match:
```php
// Model
protected $connection = 'mysql_mantra_etl';

// Config
'connection' => 'mysql_mantra_etl', // Must match exactly

// .env
DB_CONNECTION_MANTRA_ETL=mysql // Must be configured
```

### Issue 3: Relationships Not Working
**Cause:** Complex relationships require manual configuration.

**Solution:** Define explicit relationships:
```php
'complex_table' => [
    'relationships' => [
        [
            'table' => 'related_table',
            'local_key' => 'foreign_id',
            'foreign_key' => 'id',
            'join_type' => 'left',
            'select' => ['column1', 'column2'],
        ],
    ],
],
```

### Issue 4: Performance Problems
**Cause:** Auto-discovery including too many columns or inefficient queries.

**Solution:** Optimize with manual configuration:
```php
'large_table' => [
    'default_columns' => ['id', 'name', 'status'], // Limit columns
    'searchable_columns' => ['name'],               // Indexed columns only
    'cache_duration' => 300,                       // Add caching
],
```

---

## 🔄 Migration from Manual to Auto-Discovery

If you have existing manual configurations and want to try auto-discovery:

### Step 1: Backup Existing Config
```bash
cp config/data-providers.php config/data-providers.php.backup
```

### Step 2: Test Auto-Discovery
Comment out or remove specific table entries and test:
```php
return [
    // Comment out to test auto-discovery
    // 'your_table' => [...],
];
```

### Step 3: Compare Results
- Test functionality with auto-discovery
- Check performance differences
- Verify all features work as expected

### Step 4: Keep Only Necessary Overrides
Keep manual config only for:
- Complex relationships that auto-discovery can't handle
- Performance-critical optimizations  
- Specific business logic requirements

---

## 📊 Configuration Decision Matrix

| Scenario | Auto-Discovery | Manual Config | Recommendation |
|----------|---------------|---------------|----------------|
| Simple table with standard columns | ✅ Perfect | ❌ Overkill | Use Auto-Discovery |
| View without primary key | ✅ Handles well | ✅ Works | Use Auto-Discovery |
| Complex 3+ table joins | ❌ Limited | ✅ Required | Use Manual Config |
| Performance-critical (1M+ rows) | ⚠️ May be slow | ✅ Optimized | Use Manual Config |
| Standard CRUD operations | ✅ Ideal | ❌ Unnecessary | Use Auto-Discovery |
| Custom search operators needed | ❌ Limited | ✅ Full control | Use Manual Config |
| Rapid development/prototyping | ✅ Fastest | ❌ Time-consuming | Use Auto-Discovery |
| Production optimization | ⚠️ Good enough | ✅ Best | Use Manual Config for critical tables |

---

## 🎯 Best Practices

### 1. Start with Auto-Discovery
Always start with auto-discovery and only add manual config when needed:
```php
// Don't do this first - try auto-discovery!
// 'new_table' => [...complex config...]

// Do this - let auto-discovery handle it initially
// Then add manual config only if needed
```

### 2. Progressive Configuration
Add configuration incrementally:
```php
// Step 1: Basic override
'table_name' => [
    'primary_key' => null,
],

// Step 2: Add more as needed
'table_name' => [
    'primary_key' => null,
    'default_order' => ['date', 'desc'],
],

// Step 3: Full configuration if required
'table_name' => [
    'primary_key' => null,
    'default_order' => ['date', 'desc'],
    'relationships' => [...],
    'column_operators' => [...],
],
```

### 3. Environment-Specific Configs
Use environment variables for environment-specific settings:
```php
'table_name' => [
    'cache_duration' => env('TABLE_CACHE_DURATION', 300),
    'debug_sql' => env('APP_DEBUG', false),
    'max_results' => env('TABLE_MAX_RESULTS', 1000),
],
```

### 4. Documentation and Comments
Document complex configurations:
```php
'complex_reports' => [
    'class' => 'App\\Models\\ComplexReport',
    
    // Performance: Limit columns due to large dataset (2M+ rows)
    'default_columns' => ['id', 'name', 'status', 'created_at'],
    
    // Relationships: Custom join for legacy table compatibility  
    'relationships' => [
        // Legacy table uses non-standard foreign key naming
        [
            'table' => 'legacy_statuses',
            'local_key' => 'status_code', // Non-standard field name
            'foreign_key' => 'code',      // Different from typical 'id'
        ],
    ],
],
```

---

## 🚀 Future Enhancements

### Planned Features (v2.4.0+)
- **Visual Configuration Builder**: Web-based interface for creating configurations
- **Performance Analytics**: Automatic detection of performance issues with suggestions
- **Schema Versioning**: Configuration versioning with migration support
- **Advanced Caching**: Intelligent cache invalidation and warming
- **Query Optimization**: Automatic query plan analysis and optimization suggestions

### Community Contributions
We welcome contributions to improve the configuration system:
- New column operators
- Performance optimization patterns
- Integration with other Laravel packages
- Documentation improvements

---

## 📞 Support & Resources

### Getting Help
- **Documentation Issues**: [GitHub Issues](https://github.com/incodiy/table-component/issues)
- **Configuration Questions**: [GitHub Discussions](https://github.com/incodiy/table-component/discussions)  
- **Performance Problems**: Include your config and query examples in issue reports

### Related Documentation
- [INDEX.md](INDEX.md) - Main documentation index
- [FEATURES_DOCUMENTATION.md](FEATURES_DOCUMENTATION.md) - Complete feature guide
- [CHANGELOG.md](CHANGELOG.md) - Version history and changes
- [API_REFERENCE.md](API_REFERENCE.md) - Method documentation

---

*This guide covers all aspects of the config/data-providers.php configuration file. For additional information or specific use cases not covered here, please refer to our [GitHub repository](https://github.com/incodiy/table-component) or contact our support team.*