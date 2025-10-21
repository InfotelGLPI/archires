<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2024 Teclib' and contributors.
 * @copyright 2003-2014 by the INDEPNET Development Team.
 * @licence   https://www.gnu.org/licenses/gpl-3.0.html
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * ---------------------------------------------------------------------
 */

namespace GlpiPlugin\Archires;
use CommonDBTM;
use DBConnection;
use Migration;

/**
 * @since 9.5.0
 */
class ImpactItem extends CommonDBTM
{

    public static function install(Migration $migration)
    {
        global $DB;

        $table  = self::getTable();
        $default_charset   = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign  = DBConnection::getDefaultPrimaryKeySignOption();

        if (!$DB->tableExists($table)) { //not installed

            $query = "CREATE TABLE `$table` (
                        `id` int {$default_key_sign} NOT NULL auto_increment,
                        `itemtype` varchar(255) NOT NULL DEFAULT '',
                        `items_id` int {$default_key_sign} NOT NULL DEFAULT '0',
                        `parent_id` int {$default_key_sign} NOT NULL DEFAULT '0',
                        `impactcontexts_id` int {$default_key_sign} NOT NULL DEFAULT '0',
                        `is_slave` tinyint NOT NULL DEFAULT '1',
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `unicity` (`itemtype`,`items_id`),
                        KEY `source` (`itemtype`,`items_id`),
                        KEY `parent_id` (`parent_id`),
                        KEY `impactcontexts_id` (`impactcontexts_id`)
                        ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
            $DB->doQuery($query);
        }
        return true;
    }

    public static function uninstall()
    {
        global $DB;

        $DB->dropTable(self::getTable(), true);
    }


    /**
     * Find ImpactItem for a given CommonDBTM item
     *
     * @param CommonDBTM $item                The given item
     * @param bool       $create_if_missing   Should we create a new ImpactItem
     *                                        if none found ?
     * @return ImpactItem|bool ImpactItem object or false if not found and
     *                         creation is disabled
     */
    public static function findForItem(
        CommonDBTM $item,
        bool $create_if_missing = true
    ) {
        /** @var \DBmysql $DB */
        global $DB;

        $it = $DB->request([
            'SELECT' => [
                'glpi_plugin_archires_impactitems.id',
            ],
            'FROM' => self::getTable(),
            'WHERE'  => [
                'glpi_plugin_archires_impactitems.itemtype' => get_class($item),
                'glpi_plugin_archires_impactitems.items_id' => $item->fields['id'],
            ]
        ]);

        $res = $it->current();
        $impact_item = new self();

        if ($res) {
            $id = $res['id'];
        } else if (!$res && $create_if_missing) {
            $id = $impact_item->add([
                'itemtype' => get_class($item),
                'items_id' => $item->fields['id']
            ]);
        } else {
            return false;
        }

        $impact_item->getFromDB($id);
        return $impact_item;
    }

    public function prepareInputForUpdate($input)
    {
        $max_depth = $input['max_depth'] ?? 0;

        if (intval($max_depth) <= 0) {
           // If value is not valid, reset to default
            $input['max_depth'] = Archires::DEFAULT_DEPTH;
        } else if ($max_depth >= Archires::MAX_DEPTH && $max_depth != Archires::NO_DEPTH_LIMIT) {
           // Set to no limit if greater than max
            $input['max_depth'] = Archires::NO_DEPTH_LIMIT;
        }

        return $input;
    }
}
