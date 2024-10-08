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

/**
 * @return bool
 */
function plugin_archires_install()
{
    $migration = new Migration(100);

    include_once(PLUGIN_ARCHIRES_DIR . "/inc/profile.class.php");

    PluginArchiresArchires::install($migration);

    CronTask::Register('PluginArchiresArchires', 'CreateNetworkArchitecture',
        WEEK_TIMESTAMP, ['state' => CronTask::STATE_DISABLE]);

    PluginArchiresProfile::initProfile();
    PluginArchiresProfile::createFirstAccess($_SESSION['glpiactiveprofile']['id']);

    return true;
}

/**
 * @return bool
 */
function plugin_archires_uninstall()
{
    include_once(PLUGIN_ARCHIRES_DIR . "/inc/profile.class.php");

    PluginArchiresArchires::uninstall();

    CronTask::unregister("archires");

    //Delete rights associated with the plugin
    $profileRight = new ProfileRight();
    foreach (PluginArchiresProfile::getAllRights() as $right) {
        $profileRight->deleteByCriteria(array('name' => $right['field']));
    }

    PluginArchiresProfile::removeRightsFromSession();

    return true;
}

