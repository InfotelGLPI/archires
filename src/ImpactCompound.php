<?php

/**
 * -------------------------------------------------------------------------
 * archires plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of archires.
 *
 * archires is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * archires is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with archires. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2009-2026 by archires plugin team.
 * @license   AGPLv3 https://www.gnu.org/licenses/agpl-3.0.html
 * @link      https://github.com/InfotelGLPI/archires
 * --------------------------------------------------------------------------
 */

namespace GlpiPlugin\Archires;

use CommonDBTM;
use DBConnection;
use Migration;

/**
 * @since 9.5.0
 */
class ImpactCompound extends CommonDBTM
{
    public static function install(Migration $migration)
    {
        global $DB;

        $table = 'glpi_plugin_archires_impactcompounds';
        $default_charset   = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign  = DBConnection::getDefaultPrimaryKeySignOption();

        if (!$DB->tableExists($table)) { //not installed

            $query = "CREATE TABLE `$table` (
                        `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
                        `name` varchar(255) DEFAULT '',
                        `color` varchar(255) NOT NULL DEFAULT '',
                        PRIMARY KEY (`id`),
                        KEY `name` (`name`)
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
