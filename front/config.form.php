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

use GlpiPlugin\Archires\Config;

$plugin = new Plugin();
global $CFG_GLPI;

if ($plugin->isActivated("archires")) {

    Session::checkRight("plugin_archires", UPDATE);

    $config = new Config();
    if (isset($_POST["update"])) {
        $res = $config->update($_POST);
        Html::back();
    } else {
        Html::header(Config::getTypeName(), '', "config", Config::class);
        $_GET['id'] = 1;
        $config->display($_GET);
        Html::footer();
    }
} else {
    Html::header(Config::getTypeName(), '', "config", Config::class);
    echo "<div class='center'><br><br>";
    echo "<img src=\"" . $CFG_GLPI["root_doc"] . "/pics/warning.png\" alt=\"warning\"><br><br>";
    echo "<b>" . __('Please activate the plugin', 'archires') . "</b></div>";
}
