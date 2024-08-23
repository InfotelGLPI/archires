<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 archires plugin for GLPI
 Copyright (C) 2009-2017 by the archires Development Team.

 https://github.com/InfotelGLPI/archires
 -------------------------------------------------------------------------

 LICENSE

 This file is part of archires.

 archires is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 archires is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with archires. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

$plugin = new Plugin();

if ($plugin->isActivated("archires")) {

    Session::checkRight("plugin_archires", UPDATE);

    $config = new PluginArchiresConfig();
    if (isset($_POST["update"])) {
        $res = $config->update($_POST);
        Html::back();
    } else {
        Html::header(PluginArchiresConfig::getTypeName(), '', "config", 'PluginArchiresConfig');
        $_GET['id'] = 1;
        $config->display($_GET);
        Html::footer();
    }
} else {
    Html::header(PluginArchiresConfig::getTypeName(), '', "config", 'PluginArchiresConfig');
    echo "<div align='center'><br><br>";
    echo "<img src=\"" . $CFG_GLPI["root_doc"] . "/pics/warning.png\" alt=\"warning\"><br><br>";
    echo "<b>" . __('Please activate the plugin', 'archires') . "</b></div>";
}
