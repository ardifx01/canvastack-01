# Table Component — Features & API Matrix

This document enumerates all major features exposed through `$this->table()` and related helpers. Each section includes short usage examples.

---

## 1) Method & Security

- setMethod('GET'|'POST')
- setSecureMode(true|false)

```php
$this->table->setMethod('POST');
$this->table->setSecureMode(true); // forces POST in the UI
```

## 2) Columns & Lists

- lists(string $table, array $columns, array $attributes = [])
- hideColumns(array $columns)
- showColumns(array $columns)
- sortable(array $columns = [])
- searchable(array $columns = [])
- orderby(string $column, string $direction)

```php
$this->table->lists('users', [
  'username:User Name',
  'email:Email',
  'status:Status|conditional:1=Active|success,0=Inactive|danger',
  'created_at:Registered|date:Y-m-d'
]);
$this->table->hideColumns(['password']);
$this->table->orderby('created_at', 'DESC');
```

## 3) Filtering

- filterGroups(string $column, string $type, bool $relate = false)
- customFilters(array $filters)

Types: text, selectbox, multiselect, daterange, numberrange, boolean

```php
$this->table->filterGroups('username', 'text', true);
$this->table->filterGroups('status', 'selectbox', true);
$this->table->filterGroups('created_at', 'daterange', true);
```

## 4) Relations

- relations(object $model, string $type, string $field, array $relations)
- Model-based relationships where available (e.g., getUserInfo)

```php
$this->table->relations($this->model, 'group', 'group_info', [
  'user_groups.user_id' => 'users.id',
  'groups.id' => 'user_groups.group_id'
]);
```

## 5) Actions

- actionButtons(array $buttons)
- removeActionButtons(array $buttons)

```php
$this->table->actionButtons(['view', 'edit', 'delete']);
$this->table->removeActionButtons(['delete']);
```

## 6) Formatting & Transformation

- transform(string $column, callable $fn)
- Built-in format: date, currency, conditional, image

```php
$this->table->transform('status', fn($v) => $v ? 'Active' : 'Inactive');
```

## 7) Export

- exportButtons(array $formats)
- Supported: csv, excel, pdf, print

```php
$this->table->exportButtons(['excel', 'pdf']);
```

## 8) Pagination & Performance

- displayRowsLimitOnLoad(int $limit)
- pageLengthMenu(array $options)
- serverSideProcessing(bool $enabled)
- lazy(bool $enabled); chunk(int $size)

```php
$this->table->displayRowsLimitOnLoad(25);
$this->table->serverSideProcessing(true);
```

## 9) Charts

- chart(type, fields, valueFormat, category, groupBy, order)

```php
$this->table->chart('bar', ['status', 'count'], 'count', 'status', 'status', 'count DESC');
```

## 10) UI & Header Manipulation

- Column grouping/merged headers (via lists definitions)
- Column visibility toggles
- Responsive layout

## 11) Security & Validation

- CSRF handling (frontend)
- Reserved parameter filtering (backend)
- SQL keyword scrubbing
- XSS-safe rendering for custom formatters

## 12) Diagnostics & Logging

- Filter extraction logs
- SQL query logs (when enabled)
- Counts (total, filtered) tracking

---

For deeper architectural details, see GET_POST_FILTERING_BUGFIX_ANALYSIS_AND_HARDENING.md and UNIVERSAL_DATA_SOURCE_GUIDE.md.