# Incodiy Table Component - Complete Features Documentation

## 📋 Table of Contents

1. [Overview](#overview)
2. [Core Architecture](#core-architecture)
3. [Table Configuration Methods](#table-configuration-methods)
4. [Filtering & Search Features](#filtering--search-features)
5. [Display & Formatting Options](#display--formatting-options)
6. [Data Manipulation & Relations](#data-manipulation--relations)
7. [Export & Action Buttons](#export--action-buttons)
8. [Advanced Features](#advanced-features)
9. [JavaScript Integration](#javascript-integration)
10. [Security Features](#security-features)
11. [Performance Optimization](#performance-optimization)
12. [Customization Options](#customization-options)

---

## Overview

The Incodiy Table Component is a comprehensive data table management system built on top of DataTables.js with extensive server-side integration, filtering capabilities, and advanced data manipulation features.

### Key Capabilities
- ✅ **Server-side & Client-side Processing**
- ✅ **Advanced Filtering & Search System**
- ✅ **Multi-format Export (Excel, PDF, CSV)**
- ✅ **Real-time Data Relations & Joins**
- ✅ **Chart Integration & Visualization**
- ✅ **Security Features (CSRF, Method Control)**
- ✅ **Mobile Responsive Design**
- ✅ **Customizable UI Components**

---

## Core Architecture

### Class Structure
```php
namespace Incodiy\Codiy\Library\Components\Table;

class Objects extends Builder {
    use Tab, Scripts, Elements;
    
    // Core Properties
    public array $elements = [];           // HTML elements storage
    public array $records = [];            // Data records
    public array $columns = [];            // Column definitions  
    public array $labels = [];             // Column labels
    public array $relations = [];          // Table relations
    public array $filter_scripts = [];     // Filter JavaScript/CSS
    public array $hidden_columns = [];     // Hidden column management
}
```

### Inheritance Chain
```
Objects → Builder → Scripts → Elements → Tab
                ↓
         Craft\Datatables
         Craft\Export  
         Craft\Search
         Craft\Formula
```

---

## Table Configuration Methods

### 1. Basic Table Setup

#### `lists($table_name, $columns, $attributes = [])`
**Primary method for creating data tables**

```php
// Basic usage
$this->table->lists('users', [
    'username:User Name',
    'email:Email Address', 
    'group_name:User Group',
    'created_at:Join Date'
]);

// With attributes
$this->table->lists('users', $columns, [
    'new_button',           // Add "New" button
    'button_name|warning|tags',  // Custom button with styling
    'export_excel',         // Enable Excel export
    'export_pdf'           // Enable PDF export  
]);
```

#### `setMethod($method)` & Method Control
**Configure HTTP method for data requests**

```php
// Set to POST (secure operations)
$this->table->setMethod('POST');

// Set to GET (standard operations) 
$this->table->setMethod('GET');

// Enable secure mode (forces POST)
$this->table->setSecureMode(true);

// Method validation
$allowed = ['GET', 'POST'];
if (!in_array($method, $allowed)) {
    throw new InvalidArgumentException("Method not allowed");
}
```

#### `label($label)` & Title Management
```php
// Set custom table label
$this->table->label('System Users Management');

// Disable "List(s)" suffix
$this->table->label('Dashboard Data:setLabelTable');
```

### 2. Column Configuration

#### Column Definition Syntax
```php
// Basic column
'column_name'

// Column with custom label
'column_name:Display Label'

// Column with formatting
'date_field:Join Date|date:Y-m-d'

// Column with conditional formatting
'status:Status|conditional:active=success,inactive=danger'
```

#### `hideColumns($columns)`
**Hide specific columns from display**
```php
$this->table->hideColumns(['id', 'password_hash', 'internal_notes']);
```

#### `showColumns($columns)` 
**Explicitly show only specified columns**
```php
$this->table->showColumns(['username', 'email', 'status', 'created_at']);
```

---

## Filtering & Search Features

### 1. Filter Groups Configuration

#### `filterGroups($column, $type, $relate = false)`
**Create filterable columns with various input types**

```php
// Dropdown filter
$this->table->filterGroups('status', 'selectbox', true);

// Text input filter
$this->table->filterGroups('username', 'text', true);

// Date range filter  
$this->table->filterGroups('created_at', 'daterange', true);

// Number range filter
$this->table->filterGroups('age', 'numberrange', true);

// Multi-select filter
$this->table->filterGroups('tags', 'multiselect', true);
```

#### Filter Types Available
| Type | Description | Use Case |
|------|-------------|----------|
| `selectbox` | Dropdown selection | Status, Categories, Groups |
| `text` | Text input | Names, Descriptions, IDs |
| `daterange` | Date range picker | Created dates, Event dates |
| `numberrange` | Number range input | Ages, Prices, Quantities |
| `multiselect` | Multiple selection | Tags, Multiple categories |
| `boolean` | Yes/No toggle | Active/Inactive status |

### 2. Advanced Search Configuration

#### `searchable($columns = [])` 
**Configure searchable columns**

```php
// Search all columns  
$this->table->searchable();

// Search specific columns only
$this->table->searchable(['username', 'email', 'fullname']);

// Column-specific search configuration
$this->table->searchable([
    'username' => 'exact',      // Exact match only
    'email' => 'partial',       // Partial matching (default)
    'phone' => 'starts_with'    // Starts with matching
]);
```

### 3. Filter Relations & Joins

#### `relations($model, $type, $field, $relations)`
**Setup table relations for filtering**

```php
// Basic relation
$this->table->relations($this->model, 'group', 'group_info', [
    'base_user_group.user_id' => 'users.id',
    'base_group.id' => 'base_user_group.group_id'
]);

// Multiple relations
$this->table->relations($this->model, 'profile', 'profile_data', [
    'user_profiles.user_id' => 'users.id',
    'profile_details.profile_id' => 'user_profiles.id'
]);
```

---

## Display & Formatting Options

### 1. Table Appearance

#### `clickable($enable = true)`
**Configure row click behavior**
```php
// Enable row clicking (default: edit action)
$this->table->clickable();

// Disable row clicking
$this->table->clickable(false);

// Custom click action
$this->table->clickable('view'); // Routes to view action
$this->table->clickable('custom_action');
```

#### `sortable($columns = [])`
**Configure column sorting**
```php
// Enable sorting on all columns
$this->table->sortable();

// Enable sorting on specific columns
$this->table->sortable(['username', 'created_at', 'status']);

// Disable sorting on specific columns
$this->table->sortable(['created_at'], 'exclude');
```

#### `orderby($column, $direction = 'ASC')`
**Set default sorting**
```php
// Default sort by ID descending
$this->table->orderby('id', 'DESC');

// Multiple column sorting
$this->table->orderby([
    ['created_at', 'DESC'],
    ['username', 'ASC']
]);
```

### 2. Pagination & Display

#### `displayRowsLimitOnLoad($limit)`
**Set default pagination limit**
```php
$this->table->displayRowsLimitOnLoad(25); // Show 25 records per page
```

#### `pageLengthMenu($options)`
**Customize pagination options**
```php
$this->table->pageLengthMenu([10, 25, 50, 100, -1]); 
// -1 = Show all
```

### 3. Data Formatting

#### Built-in Formatters
```php
// Date formatting
'created_at:Join Date|date:Y-m-d H:i:s'

// Number formatting  
'price:Price|number:2,.,,'  // 2 decimals, dot separator

// Conditional formatting
'status:Status|conditional:1=Active|success,0=Inactive|danger'

// Image formatting
'photo:Photo|image:150,150' // Width, Height in pixels

// Link formatting
'website:Website|link:_blank' // Open in new tab

// JSON formatting
'metadata:Details|json:pretty'
```

#### Custom Formatters
```php
// Register custom formatter
$this->table->addCustomFormatter('currency', function($value) {
    return 'Rp ' . number_format($value, 0, ',', '.');
});

// Use custom formatter
'price:Price|currency'
```

---

## Data Manipulation & Relations

### 1. Model Configuration

#### `setModel($model)`
**Set data source model**
```php
// Eloquent model
$this->table->setModel(User::class);

// Custom SQL query
$this->table->setModel('sql')->setQuery("
    SELECT u.*, g.group_name 
    FROM users u 
    LEFT JOIN user_groups ug ON u.id = ug.user_id
    LEFT JOIN groups g ON ug.group_id = g.id
    WHERE u.active = 1
");
```

#### `connection($connection_name)`
**Set database connection**
```php
$this->table->connection('mysql_secondary');
$this->table->connection('postgresql_reports');
```

### 2. Data Transformation

#### `transform($column, $callback)`
**Transform data before display**
```php
$this->table->transform('status', function($value, $row) {
    return $value == 1 ? 'Active' : 'Inactive';
});

$this->table->transform('full_name', function($value, $row) {
    return $row->first_name . ' ' . $row->last_name;
});
```

#### `aggregate($column, $function)`
**Add aggregate calculations**
```php
// Add totals row
$this->table->aggregate('price', 'SUM');
$this->table->aggregate('quantity', 'AVG'); 
$this->table->aggregate('items', 'COUNT');
```

---

## Export & Action Buttons

### 1. Export Configuration

#### `exportButtons($formats)`
**Enable data export functionality**
```php
// Enable multiple export formats
$this->table->exportButtons(['excel', 'pdf', 'csv', 'print']);

// Custom export configuration
$this->table->exportButtons([
    'excel' => ['filename' => 'users-export'],
    'pdf' => ['orientation' => 'landscape'],
    'csv' => ['separator' => ';']
]);
```

#### Export Button Configuration Options
```php
$buttonConfig = [
    'extend' => 'collection',
    'exportOptions' => ['columns' => ':visible:not(:last-child)'],
    'text' => '<i class="fa fa-external-link"></i> Export',
    'buttons' => [
        ['text' => 'Excel', 'extend' => 'excel'],
        ['text' => 'PDF', 'extend' => 'pdf'],
        ['text' => 'CSV', 'extend' => 'csv']
    ]
];
```

### 2. Action Buttons

#### `actionButtons($buttons)`
**Configure row-level action buttons**
```php
// Standard CRUD buttons
$this->table->actionButtons(['view', 'edit', 'delete']);

// Custom action buttons
$this->table->actionButtons([
    'activate' => [
        'icon' => 'fa-check', 
        'class' => 'btn-success',
        'route' => 'user.activate'
    ],
    'suspend' => [
        'icon' => 'fa-ban',
        'class' => 'btn-warning', 
        'route' => 'user.suspend'
    ]
]);
```

#### `removeActionButtons($buttons)`
**Remove specific action buttons**
```php
$this->table->removeActionButtons(['delete']); // Remove delete button
$this->table->removeActionButtons(['add']);    // Remove "New" button
```

---

## Advanced Features

### 1. Chart Integration

#### `chart($type, $fields, $format, $category, $group, $order)`
**Create charts from table data**
```php
$this->table->chart(
    'bar',                          // Chart type
    ['status', 'count'],           // Data fields
    'count',                       // Value format
    'status',                      // Category field
    'status',                      // Group by field
    'count DESC'                   // Order
);
```

#### Available Chart Types
- `bar` - Bar charts
- `line` - Line charts  
- `pie` - Pie charts
- `doughnut` - Doughnut charts
- `area` - Area charts
- `scatter` - Scatter plots

### 2. Real-time Features

#### `liveUpdates($interval_seconds)`
**Enable automatic data refresh**
```php
$this->table->liveUpdates(30); // Refresh every 30 seconds
```

#### `websocketUpdates($channel)`
**Enable real-time WebSocket updates**
```php
$this->table->websocketUpdates('users-table-updates');
```

### 3. Advanced Filtering

#### `serverSideProcessing($enable = true)`
**Enable server-side processing for large datasets**
```php
$this->table->serverSideProcessing(true);
$this->table->serverSideProcessing([
    'url' => route('users.datatable'),
    'method' => 'POST'
]);
```

#### `customFilters($filters)`
**Add custom filter conditions**
```php
$this->table->customFilters([
    'active_users' => function($query) {
        return $query->where('status', 'active')
                    ->where('last_login', '>', now()->subDays(30));
    },
    'premium_users' => function($query) {
        return $query->whereHas('subscription', function($q) {
            $q->where('type', 'premium');
        });
    }
]);
```

---

## JavaScript Integration

### 1. Client-side Configuration

#### JavaScript Events
```javascript
// Table initialization event
$(document).on('tableInit', function(e, tableId, settings) {
    console.log('Table initialized:', tableId);
});

// Filter applied event
$(document).on('filterApplied', function(e, tableId, filters) {
    console.log('Filters applied:', filters);
});

// Row selected event
$(document).on('rowSelected', function(e, tableId, rowData) {
    console.log('Row selected:', rowData);
});
```

#### Custom JavaScript Integration
```php
$this->table->addScript('
    $(document).ready(function() {
        $("#' . $tableId . '").on("draw.dt", function() {
            // Custom logic after table redraw
            initializeTooltips();
            bindCustomEvents();
        });
    });
');
```

### 2. AJAX Configuration

#### `ajaxUrl($url)`
**Set custom AJAX endpoint**
```php
$this->table->ajaxUrl(route('custom.datatable.endpoint'));
```

#### `ajaxHeaders($headers)`
**Set custom AJAX headers**
```php
$this->table->ajaxHeaders([
    'X-Custom-Header' => 'value',
    'Authorization' => 'Bearer ' . $token
]);
```

---

## Security Features

### 1. CSRF Protection

#### Automatic CSRF Handling
```php
// CSRF tokens automatically included in:
// - Form submissions
// - AJAX requests  
// - Export operations
// - Filter operations

// Custom CSRF configuration
$this->table->csrf([
    'token' => csrf_token(),
    'header' => 'X-CSRF-TOKEN'
]);
```

### 2. Access Control

#### `permissions($permissions)`
**Set permission requirements**
```php
$this->table->permissions([
    'view' => 'users.view',
    'edit' => 'users.edit', 
    'delete' => 'users.delete',
    'export' => 'users.export'
]);
```

#### `secureColumns($columns)`
**Mark columns as sensitive**
```php
$this->table->secureColumns(['password', 'api_key', 'ssn']);
// These columns will be hidden in exports and public views
```

---

## Performance Optimization

### 1. Caching

#### `cache($options)`
**Enable query result caching**
```php
$this->table->cache([
    'ttl' => 300,              // 5 minutes
    'key' => 'users_table',    // Cache key
    'tags' => ['users', 'data'] // Cache tags for invalidation
]);
```

#### `lazy($enable = true)`
**Enable lazy loading for large datasets**
```php
$this->table->lazy(true); // Load data as needed
```

### 2. Query Optimization

#### `select($columns)`
**Optimize database queries**
```php
// Only select needed columns
$this->table->select(['id', 'username', 'email', 'status']);
```

#### `chunk($size)`
**Process large datasets in chunks**
```php
$this->table->chunk(1000); // Process 1000 records at a time
```

---

## Customization Options

### 1. Theme Customization

#### `theme($theme_name)`
**Apply custom themes**
```php
$this->table->theme('bootstrap4');
$this->table->theme('material-design');
$this->table->theme('corporate');
```

#### `customCSS($css)`
**Add custom CSS**
```php
$this->table->customCSS('
    .my-table .status-active { color: green; }
    .my-table .status-inactive { color: red; }
');
```

### 2. Template Customization

#### `template($template_name)`
**Use custom templates**
```php
$this->table->template('admin.tables.users'); // Custom Blade template
```

#### `partial($partial_name, $data)`
**Include custom partial views**
```php
$this->table->partial('custom.user-actions', [
    'permissions' => $userPermissions
]);
```

---

## Error Handling & Debugging

### 1. Debug Mode

#### `debug($enable = true)`
**Enable comprehensive debugging**
```php
$this->table->debug(true);
// Outputs:
// - SQL queries generated
// - Filter parameters applied  
// - Performance metrics
// - Memory usage statistics
```

### 2. Error Handling

#### Custom Error Handlers
```php
$this->table->onError(function($exception, $context) {
    Log::error('Table Error: ' . $exception->getMessage(), [
        'table' => $context['table_name'],
        'filters' => $context['applied_filters'],
        'stack' => $exception->getTraceAsString()
    ]);
});
```

---

## API Reference Summary

### Core Methods
| Method | Purpose | Parameters |
|--------|---------|------------|
| `lists()` | Create data table | table_name, columns, attributes |
| `setMethod()` | Set HTTP method | GET/POST |
| `filterGroups()` | Add column filter | column, type, relate |
| `searchable()` | Configure search | columns array |
| `sortable()` | Configure sorting | columns array |
| `relations()` | Setup table joins | model, type, field, relations |

### Display Methods  
| Method | Purpose | Parameters |
|--------|---------|------------|
| `clickable()` | Row click behavior | enable, action |
| `orderby()` | Default sorting | column, direction |
| `hideColumns()` | Hide columns | columns array |
| `label()` | Table title | label string |

### Advanced Methods
| Method | Purpose | Parameters |  
|--------|---------|------------|
| `chart()` | Chart integration | type, fields, format |
| `export()` | Export functionality | formats array |
| `cache()` | Result caching | options array |
| `permissions()` | Access control | permissions array |

---

*This documentation covers all major features and capabilities of the Incodiy Table Component. For specific implementation examples and advanced use cases, refer to the controller examples in the codebase.*