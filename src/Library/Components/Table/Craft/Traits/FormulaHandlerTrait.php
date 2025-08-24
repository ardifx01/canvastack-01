<?php

namespace Incodiy\Codiy\Library\Components\Table\Craft\Traits;

use Incodiy\Codiy\Library\Components\Table\Craft\Formula;

/**
 * FormulaHandlerTrait
 * - Handles formula-based computed columns
 */
trait FormulaHandlerTrait
{
    /**
     * Apply formula rules to datatable
     */
    protected function processFormulaColumnsTrait($datatables, $data, string $tableName): void
    {
        if (!isset($data->datatables->formula) ||
            !isset($data->datatables->formula[$tableName]) ||
            empty($data->datatables->formula[$tableName])) {
            \Log::info('No formula configuration for table', ['table' => $tableName]);
            return;
        }

        $formulas = $data->datatables->formula[$tableName];
        if (!is_array($formulas)) { return; }

        if (!isset($data->datatables->columns[$tableName]['lists'])) {
            $data->datatables->columns[$tableName]['lists'] = [];
        }

        $columnLists = $data->datatables->columns[$tableName]['lists'];
        if (!is_array($columnLists)) { $columnLists = []; }

        try {
            $data->datatables->columns[$tableName]['lists'] = diy_set_formula_columns($columnLists, $formulas);
        } catch (\Throwable $e) {
            \Log::error('Error in diy_set_formula_columns', ['message' => $e->getMessage(), 'line' => $e->getLine(), 'table' => $tableName]);
        }

        foreach ($formulas as $formula) {
            $datatables->editColumn($formula['name'], function ($row) use ($formula) {
                $logic = new Formula($formula, $row);
                return $logic->calculate();
            });
        }
    }
}