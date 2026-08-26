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
use Monitor;
use NetworkEquipment;
use Notepad;
use Peripheral;
use PluginDatainjectionCommonInjectionLib;
use PluginDatainjectionNotepadInjection;
use Printer;

final class NotepadInjectionTest extends DbTestCase
{
    public function testNotepadCanBeMappedForSupportedAssetImports(): void
    {
        $connected_to = (new PluginDatainjectionNotepadInjection())->connectedTo();

        self::assertContains(Computer::class, $connected_to);
        self::assertContains(Monitor::class, $connected_to);
        self::assertContains(NetworkEquipment::class, $connected_to);
        self::assertContains(Peripheral::class, $connected_to);
        self::assertContains(Printer::class, $connected_to);
    }

    public function testNotepadOptionsExposeContentFieldForNewAssetImports(): void
    {
        foreach ([Monitor::class, Peripheral::class] as $itemtype) {
            $options = (new PluginDatainjectionNotepadInjection())->getOptions($itemtype);

            foreach ($options as $option) {
                if (
                    ($option['table'] ?? null) === Notepad::getTable()
                    && ($option['linkfield'] ?? null) === 'content'
                ) {
                    self::assertSame(PluginDatainjectionCommonInjectionLib::FIELD_INJECTABLE, $option['injectable']);
                    self::assertSame('multiline_text', $option['displaytype']);
                    continue 2;
                }
            }

            self::fail(sprintf('Unable to find the notepad content option for %s.', $itemtype));
        }
    }
}
