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

use PluginDatainjectionManufacturerInjection;
use Closure;
use Computer;
use Glpi\Tests\DbTestCase;
use Manufacturer;
use PluginDatainjectionComputerInjection;
use PluginDatainjectionCommonInjectionLib;

require_once dirname(__DIR__, 2) . '/inc/injectioninterface.class.php';
require_once dirname(__DIR__, 2) . '/inc/commoninjectionlib.class.php';
require_once dirname(__DIR__, 2) . '/inc/computerinjection.class.php';
require_once dirname(__DIR__, 2) . '/inc/manufacturerinjection.class.php';

final class CommonInjectionLibDataAlreadyInDbTest extends DbTestCase
{
    public function testDataAlreadyInDbHandlesApostropheInMandatoryFieldValue(): void
    {
        $manufacturer_name = "O'Brien Manufacturer";
        $manufacturer = $this->createItem(Manufacturer::class, [
            'name' => $manufacturer_name,
        ]);

        $injection_class = new PluginDatainjectionManufacturerInjection();
        $common_injection_lib = new PluginDatainjectionCommonInjectionLib(
            $injection_class,
            [
                'Manufacturer' => [
                    'name' => $manufacturer_name,
                ],
            ],
            [
                'mandatory_fields' => [
                    'Manufacturer' => [
                        'name' => true,
                    ],
                ],
            ],
        );

        $invoke_data_already_in_db = Closure::bind(
            function ($injection_class, string $itemtype): void {
                $this->dataAlreadyInDB($injection_class, $itemtype);
            },
            $common_injection_lib,
            PluginDatainjectionCommonInjectionLib::class,
        );

        $invoke_data_already_in_db($injection_class, 'Manufacturer');

        $values = $common_injection_lib->getValuesForItemtype('Manufacturer');
        self::assertIsArray($values);
        self::assertSame($manufacturer->getID(), $values['id']);
    }

    public function testUpdateWritesOnlyMappedFieldsAndMetadata(): void
    {
        $common_injection_lib = new PluginDatainjectionCommonInjectionLib(
            new PluginDatainjectionComputerInjection(),
            [
                Computer::class => [
                    'name'   => 'Mapped computer',
                    'serial' => 'SERIAL_NEW',
                ],
            ],
        );

        $should_write_field = Closure::bind(
            function (string $itemtype, string $field): bool {
                return $this->shouldWriteFieldForUpdate($itemtype, $field);
            },
            $common_injection_lib,
            PluginDatainjectionCommonInjectionLib::class,
        );

        self::assertTrue($should_write_field(Computer::class, 'id'));
        self::assertTrue($should_write_field(Computer::class, 'name'));
        self::assertTrue($should_write_field(Computer::class, 'serial'));
        self::assertFalse($should_write_field(Computer::class, 'otherserial'));
        self::assertFalse($should_write_field(Computer::class, 'contact'));
    }

    public function testHistoryContextContainsOnlyMappedFields(): void
    {
        $common_injection_lib = new PluginDatainjectionCommonInjectionLib(
            new PluginDatainjectionComputerInjection(),
            [
                Computer::class => [
                    'name'   => 'Mapped computer',
                    'serial' => 'SERIAL_NEW',
                ],
            ],
        );

        $get_history_context = Closure::bind(
            function (string $itemtype): array {
                return $this->getHistoryContextForItemtype($itemtype);
            },
            $common_injection_lib,
            PluginDatainjectionCommonInjectionLib::class,
        );

        $context = $get_history_context(Computer::class);

        self::assertSame(['name', 'serial'], $context['mapped_fields']);
    }
}
