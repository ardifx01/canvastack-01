# Incodiy Table Component - API Reference

## 📋 Complete Method Documentation

This document provides comprehensive documentation for all public methods, properties, and configuration options available in the Incodiy Table Component.

---

## Table of Contents

1. [Core Classes](#core-classes)
2. [Configuration Methods](#configuration-methods)
3. [Display Methods](#display-methods)
4. [Filtering Methods](#filtering-methods)
5. [Data Methods](#data-methods)
6. [Export Methods](#export-methods)
7. [Security Methods](#security-methods)
8. [Performance Methods](#performance-methods)
9. [Event Methods](#event-methods)
10. [Utility Methods](#utility-methods)

---

## Core Classes

### Objects Class
```php
namespace Incodiy\Codiy\Library\Components\Table;

class Objects extends Builder
{
    use Tab, Scripts, Elements;
}
```

**Primary class for table creation and management**

#### Properties
```php
public array $elements = [];           // HTML elements storage
public array $records = [];            // Data records
public array $columns = [];            // Column definitions
public array $labels = [];             // Column labels
public array $relations = [];          // Table relations
public array $filter_scripts = [];     // Filter JS/CSS
public array $hidden_columns = [];     // Hidden columns
```

---

## Configuration Methods

### `lists(string $table_name, array $columns, array $attributes = [])`

**Primary method for creating data tables**

#### Parameters
- `$table_name` (string): Name of the table/model
- `$columns` (array): Column definitions
- `$attributes` (array, optional): Additional table attributes

#### Column Definition Format
```php
// Basic formats
'column_name'                          // Simple column
'column_name:Display Label'            // With custom label
'column_name:Label|format:options'     // With formatting

// Formatting examples
'status:Status|conditional:1=Active|success,0=Inactive|danger'
'created_at:Date|date:Y-m-d H:i:s'
'price:Price|currency:IDR'
'photo:Image|image:150,150'
```

#### Attributes Options
```php
$attributes = [
    'new_button',                      // Add "New" button
    'button_name|warning|tags',        // Custom button styling
    'export_excel',                    // Enable Excel export
    'export_pdf',                      // Enable PDF export
    'export_csv'                       // Enable CSV export
];
```

#### Example Usage
```php
$this->table->lists('users', [
    'id:ID',
    'username:User Name',
    'email:Email Address',
    'status:Status|conditional:1=Active|success,0=Inactive|danger',
    'created_at:Join Date|date:Y-m-d'
], ['new_button', 'export_excel']);
```

#### Return Value
- `void` - Renders table HTML

---

### `setMethod(string $method)`

**Configure HTTP method for data requests**

#### Parameters
- `$method` (string): HTTP method ('GET' or 'POST')

#### Validation
```php
$allowedMethods = ['GET', 'POST'];
if (!in_array($method, $allowedMethods)) {
    throw new InvalidArgumentException("Method not allowed");
}
```

#### Example Usage
```php
$this->table->setMethod('POST');  // For secure operations
$this->table->setMethod('GET');   // For standard operations
```

#### Return Value
- `self` - Method chaining support

---

### `setSecureMode(bool $secure = true)`

**Enable secure mode (forces POST method)**

#### Parameters
- `$secure` (bool, optional): Enable secure mode (default: true)

#### Behavior
- When enabled, automatically sets method to POST
- Overrides any previous method configuration
- Adds additional security headers

#### Example Usage
```php
$this->table->setSecureMode(true);   // Enable secure mode
$this->table->setSecureMode(false);  // Disable secure mode
```

#### Return Value
- `self` - Method chaining support

---

### `label(string $label)`

**Set custom table title/label**

#### Parameters
- `$label` (string): Custom table label

#### Special Modifiers
- `:setLabelTable` - Removes "List(s)" suffix from title

#### Example Usage
```php
$this->table->label('System Users');
$this->table->label('Dashboard Data:setLabelTable');
```

#### Return Value
- `void`

---

## Display Methods

### `clickable(bool|string $enable = true)`

**Configure row click behavior**

#### Parameters
- `$enable` (bool|string): Enable clicking or specify action

#### Options
- `true` - Enable with default action (edit)
- `false` - Disable row clicking
- `'view'` - Click routes to view action
- `'custom_action'` - Click routes to custom action

#### Example Usage
```php
$this->table->clickable();           // Enable with edit action
$this->table->clickable(false);      // Disable clicking
$this->table->clickable('view');     // Click for view action
```

#### Return Value
- `self` - Method chaining support

---

### `sortable(array $columns = [])`

**Configure column sorting**

#### Parameters
- `$columns` (array, optional): Specific columns to enable sorting

#### Modes
```php
// Enable sorting on all columns
$this->table->sortable();

// Enable on specific columns
$this->table->sortable(['username', 'created_at']);

// Disable on specific columns  
$this->table->sortable(['password'], 'exclude');
```

#### Example Usage
```php
$this->table->sortable(['username', 'email', 'created_at']);
```

#### Return Value
- `self` - Method chaining support

---

### `orderby(string|array $column, string $direction = 'ASC')`

**Set default table sorting**

#### Parameters
- `$column` (string|array): Column name or array of sort conditions
- `$direction` (string, optional): Sort direction ('ASC' or 'DESC')

#### Single Column Sort
```php
$this->table->orderby('created_at', 'DESC');
$this->table->orderby('username', 'ASC');
```

#### Multiple Column Sort
```php
$this->table->orderby([
    ['created_at', 'DESC'],
    ['username', 'ASC']
]);
```

#### Return Value
- `self` - Method chaining support

---

### `hideColumns(array $columns)`

**Hide specific columns from display**

#### Parameters
- `$columns` (array): Array of column names to hide

#### Example Usage
```php
$this->table->hideColumns(['id', 'password_hash', 'internal_notes']);
```

#### Use Cases
- Hide sensitive information
- Hide technical/system columns
- Responsive design adjustments

#### Return Value
- `self` - Method chaining support

---

### `showColumns(array $columns)`

**Show only specified columns (hide all others)**

#### Parameters
- `$columns` (array): Array of column names to display

#### Example Usage
```php
$this->table->showColumns(['username', 'email', 'status']);
```

#### Return Value
- `self` - Method chaining support

---

### `displayRowsLimitOnLoad(int $limit)`

**Set initial pagination limit**

#### Parameters
- `$limit` (int): Number of rows to display per page

#### Example Usage
```php
$this->table->displayRowsLimitOnLoad(25);
```

#### Common Values
- `10` - Small datasets
- `25` - Default recommended
- `50` - Medium datasets
- `100` - Large datasets

#### Return Value
- `void`

---

## Filtering Methods

### `filterGroups(string $column, string $type, bool|array $relate = false)`

**Create filterable columns with input types**

#### Parameters
- `$column` (string): Column name to filter
- `$type` (string): Filter input type
- `$relate` (bool|array): Enable relations or specify columns

#### Filter Types
| Type | Description | Use Case |
|------|-------------|----------|
| `selectbox` | Dropdown selection | Status, categories |
| `text` | Text input | Names, descriptions |
| `daterange` | Date range picker | Date filtering |
| `numberrange` | Number range | Numeric filtering |
| `multiselect` | Multiple selection | Tags, categories |
| `boolean` | Yes/No toggle | Boolean values |

#### Example Usage
```php
$this->table->filterGroups('status', 'selectbox', true);
$this->table->filterGroups('username', 'text', true);
$this->table->filterGroups('created_at', 'daterange', true);
$this->table->filterGroups('tags', 'multiselect', ['tag1', 'tag2']);
```

#### Return Value
- `self` - Method chaining support

---

### `searchable(array $columns = [])`

**Configure searchable columns**

#### Parameters
- `$columns` (array, optional): Columns to make searchable

#### Search All Columns
```php
$this->table->searchable();
```

#### Search Specific Columns
```php
$this->table->searchable(['username', 'email', 'fullname']);
```

#### Advanced Search Configuration
```php
$this->table->searchable([
    'username' => 'exact',      // Exact match
    'email' => 'partial',       // Partial match (default)
    'phone' => 'starts_with'    // Starts with match
]);
```

#### Return Value
- `self` - Method chaining support

---

### `relations(object $model, string $type, string $field, array $relations)`

**Setup table relations for data joining**

#### Parameters
- `$model` (object): Model instance
- `$type` (string): Relation type identifier
- `$field` (string): Field name for relation data
- `$relations` (array): Relation mapping

#### Relation Mapping Format
```php
$relations = [
    'foreign_table.foreign_key' => 'local_table.local_key',
    'another_table.id' => 'foreign_table.another_id'
];
```

#### Example Usage
```php
// User groups relation
$this->table->relations($this->model, 'group', 'group_info', [
    'user_groups.user_id' => 'users.id',
    'groups.id' => 'user_groups.group_id'
]);

// Profile relation
$this->table->relations($this->model, 'profile', 'profile_data', [
    'user_profiles.user_id' => 'users.id'
]);
```

#### Return Value
- `void`

---

## Data Methods

### `setModel(string|object $model)`

**Set data source model**

#### Parameters
- `$model` (string|object): Model class name or instance

#### Usage with Eloquent Model
```php
$this->table->setModel(User::class);
$this->table->setModel(new User());
```

#### Usage with Custom SQL
```php
$this->table->setModel('sql')->setQuery("
    SELECT u.*, g.name as group_name
    FROM users u
    LEFT JOIN user_groups ug ON u.id = ug.user_id  
    LEFT JOIN groups g ON ug.group_id = g.id
    WHERE u.active = 1
");
```

#### Return Value
- `self` - Method chaining support

---

### `connection(string $connection_name)`

**Set database connection**

#### Parameters
- `$connection_name` (string): Connection name from config

#### Example Usage
```php
$this->table->connection('mysql_secondary');
$this->table->connection('postgresql_reports');
```

#### Return Value
- `self` - Method chaining support

---

### `transform(string $column, callable $callback)`

**Transform column data before display**

#### Parameters
- `$column` (string): Column name to transform
- `$callback` (callable): Transformation function

#### Callback Parameters
- `$value` - Column value
- `$row` - Complete row data

#### Example Usage
```php
$this->table->transform('status', function($value, $row) {
    return $value == 1 ? 'Active' : 'Inactive';
});

$this->table->transform('full_name', function($value, $row) {
    return $row->first_name . ' ' . $row->last_name;
});
```

#### Return Value
- `self` - Method chaining support

---

### `aggregate(string $column, string $function)`

**Add aggregate calculations**

#### Parameters
- `$column` (string): Column to aggregate
- `$function` (string): Aggregate function

#### Available Functions
- `SUM` - Sum all values
- `AVG` - Average of values
- `COUNT` - Count records
- `MIN` - Minimum value
- `MAX` - Maximum value

#### Example Usage
```php
$this->table->aggregate('price', 'SUM');
$this->table->aggregate('quantity', 'AVG');
$this->table->aggregate('orders', 'COUNT');
```

#### Return Value
- `self` - Method chaining support

---

## Export Methods

### `exportButtons(array $formats)`

**Enable data export functionality**

#### Parameters
- `$formats` (array): Export formats and configurations

#### Simple Format List
```php
$this->table->exportButtons(['excel', 'pdf', 'csv', 'print']);
```

#### Advanced Configuration
```php
$this->table->exportButtons([
    'excel' => [
        'filename' => 'users-export-' . date('Y-m-d'),
        'title' => 'User Management Report',
        'columns' => ':visible:not(:last-child)'
    ],
    'pdf' => [
        'orientation' => 'landscape',
        'pageSize' => 'A4',
        'title' => 'User Report'
    ],
    'csv' => [
        'separator' => ';',
        'filename' => 'users.csv'
    ]
]);
```

#### Return Value
- `self` - Method chaining support

---

### `actionButtons(array $buttons)`

**Configure row-level action buttons**

#### Parameters
- `$buttons` (array): Button configurations

#### Standard Buttons
```php
$this->table->actionButtons(['view', 'edit', 'delete']);
```

#### Custom Button Configuration
```php
$this->table->actionButtons([
    'approve' => [
        'icon' => 'fa-check',
        'class' => 'btn-success btn-sm',
        'route' => 'user.approve',
        'permission' => 'users.approve',
        'confirm' => 'Are you sure you want to approve this user?'
    ],
    'suspend' => [
        'icon' => 'fa-ban', 
        'class' => 'btn-warning btn-sm',
        'route' => 'user.suspend',
        'permission' => 'users.suspend'
    ]
]);
```

#### Return Value
- `self` - Method chaining support

---

### `removeActionButtons(array $buttons)`

**Remove specific action buttons**

#### Parameters
- `$buttons` (array): Array of button names to remove

#### Example Usage
```php
$this->table->removeActionButtons(['delete']);     // Remove delete button
$this->table->removeActionButtons(['add']);        // Remove "New" button
$this->table->removeActionButtons(['edit', 'delete']); // Remove multiple
```

#### Return Value
- `self` - Method chaining support

---

## Security Methods

### `permissions(array $permissions)`

**Set permission requirements for actions**

#### Parameters
- `$permissions` (array): Permission mappings

#### Permission Configuration
```php
$this->table->permissions([
    'view' => 'users.view',
    'edit' => 'users.edit',
    'delete' => 'users.delete',
    'export' => 'users.export',
    'create' => 'users.create'
]);
```

#### Return Value
- `self` - Method chaining support

---

### `secureColumns(array $columns)`

**Mark columns as sensitive/secure**

#### Parameters
- `$columns` (array): Array of sensitive column names

#### Example Usage
```php
$this->table->secureColumns(['password', 'api_key', 'ssn', 'bank_account']);
```

#### Effects
- Hidden in exports
- Hidden in public views
- Additional access control applied

#### Return Value
- `self` - Method chaining support

---

## Performance Methods

### `cache(array $options)`

**Enable result caching**

#### Parameters
- `$options` (array): Caching configuration

#### Cache Configuration
```php
$this->table->cache([
    'ttl' => 300,                    // Time to live (seconds)
    'key' => 'users_table_data',     // Cache key
    'tags' => ['users', 'table'],    // Cache tags for invalidation
    'driver' => 'redis'              // Cache driver (optional)
]);
```

#### Return Value
- `self` - Method chaining support

---

### `serverSideProcessing(bool|array $enable = true)`

**Enable server-side processing for large datasets**

#### Parameters
- `$enable` (bool|array): Enable or configuration array

#### Simple Enable
```php
$this->table->serverSideProcessing(true);
```

#### Advanced Configuration
```php
$this->table->serverSideProcessing([
    'url' => route('users.datatable'),
    'method' => 'POST',
    'chunk_size' => 1000,
    'cache_results' => true
]);
```

#### Return Value
- `self` - Method chaining support

---

### `lazy(bool $enable = true)`

**Enable lazy loading**

#### Parameters
- `$enable` (bool): Enable lazy loading

#### Example Usage
```php
$this->table->lazy(true);  // Enable lazy loading
```

#### Benefits
- Faster initial page load
- Reduced memory usage
- Better user experience

#### Return Value
- `self` - Method chaining support

---

### `chunk(int $size)`

**Process data in chunks**

#### Parameters
- `$size` (int): Chunk size for processing

#### Example Usage
```php
$this->table->chunk(1000);  // Process 1000 records at a time
```

#### Return Value
- `self` - Method chaining support

---

## Event Methods

### `onInit(callable $callback)`

**Execute callback on table initialization**

#### Parameters
- `$callback` (callable): Initialization callback

#### Example Usage
```php
$this->table->onInit(function($table) {
    // Custom initialization logic
    $table->addCustomScript('console.log("Table initialized");');
});
```

#### Return Value
- `self` - Method chaining support

---

### `onFilter(callable $callback)`

**Execute callback when filters are applied**

#### Parameters
- `$callback` (callable): Filter callback

#### Example Usage
```php
$this->table->onFilter(function($filters, $table) {
    // Log filter usage
    Log::info('Table filtered', ['filters' => $filters]);
});
```

#### Return Value
- `self` - Method chaining support

---

### `onError(callable $callback)`

**Execute callback on table errors**

#### Parameters
- `$callback` (callable): Error handling callback

#### Example Usage
```php
$this->table->onError(function($exception, $context) {
    Log::error('Table error: ' . $exception->getMessage(), $context);
});
```

#### Return Value
- `self` - Method chaining support

---

## Utility Methods

### `debug(bool $enable = true)`

**Enable debug mode**

#### Parameters
- `$enable` (bool): Enable debug mode

#### Debug Output Includes
- SQL queries generated
- Filter parameters applied
- Performance metrics
- Memory usage statistics

#### Example Usage
```php
$this->table->debug(true);
```

#### Return Value
- `self` - Method chaining support

---

### `addCustomScript(string $script)`

**Add custom JavaScript**

#### Parameters
- `$script` (string): JavaScript code to add

#### Example Usage
```php
$this->table->addCustomScript('
    $(document).ready(function() {
        console.log("Custom script executed");
    });
');
```

#### Return Value
- `self` - Method chaining support

---

### `addCustomCSS(string $css)`

**Add custom CSS styles**

#### Parameters
- `$css` (string): CSS code to add

#### Example Usage
```php
$this->table->addCustomCSS('
    .my-table .status-active { 
        color: green; 
        font-weight: bold; 
    }
');
```

#### Return Value
- `self` - Method chaining support

---

### `getTableId()`

**Get generated table ID**

#### Return Value
- `string` - Unique table identifier

#### Example Usage
```php
$tableId = $this->table->getTableId();
echo "Table ID: " . $tableId;
```

---

### `getConfiguration()`

**Get current table configuration**

#### Return Value
- `array` - Complete configuration array

#### Example Usage
```php
$config = $this->table->getConfiguration();
var_dump($config);
```

---

## Method Chaining Examples

The API supports extensive method chaining for clean, readable code:

```php
$this->table
    ->setMethod('POST')
    ->setSecureMode(true)
    ->searchable(['username', 'email'])
    ->sortable(['created_at', 'username'])
    ->filterGroups('status', 'selectbox', true)
    ->filterGroups('created_at', 'daterange', true)
    ->orderby('created_at', 'DESC')
    ->clickable()
    ->exportButtons(['excel', 'pdf'])
    ->cache(['ttl' => 300])
    ->lists('users', [
        'username:User Name',
        'email:Email Address', 
        'status:Status|conditional:1=Active|success,0=Inactive|danger',
        'created_at:Join Date|date:Y-m-d'
    ]);
```

---

*This API reference covers all public methods available in the Incodiy Table Component. For implementation examples and best practices, refer to the main [README](README.md) and [Features Documentation](FEATURES_DOCUMENTATION.md).*