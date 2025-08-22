<?php

namespace Incodiy\Codiy\Library\Components\Table\Contracts;

/**
 * DataResponse
 * 
 * Standardized response format for all data providers.
 * This class ensures consistent data structure regardless of the
 * underlying data source (Eloquent, Query Builder, Raw SQL, etc.)
 * 
 * The generic format enables easy adaptation to different frontend
 * frameworks and presentation layers.
 */
class DataResponse
{
    /**
     * The actual data records
     * 
     * @var array
     */
    public array $data;

    /**
     * Total number of records (before filtering)
     * 
     * @var int
     */
    public int $total;

    /**
     * Number of records after filtering
     * 
     * @var int
     */
    public int $filtered;

    /**
     * Column definitions and metadata
     * 
     * @var array
     */
    public array $columns;

    /**
     * Pagination information
     * 
     * @var array
     */
    public array $pagination;

    /**
     * Filtering information applied
     * 
     * @var array
     */
    public array $filters;

    /**
     * Sorting information applied
     * 
     * @var array
     */
    public array $sorting;

    /**
     * Additional metadata
     * 
     * @var array
     */
    public array $metadata;

    /**
     * Create a new DataResponse instance
     * 
     * @param array $data The data records
     * @param int $total Total record count
     * @param int $filtered Filtered record count
     * @param array $columns Column definitions
     * @param array $pagination Pagination info
     * @param array $filters Applied filters
     * @param array $sorting Applied sorting
     * @param array $metadata Additional metadata
     */
    public function __construct(
        array $data = [],
        int $total = 0,
        int $filtered = 0,
        array $columns = [],
        array $pagination = [],
        array $filters = [],
        array $sorting = [],
        array $metadata = []
    ) {
        $this->data = $data;
        $this->total = $total;
        $this->filtered = $filtered;
        $this->columns = $columns;
        $this->pagination = $pagination;
        $this->filters = $filters;
        $this->sorting = $sorting;
        $this->metadata = $metadata;
    }

    /**
     * Convert to array for JSON serialization
     * 
     * @return array
     */
    public function toArray(): array
    {
        return [
            'data' => $this->data,
            'total' => $this->total,
            'filtered' => $this->filtered,
            'columns' => $this->columns,
            'pagination' => $this->pagination,
            'filters' => $this->filters,
            'sorting' => $this->sorting,
            'metadata' => $this->metadata
        ];
    }

    /**
     * Convert to JSON
     * 
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * Create a DataResponse for DataTables format
     * 
     * @param int $draw Draw parameter from DataTables
     * @return array DataTables-specific format
     */
    public function toDataTablesFormat(int $draw = 1): array
    {
        return [
            'draw' => $draw,
            'recordsTotal' => $this->total,
            'recordsFiltered' => $this->filtered,
            'data' => $this->data
        ];
    }

    /**
     * Create a DataResponse for API format
     * 
     * @return array Generic API format
     */
    public function toApiFormat(): array
    {
        return [
            'success' => true,
            'data' => $this->data,
            'meta' => [
                'total' => $this->total,
                'filtered' => $this->filtered,
                'per_page' => $this->pagination['length'] ?? 10,
                'current_page' => isset($this->pagination['start'], $this->pagination['length']) 
                    ? floor($this->pagination['start'] / $this->pagination['length']) + 1 
                    : 1,
                'columns' => $this->columns,
                'filters' => $this->filters,
                'sorting' => $this->sorting
            ]
        ];
    }

    /**
     * Create a DataResponse for React props format
     * 
     * @return array React component props format
     */
    public function toReactProps(): array
    {
        return [
            'data' => $this->data,
            'columns' => $this->columns,
            'pagination' => [
                'total' => $this->total,
                'filtered' => $this->filtered,
                'currentPage' => isset($this->pagination['start'], $this->pagination['length']) 
                    ? floor($this->pagination['start'] / $this->pagination['length']) + 1 
                    : 1,
                'perPage' => $this->pagination['length'] ?? 10,
                'hasNextPage' => ($this->pagination['start'] ?? 0) + ($this->pagination['length'] ?? 10) < $this->filtered,
                'hasPrevPage' => ($this->pagination['start'] ?? 0) > 0
            ],
            'filters' => $this->filters,
            'sorting' => $this->sorting,
            'metadata' => $this->metadata
        ];
    }

    /**
     * Check if response has data
     * 
     * @return bool
     */
    public function hasData(): bool
    {
        return !empty($this->data);
    }

    /**
     * Check if response is empty
     * 
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->data);
    }

    /**
     * Get data count
     * 
     * @return int
     */
    public function count(): int
    {
        return count($this->data);
    }
}