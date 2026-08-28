<?php

/**
 * -------------------------------------------------------------------------
 * DataInjection plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of DataInjection.
 *
 * DataInjection is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * DataInjection is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with DataInjection. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2007-2023 by DataInjection plugin team.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/pluginsGLPI/datainjection
 * -------------------------------------------------------------------------
 */

class PluginDatainjectionHistoryLogger
{
    private static array $loggedMarkers = [];
    private static array $pendingCleanups = [];
    private static bool $cleanupRegistered = false;

    public static function beforeWrite($item, array $toinject): array
    {
        return [
            'baseline' => self::getLastLogID(),
            'fields'   => self::loadCurrentFields($item, $toinject),
        ];
    }

    public static function afterWrite(
        array $snapshot,
        PluginDatainjectionInjectionInterface $injectionClass,
        $item,
        array $toinject,
        $newID,
        bool $add,
        array $context = []
    ): void
    {
        if (empty($newID) || (int) $newID <= 0) {
            return;
        }

        $target = self::getHistoryTarget($item, $toinject, $newID);
        if ($target === null) {
            return;
        }

        if (!self::targetKeepsHistory($item, $target)) {
            return;
        }

        self::logImportMarker($target, $add, $context);

        if (self::isFieldsPluginObject($item)) {
            self::logFieldsPluginChanges($snapshot, $injectionClass, $item, $toinject, $target, $add, $context);
            return;
        }

        self::logNativeChanges($snapshot, $injectionClass, $item, $toinject, $newID, $target, $add, $context);
    }

    private static function logNativeChanges(
        array $snapshot,
        PluginDatainjectionInjectionInterface $injectionClass,
        $item,
        array $toinject,
        $newID,
        array $target,
        bool $add,
        array $context
    ): void
    {
        $after = self::loadFieldsByID(get_class($item), $newID);
        if (empty($after)) {
            return;
        }

        $options = self::getNativeOptions($injectionClass, $item, $target);
        if (!$add) {
            self::cleanupEmptyNoopFieldHistory($snapshot['baseline'], $target, $options, $toinject, false, $context);
        }

        foreach ($toinject as $field => $inputValue) {
            if (
                !self::isMappedFieldForHistory($field, $context)
                || self::shouldSkipField($field, $inputValue)
                || (self::isInfocomObject($item) && self::shouldSkipInfocomField($field))
            ) {
                continue;
            }

            $option = self::findSearchOptionForField($options, $field);
            if ($option === null) {
                continue;
            }

            $oldValue = $add ? 'N/A' : ($snapshot['fields'][$field] ?? '');
            $newValue = $after[$field] ?? $inputValue;
            if (!$add && self::sameValue($oldValue, $newValue)) {
                continue;
            }

            self::logMissingChange(
                $snapshot['baseline'],
                $target,
                $option['id'],
                self::normalizeLogValue($oldValue),
                self::normalizeLogValue($newValue),
            );
        }
    }

    private static function logFieldsPluginChanges(
        array $snapshot,
        PluginDatainjectionInjectionInterface $injectionClass,
        $item,
        array $toinject,
        array $target,
        bool $add,
        array $context
    ): void
    {
        if (
            !class_exists('PluginFieldsContainer')
            || !class_exists('PluginFieldsAbstractContainerInstance')
        ) {
            return;
        }

        $containerID = self::getFieldsPluginContainerID($injectionClass, $item, $toinject, $snapshot, $target);
        if ($containerID === null) {
            return;
        }

        self::cleanupFieldsPluginLinkedHistory($snapshot['baseline'], $target, get_class($item));

        $options = self::getFieldsPluginOptions($injectionClass, $target['itemtype'], $containerID);
        if (!$add) {
            self::cleanupEmptyNoopFieldHistory($snapshot['baseline'], $target, $options, $toinject, true, $context);
        }

        $criteria = [
            'items_id'                    => $target['items_id'],
            'itemtype'                    => $target['itemtype'],
            'plugin_fields_containers_id' => $containerID,
        ];
        $after = self::loadFieldsByCriteria(get_class($item), $criteria);
        if (empty($after)) {
            return;
        }

        foreach ($toinject as $field => $inputValue) {
            if (
                !self::isMappedFieldForHistory($field, $context)
                || self::shouldSkipFieldsPluginField($field, $inputValue)
            ) {
                continue;
            }

            $option = self::findSearchOptionForField($options, $field);
            if ($option === null) {
                continue;
            }

            $oldValue = $add ? 'N/A' : ($snapshot['fields'][$field] ?? '');
            $newValue = $after[$field] ?? $inputValue;
            if (!$add && self::sameValue($oldValue, $newValue)) {
                continue;
            }

            self::logMissingChange(
                $snapshot['baseline'],
                $target,
                $option['id'],
                self::normalizeLogValue(self::displayFieldsPluginValue($field, $oldValue, $option)),
                self::normalizeLogValue(self::displayFieldsPluginValue($field, $newValue, $option)),
            );
        }
    }

    private static function logMissingChange(
        int $baseline,
        array $target,
        $searchOptionID,
        string $oldValue,
        string $newValue
    ): void
    {
        if (
            self::historyExists(
                $baseline,
                $target['items_id'],
                $target['itemtype'],
                $searchOptionID,
                $oldValue,
                $newValue,
            )
        ) {
            return;
        }

        Log::history($target['items_id'], $target['itemtype'], [$searchOptionID, $oldValue, $newValue]);
    }

    private static function cleanupEmptyNoopFieldHistory(
        int $baseline,
        array $target,
        array $options,
        array $toinject,
        bool $fieldsPlugin,
        array $context = []
    ): void {
        /** @var DBmysql $DB */
        global $DB;

        $search_option_ids = [];
        foreach ($toinject as $field => $value) {
            if (
                !self::isMappedFieldForHistory($field, $context)
                || ($fieldsPlugin && self::shouldSkipFieldsPluginField($field, $value))
                || (!$fieldsPlugin && self::shouldSkipField($field, $value))
            ) {
                continue;
            }

            $option = self::findSearchOptionForField($options, $field);
            if ($option !== null) {
                $search_option_ids[] = (int) $option['id'];
            }
        }

        $search_option_ids = array_values(array_unique(array_filter($search_option_ids)));
        if (empty($search_option_ids)) {
            return;
        }

        self::deleteEmptyNoopFieldHistory($baseline, $target, $search_option_ids);
        self::scheduleCleanup([
            'type'              => 'empty_noop',
            'baseline'          => $baseline,
            'target'            => $target,
            'search_option_ids' => $search_option_ids,
        ]);
    }

    private static function cleanupFieldsPluginLinkedHistory(int $baseline, array $target, string $fieldsItemtype): void
    {
        self::deleteFieldsPluginLinkedHistory($baseline, $target, $fieldsItemtype);
        self::scheduleCleanup([
            'type'            => 'fields_linked',
            'baseline'        => $baseline,
            'target'          => $target,
            'fields_itemtype' => $fieldsItemtype,
        ]);
    }

    public static function runPendingCleanups(): void
    {
        foreach (self::$pendingCleanups as $cleanup) {
            if ($cleanup['type'] === 'empty_noop') {
                self::deleteEmptyNoopFieldHistory(
                    $cleanup['baseline'],
                    $cleanup['target'],
                    $cleanup['search_option_ids'],
                );
            } elseif ($cleanup['type'] === 'fields_linked') {
                self::deleteFieldsPluginLinkedHistory(
                    $cleanup['baseline'],
                    $cleanup['target'],
                    $cleanup['fields_itemtype'],
                );
            }
        }

        self::$pendingCleanups = [];
    }

    private static function scheduleCleanup(array $cleanup): void
    {
        self::$pendingCleanups[] = $cleanup;
        if (self::$cleanupRegistered) {
            return;
        }

        register_shutdown_function([self::class, 'runPendingCleanups']);
        self::$cleanupRegistered = true;
    }

    private static function deleteEmptyNoopFieldHistory(int $baseline, array $target, array $search_option_ids): void
    {
        /** @var DBmysql $DB */
        global $DB;

        if (empty($search_option_ids)) {
            return;
        }

        $query = "DELETE FROM `" . Log::getTable() . "`
                WHERE `id` > " . (int) $baseline . "
                  AND `items_id` = " . (int) $target['items_id'] . "
                  AND `itemtype` = '" . $DB->escape($target['itemtype']) . "'
                  AND `id_search_option` IN (" . implode(',', array_map('intval', $search_option_ids)) . ")
                  AND (`old_value` IS NULL OR `old_value` = '')
                  AND (`new_value` IS NULL OR `new_value` = '')";
        $DB->doQuery($query);
    }

    private static function deleteFieldsPluginLinkedHistory(int $baseline, array $target, string $fieldsItemtype): void
    {
        /** @var DBmysql $DB */
        global $DB;

        $query = "DELETE FROM `" . Log::getTable() . "`
                WHERE `id` > " . (int) $baseline . "
                  AND `items_id` = " . (int) $target['items_id'] . "
                  AND `itemtype` = '" . $DB->escape($target['itemtype']) . "'
                  AND `itemtype_link` = '" . $DB->escape($fieldsItemtype) . "'
                  AND `id_search_option` = '0'";
        $DB->doQuery($query);
    }

    private static function getFieldsPluginContainerID(
        PluginDatainjectionInjectionInterface $injectionClass,
        $item,
        array $toinject,
        array $snapshot,
        array $target
    ): ?int {
        foreach ([
            $toinject['plugin_fields_containers_id'] ?? null,
            $item->fields['plugin_fields_containers_id'] ?? null,
            $snapshot['fields']['plugin_fields_containers_id'] ?? null,
        ] as $containerID) {
            if (!empty($containerID)) {
                return (int) $containerID;
            }
        }

        $options = method_exists($injectionClass, 'getOptions')
            ? $injectionClass->getOptions($target['itemtype'])
            : [];
        foreach ($options as $option) {
            if (!is_array($option) || empty($option['pfields_fields_id'])) {
                continue;
            }

            $containerID = self::getFieldsPluginContainerIDByFieldID((int) $option['pfields_fields_id']);
            if ($containerID !== null) {
                return $containerID;
            }
        }

        return self::getFieldsPluginContainerIDByClass(get_class($item), $target['itemtype']);
    }

    private static function getFieldsPluginOptions(
        PluginDatainjectionInjectionInterface $injectionClass,
        string $targetItemtype,
        int $containerID
    ): array {
        $options = method_exists($injectionClass, 'getOptions')
            ? $injectionClass->getOptions($targetItemtype)
            : [];
        if (!empty($options)) {
            return $options;
        }

        if (class_exists('PluginFieldsContainer')) {
            $options = PluginFieldsContainer::getAddSearchOptions($targetItemtype, $containerID);
            if (!empty($options)) {
                return $options;
            }
        }

        return self::buildFieldsPluginOptionsFromMetadata($targetItemtype, $containerID);
    }

    private static function getNativeOptions(
        PluginDatainjectionInjectionInterface $injectionClass,
        $item,
        array $target
    ): array {
        if (self::isInfocomObject($item)) {
            $options = self::getInfocomParentOptions($target['itemtype']);
            if (!empty($options)) {
                return $options;
            }
        }

        return $injectionClass->getOptions($target['itemtype']);
    }

    private static function getInfocomParentOptions(string $targetItemtype): array
    {
        if (!class_exists('Infocom') || !method_exists('Infocom', 'rawSearchOptionsToAdd')) {
            return [];
        }

        $options = self::indexSearchOptionsByID(Infocom::rawSearchOptionsToAdd($targetItemtype));
        foreach ($options as &$option) {
            if (!is_array($option) || isset($option['linkfield'])) {
                continue;
            }

            if (($option['table'] ?? null) === Infocom::getTable()) {
                $option['linkfield'] = $option['field'] ?? null;
                continue;
            }

            if (!empty($option['table']) && function_exists('getForeignKeyFieldForTable')) {
                $option['linkfield'] = getForeignKeyFieldForTable($option['table']);
            }
        }
        unset($option);

        return $options;
    }

    private static function indexSearchOptionsByID(array $options): array
    {
        $indexed = [];
        foreach ($options as $id => $option) {
            if (is_array($option) && array_key_exists('id', $option)) {
                $id = $option['id'];
            }

            $indexed[$id] = $option;
        }

        return $indexed;
    }

    private static function getFieldsPluginContainerIDByFieldID(int $fieldID): ?int
    {
        /** @var DBmysql $DB */
        global $DB;

        $result = $DB->request([
            'SELECT' => ['plugin_fields_containers_id'],
            'FROM'   => 'glpi_plugin_fields_fields',
            'WHERE'  => ['id' => $fieldID],
            'LIMIT'  => 1,
        ]);
        if (count($result) === 0) {
            return null;
        }

        return (int) $result->current()['plugin_fields_containers_id'];
    }

    private static function getFieldsPluginContainerIDByClass(string $fieldsItemtype, string $targetItemtype): ?int
    {
        /** @var DBmysql $DB */
        global $DB;

        if (!class_exists('PluginFieldsContainer') || !method_exists('PluginFieldsContainer', 'getClassname')) {
            return null;
        }

        $containers = $DB->request([
            'SELECT' => ['id', 'name', 'itemtypes'],
            'FROM'   => 'glpi_plugin_fields_containers',
            'WHERE'  => ['is_active' => 1],
        ]);
        foreach ($containers as $container) {
            $itemtypes = json_decode((string) $container['itemtypes'], true);
            if (!is_array($itemtypes) || !in_array($targetItemtype, $itemtypes, true)) {
                continue;
            }

            if (PluginFieldsContainer::getClassname($targetItemtype, $container['name']) === $fieldsItemtype) {
                return (int) $container['id'];
            }
        }

        return null;
    }

    private static function buildFieldsPluginOptionsFromMetadata(string $targetItemtype, int $containerID): array
    {
        /** @var DBmysql $DB */
        global $DB;

        if (
            !class_exists('PluginFieldsField')
            || !class_exists('PluginFieldsContainer')
            || !method_exists('PluginFieldsContainer', 'getClassname')
        ) {
            return [];
        }

        $fields = $DB->request([
            'SELECT' => [
                'glpi_plugin_fields_fields.id AS field_id',
                'glpi_plugin_fields_fields.name AS field_name',
                'glpi_plugin_fields_fields.label AS field_label',
                'glpi_plugin_fields_fields.type',
                'glpi_plugin_fields_fields.multiple',
                'glpi_plugin_fields_containers.name AS container_name',
                'glpi_plugin_fields_containers.label AS container_label',
            ],
            'FROM'       => 'glpi_plugin_fields_fields',
            'INNER JOIN' => [
                'glpi_plugin_fields_containers' => [
                    'FKEY' => [
                        'glpi_plugin_fields_containers' => 'id',
                        'glpi_plugin_fields_fields'     => 'plugin_fields_containers_id',
                    ],
                ],
            ],
            'WHERE' => [
                'glpi_plugin_fields_fields.plugin_fields_containers_id' => $containerID,
                'glpi_plugin_fields_fields.is_active'                  => 1,
                ['NOT' => ['glpi_plugin_fields_fields.type' => 'header']],
            ],
            'ORDERBY' => ['glpi_plugin_fields_fields.id'],
        ]);

        $options = [];
        foreach ($fields as $field) {
            $searchOptionID = PluginFieldsField::SEARCH_OPTION_STARTING_INDEX + (int) $field['field_id'];
            $tablename = getTableForItemType(
                PluginFieldsContainer::getClassname($targetItemtype, $field['container_name']),
            );
            $linkfield = $field['field_name'];
            $dbField = $field['field_name'];
            $table = $tablename;
            $datatype = self::getFieldsPluginMetadataDatatype($field['type']);

            if ($field['type'] === 'dropdown') {
                $linkfield = 'plugin_fields_' . $field['field_name'] . 'dropdowns_id';
                if ((int) $field['multiple'] === 1) {
                    $dbField = $linkfield;
                    $datatype = 'specific';
                } else {
                    $table = 'glpi_plugin_fields_' . $field['field_name'] . 'dropdowns';
                    $dbField = 'completename';
                    $datatype = 'dropdown';
                }
            }

            $options[$searchOptionID] = [
                'table'             => $table,
                'field'             => $dbField,
                'name'              => $field['container_label'] . ' - ' . $field['field_label'],
                'linkfield'         => $linkfield,
                'datatype'          => $datatype,
                'pfields_type'      => $field['type'],
                'pfields_fields_id' => (int) $field['field_id'],
            ];
        }

        return $options;
    }

    private static function getFieldsPluginMetadataDatatype(string $type): string
    {
        switch ($type) {
            case 'yesno':
                return 'bool';

            case 'textarea':
                return 'text';

            case 'date':
            case 'datetime':
                return $type;

            case 'url':
                return 'weblink';
        }

        return 'string';
    }

    private static function logImportMarker(array $target, bool $add, array $context): void
    {
        $label = $add ? __('Add from CSV file', 'datainjection') : __('Update from CSV file', 'datainjection');
        if (!empty($context['model_name']) && !empty($context['model_id']) && !empty($context['line'])) {
            $label = sprintf(
                __('%1$s - Model: %2$s (#%3$s), row %4$s', 'datainjection'),
                $label,
                $context['model_name'],
                $context['model_id'],
                $context['line'],
            );
        }

        $key = implode(':', [
            $target['itemtype'],
            $target['items_id'],
            $context['model_id'] ?? '',
            $context['line'] ?? '',
            $label,
        ]);
        if (isset(self::$loggedMarkers[$key])) {
            return;
        }

        Log::history($target['items_id'], $target['itemtype'], [0, '', $label]);
        self::$loggedMarkers[$key] = true;
    }

    private static function getHistoryTarget($item, array $toinject, $newID): ?array
    {
        if (self::isFieldsPluginObject($item) || self::isInfocomObject($item)) {
            $itemtype = $toinject['itemtype'] ?? ($item->fields['itemtype'] ?? null);
            $items_id = $toinject['items_id'] ?? ($item->fields['items_id'] ?? null);
            if ($itemtype && $items_id) {
                return [
                    'itemtype' => $itemtype,
                    'items_id' => (int) $items_id,
                ];
            }
        }

        return [
            'itemtype' => get_class($item),
            'items_id' => (int) $newID,
        ];
    }

    private static function targetKeepsHistory($item, array $target): bool
    {
        if (!self::isFieldsPluginObject($item) && !self::isInfocomObject($item)) {
            return (bool) $item->dohistory;
        }

        if (!is_a($target['itemtype'], CommonDBTM::class, true)) {
            return false;
        }

        $targetItem = new $target['itemtype']();
        return (bool) $targetItem->dohistory;
    }

    private static function findSearchOptionForField(array $options, string $field): ?array
    {
        foreach ($options as $id => $option) {
            if (!is_array($option)) {
                continue;
            }

            if (($option['linkfield'] ?? null) === $field) {
                $option['id'] = $option['id'] ?? $id;
                return $option;
            }
        }

        foreach ($options as $id => $option) {
            if (!is_array($option)) {
                continue;
            }

            if (($option['field'] ?? null) === $field) {
                $option['id'] = $option['id'] ?? $id;
                return $option;
            }
        }

        return null;
    }

    private static function historyExists(
        int $baseline,
        int $items_id,
        string $itemtype,
        $searchOptionID,
        string $oldValue,
        string $newValue
    ): bool
    {
        /** @var DBmysql $DB */
        global $DB;

        $query = "SELECT `id`
                FROM `" . Log::getTable() . "`
                WHERE `id` > " . (int) $baseline . "
                  AND `items_id` = " . (int) $items_id . "
                  AND `itemtype` = '" . $DB->escape($itemtype) . "'
                  AND `id_search_option` = '" . $DB->escape((string) $searchOptionID) . "'
                  AND `old_value` = '" . $DB->escape($oldValue) . "'
                  AND `new_value` = '" . $DB->escape($newValue) . "'
                LIMIT 1";
        $result = $DB->doQuery($query);

        return $result && $DB->numrows($result) > 0;
    }

    private static function loadCurrentFields($item, array $toinject): array
    {
        if (isset($toinject['id']) && (int) $toinject['id'] > 0) {
            return self::loadFieldsByID(get_class($item), (int) $toinject['id']);
        }

        if (
            self::isFieldsPluginObject($item)
            && isset($toinject['items_id'], $toinject['itemtype'])
        ) {
            $criteria = [
                'items_id'                    => $toinject['items_id'],
                'itemtype'                    => $toinject['itemtype'],
            ];
            if (!empty($toinject['plugin_fields_containers_id'])) {
                $criteria['plugin_fields_containers_id'] = $toinject['plugin_fields_containers_id'];
            }

            return self::loadFieldsByCriteria(get_class($item), $criteria);
        }

        return [];
    }

    private static function loadFieldsByID(string $itemtype, $id): array
    {
        if (!is_a($itemtype, CommonDBTM::class, true) || empty($id)) {
            return [];
        }

        $item = new $itemtype();
        if (!$item->getFromDB((int) $id)) {
            return [];
        }

        return $item->fields;
    }

    private static function loadFieldsByCriteria(string $itemtype, array $criteria): array
    {
        if (!is_a($itemtype, CommonDBTM::class, true)) {
            return [];
        }

        $item = new $itemtype();
        if (!$item->getFromDBByCrit($criteria)) {
            return [];
        }

        return $item->fields;
    }

    private static function getLastLogID(): int
    {
        /** @var DBmysql $DB */
        global $DB;

        $result = $DB->doQuery("SELECT MAX(`id`) AS max_id FROM `" . Log::getTable() . "`");
        if (!$result || $DB->numrows($result) === 0) {
            return 0;
        }

        return (int) $DB->result($result, 0, 'max_id');
    }

    private static function shouldSkipField(string $field, $value): bool
    {
        if ($field === 'id' || str_starts_with($field, '_') || is_array($value) || is_object($value)) {
            return true;
        }

        return false;
    }

    private static function isMappedFieldForHistory(string $field, array $context): bool
    {
        if (!array_key_exists('mapped_fields', $context)) {
            return true;
        }

        if (!is_array($context['mapped_fields'])) {
            return true;
        }

        return in_array($field, $context['mapped_fields'], true);
    }

    private static function shouldSkipFieldsPluginField(string $field, $value): bool
    {
        $metadata = [
            'id',
            'items_id',
            'itemtype',
            'plugin_fields_containers_id',
            'entities_id',
            'is_recursive',
        ];

        if (in_array($field, $metadata, true) || str_starts_with($field, '_') || is_array($value) || is_object($value)) {
            return true;
        }

        return false;
    }

    private static function shouldSkipInfocomField(string $field): bool
    {
        return in_array($field, [
            'items_id',
            'itemtype',
            'entities_id',
            'is_recursive',
        ], true);
    }

    private static function isFieldsPluginObject($item): bool
    {
        return class_exists('PluginFieldsAbstractContainerInstance')
            && is_a($item, 'PluginFieldsAbstractContainerInstance');
    }

    private static function isInfocomObject($item): bool
    {
        return is_a($item, Infocom::class);
    }

    private static function displayFieldsPluginValue(string $field, $value, array $option)
    {
        if ($value === 'N/A') {
            return $value;
        }

        switch ($option['datatype'] ?? '') {
            case 'dropdown':
                return Dropdown::getDropdownName($option['table'], $value);

            case 'bool':
                return Dropdown::getYesNo($value);

            case 'specific':
                if (class_exists('PluginFieldsAbstractContainerInstance')) {
                    return PluginFieldsAbstractContainerInstance::getSpecificValueToDisplay(
                        $field,
                        $value,
                        [
                            'searchopt' => $option,
                            'separator' => ', ',
                        ],
                    );
                }
                break;
        }

        return $value;
    }

    private static function sameValue($oldValue, $newValue): bool
    {
        return self::normalizeComparableValue($oldValue) === self::normalizeComparableValue($newValue);
    }

    private static function normalizeComparableValue($value): string
    {
        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    private static function normalizeLogValue($value): string
    {
        if (is_array($value) || is_object($value)) {
            return '';
        }

        $value = (string) ($value ?? '');
        $decoded = json_decode($value);
        if (is_array($decoded) || is_object($decoded)) {
            $value = '';
        }

        return mb_substr($value, 0, 255);
    }
}
