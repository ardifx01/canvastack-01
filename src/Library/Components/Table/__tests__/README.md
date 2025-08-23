# Table System Test Suite

This directory contains comprehensive tests for the Table System components, specifically focusing on the fixes implemented in **v2.3.1** to resolve the "prepare() on null" error with temp tables.

## Directory Structure

```
__tests__/
├── README.md                           # This file
├── run_all_tests.php                   # Test suite runner
├── TempTables/                         # Tests for temp table handling
│   ├── TempTableModelCreationTest.php  # Model creation for temp tables
│   └── SetupPaginationFixTest.php      # Pagination setup fix verification
├── Integration/                        # Integration tests
│   └── FullDataTablesFlowTest.php      # Complete DataTables flow test
├── Craft/                             # Component-specific tests
│   └── DatatablesModelMappingTest.php  # Model mapping logic tests
├── Relations/                          # Relation system tests
│   └── UserRelationTest.php           # User model relation verification
└── UserActivity/                       # UserActivity-specific tests
    └── UserActivityTempTableTest.php   # UserActivity temp table tests
```

## Test Categories

### 1. TempTables Tests
**Purpose**: Verify temp table handling fixes

- **TempTableModelCreationTest.php**: Tests that temp tables use Query Builder instead of Eloquent Builder
- **SetupPaginationFixTest.php**: Verifies that setupPagination no longer throws "prepare() on null" errors

### 2. Integration Tests
**Purpose**: End-to-end testing of DataTables processing

- **FullDataTablesFlowTest.php**: Tests complete DataTables flow including Enhanced Architecture fallback to Legacy processing

### 3. Craft Tests
**Purpose**: Component-specific unit tests

- **DatatablesModelMappingTest.php**: Tests model mapping logic in tryCreateSpecificModel method

### 4. Relations Tests
**Purpose**: Verify relation system functionality

- **UserRelationTest.php**: Tests User model relations and Zero-Configuration compatibility

### 5. UserActivity Tests
**Purpose**: UserActivity-specific functionality tests

- **UserActivityTempTableTest.php**: Tests UserActivity temp table creation and processing

## Critical Fix Verified (v2.3.1)

### Problem Solved
- **Error**: `Call to a member function prepare() on null` in Connection.php
- **Root Cause**: Temp tables were mapped to User model but User model uses 'users' table, not temp table names
- **Impact**: DataTables processing crashed when working with temp tables

### Solution Implemented
- **Detection**: Identify tables with 'temp_' prefix
- **Query Builder**: Create `\DB::table($tableName)` directly for temp tables
- **Connection Validation**: Ensure database connection is valid before returning Query Builder
- **Eloquent Preservation**: Keep Eloquent Builder for regular tables to maintain relation support

## Running Tests

### Individual Tests
```bash
# Run specific test
php vendor/incodiy/codiy/src/Library/Components/Table/__tests__/TempTables/TempTableModelCreationTest.php

# Run pagination fix test
php vendor/incodiy/codiy/src/Library/Components/Table/__tests__/TempTables/SetupPaginationFixTest.php

# Run integration test
php vendor/incodiy/codiy/src/Library/Components/Table/__tests__/Integration/FullDataTablesFlowTest.php

# Run model mapping test
php vendor/incodiy/codiy/src/Library/Components/Table/__tests__/Craft/DatatablesModelMappingTest.php
```

### All Tests (Manual)
```bash
# Run all temp table tests
php vendor/incodiy/codiy/src/Library/Components/Table/__tests__/TempTables/TempTableModelCreationTest.php
php vendor/incodiy/codiy/src/Library/Components/Table/__tests__/TempTables/SetupPaginationFixTest.php

# Run integration tests
php vendor/incodiy/codiy/src/Library/Components/Table/__tests__/Integration/FullDataTablesFlowTest.php

# Run craft tests
php vendor/incodiy/codiy/src/Library/Components/Table/__tests__/Craft/DatatablesModelMappingTest.php
```

## Expected Results

### ✅ Success Indicators
- **Model Creation**: Temp tables create Query Builder, regular tables create Eloquent Builder
- **Connection Validation**: All Query Builders have valid database connections
- **Count Operations**: No "prepare() on null" errors when calling count()
- **Pagination Setup**: setupPagination completes successfully with record counts

### ⚠️ Warning Indicators
- **Enhanced → Legacy Fallback**: Expected for temp tables (no registry configuration)
- **Debug Logs**: Enhanced Architecture attempts then falls back to Legacy processing

### ❌ Failure Indicators
- **Null Connections**: Query Builder without database connection
- **Wrong Builder Types**: Temp tables using Eloquent Builder or vice versa
- **Prepare Errors**: Any "prepare() on null" errors indicate incomplete fix

## Test Environment Requirements

- **Laravel Application**: Bootstrapped Laravel environment
- **Database Connection**: Valid MySQL/database connection
- **UserActivity Model**: Available for temp table creation
- **Debug Mode**: Enabled for detailed logging (`config(['datatables.debug' => true])`)

## Debugging

### Log Files
Check `storage/logs/laravel.log` for detailed debug information:
- Model creation logs
- Connection validation logs
- Enhanced Architecture → Legacy fallback logs
- Error traces if any

### Common Issues
1. **Bootstrap Errors**: Ensure Laravel is properly bootstrapped
2. **Database Connection**: Verify database credentials in .env
3. **Missing Models**: Ensure UserActivity and related models are available
4. **Permission Issues**: Check file permissions for log writing

## Version History

- **v2.3.1**: Initial test suite creation with temp table fix verification
- **v2.3.0**: Base DataTables functionality (before temp table fix)

## Contributing

When adding new tests:
1. Follow the existing naming convention
2. Include comprehensive documentation
3. Add appropriate test categories
4. Update this README with new test descriptions
5. Ensure tests are self-contained and don't interfere with each other

## Related Documentation

- `../docs/DEVELOPMENT_SUMMARY_AND_NEXT_ENHANCEMENTS.md`
- `../docs/LAST DEVELOPMENT PROGRESS 3.md`
- `../CHANGELOG.md`