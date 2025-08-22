<?php

namespace Incodiy\Codiy\Library\Components\Table\Contracts;

/**
 * DataProviderInterface
 * 
 * Core contract for data providers - enables separation between
 * data processing (backend) and presentation logic (frontend).
 * 
 * This interface supports the architectural goal of making the system
 * framework-agnostic and swappable for different frontend technologies
 * (Tailwind, React, Vue, etc.)
 */
interface DataProviderInterface
{
    /**
     * Get data based on configuration
     * 
     * @param array $config Configuration parameters
     * @return DataResponse Clean, generic data response
     */
    public function getData(array $config): DataResponse;

    /**
     * Get metadata about the data source
     * 
     * @return array Metadata including columns, types, relationships
     */
    public function getMetadata(): array;

    /**
     * Get total count of records (before filtering)
     * 
     * @return int Total record count
     */
    public function getTotalCount(): int;

    /**
     * Get filtered count of records (after filtering)
     * 
     * @return int Filtered record count
     */
    public function getFilteredCount(): int;

    /**
     * Apply filters to the data source
     * 
     * @param array $filters Array of filter criteria
     * @return self For method chaining
     */
    public function applyFilters(array $filters): self;

    /**
     * Apply sorting to the data source
     * 
     * @param string $column Column to sort by
     * @param string $direction Sort direction (asc/desc)
     * @return self For method chaining
     */
    public function applySorting(string $column, string $direction = 'asc'): self;

    /**
     * Apply pagination to the data source
     * 
     * @param int $start Starting record
     * @param int $length Number of records to fetch
     * @return self For method chaining
     */
    public function applyPagination(int $start, int $length): self;

    /**
     * Validate configuration
     * 
     * @param array $config Configuration to validate
     * @return bool True if valid
     * @throws \InvalidArgumentException If configuration is invalid
     */
    public function validateConfig(array $config): bool;
}