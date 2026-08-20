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

namespace GlpiPlugin\Datainjection\Tests\Unit;

use Computer;
use Glpi\Tests\DbTestCase;
use Log;
use PluginDatainjectionCommonInjectionLib;
use PluginDatainjectionComputerInjection;

final class HistoryLoggerTest extends DbTestCase
{
    public function testImportUpdateWritesContextMarkerAndSingleFieldHistory(): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        $computer = $this->createItem(Computer::class, [
            'name'        => 'Test_Computer_HistoryLogger',
            'serial'      => 'SERIAL_OLD',
            'entities_id' => 0,
        ]);

        $injection_class = new PluginDatainjectionComputerInjection();
        $serial_search_option = $this->getSearchOptionID($injection_class, 'serial');
        $baseline = $this->getLastLogID();

        $lib = new PluginDatainjectionCommonInjectionLib(
            $injection_class,
            [
                'Computer' => [
                    'name'   => 'Test_Computer_HistoryLogger',
                    'serial' => 'SERIAL_NEW',
                ],
            ],
            [
                'rights' => [
                    'can_add'      => true,
                    'can_update'   => true,
                    'add_dropdown' => true,
                ],
                'mandatory_fields' => [
                    'Computer' => ['name' => true],
                ],
                'entities_id'     => 0,
                'import_context' => [
                    'model_id'   => 99,
                    'model_name' => 'History Model',
                    'line'       => 42,
                ],
            ],
        );

        $lib->processAddOrUpdate();
        $results = $lib->getInjectionResults();

        self::assertSame(PluginDatainjectionCommonInjectionLib::SUCCESS, $results['status']);
        self::assertSame(PluginDatainjectionCommonInjectionLib::IMPORT_UPDATE, $results['type']);

        $marker = 'Update from CSV file - Model: History Model (#99), row 42';
        self::assertGreaterThan(
            0,
            countElementsInTable(Log::getTable(), [
                'items_id'         => $computer->getID(),
                'itemtype'         => Computer::class,
                'id_search_option' => 0,
                'new_value'        => $marker,
            ]),
        );

        $query = "SELECT `id`
                FROM `" . Log::getTable() . "`
                WHERE `id` > " . (int) $baseline . "
                  AND `items_id` = " . (int) $computer->getID() . "
                  AND `itemtype` = '" . $DB->escape(Computer::class) . "'
                  AND `id_search_option` = '" . $DB->escape((string) $serial_search_option) . "'
                  AND `old_value` = 'SERIAL_OLD'
                  AND `new_value` = 'SERIAL_NEW'";
        $result = $DB->doQuery($query);

        self::assertSame(1, $DB->numrows($result));
    }

    private function getSearchOptionID(PluginDatainjectionComputerInjection $injection_class, string $field): int
    {
        foreach ($injection_class->getOptions(Computer::class) as $id => $option) {
            if (($option['linkfield'] ?? null) === $field) {
                return (int) $id;
            }
        }

        self::fail(sprintf('Unable to find search option for field "%s"', $field));
    }

    private function getLastLogID(): int
    {
        /** @var \DBmysql $DB */
        global $DB;

        $result = $DB->doQuery("SELECT MAX(`id`) AS max_id FROM `" . Log::getTable() . "`");
        if (!$result || $DB->numrows($result) === 0) {
            return 0;
        }

        return (int) $DB->result($result, 0, 'max_id');
    }
}
