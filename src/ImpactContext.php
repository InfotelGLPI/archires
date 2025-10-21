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
class ImpactContext extends CommonDBTM
{

    /**
     * Get ImpactContext for the given ImpactItem
     *
     * @param ImpactItem $item
     * @return ImpactContext|false
     */
    public static function findForImpactItem(ImpactItem $item)
    {
        $impactContext = new self();
        $exist = $impactContext->getFromDB($item->fields['impactcontexts_id']);

        return $exist ? $impactContext : false;
    }

    public static function install(Migration $migration)
    {
        global $DB;

        $table  = self::getTable();
        $default_charset   = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign  = DBConnection::getDefaultPrimaryKeySignOption();

        if (!$DB->tableExists($table)) { //not installed

            $query = "CREATE TABLE `$table` (
                        `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
                        `positions` mediumtext NOT NULL,
                        `zoom` float NOT NULL DEFAULT '0',
                        `pan_x` float NOT NULL DEFAULT '0',
                        `pan_y` float NOT NULL DEFAULT '0',
                        `impact_color` varchar(255) NOT NULL DEFAULT '',
                        `depends_color` varchar(255) NOT NULL DEFAULT '',
                        `impact_and_depends_color` varchar(255) NOT NULL DEFAULT '',
                        `show_depends` tinyint NOT NULL DEFAULT '1',
                        `show_impact` tinyint NOT NULL DEFAULT '1',
                        `max_depth` int NOT NULL DEFAULT '5',
                        PRIMARY KEY (`id`)
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
}
