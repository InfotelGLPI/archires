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

use GlpiPlugin\Archires\Archires;
use GlpiPlugin\Archires\ImpactCompound;
use GlpiPlugin\Archires\ImpactContext;
use GlpiPlugin\Archires\ImpactItem;
use GlpiPlugin\Archires\ImpactRelation;
use GlpiPlugin\Archires\Profile;

/**
 * @return bool
 */
function plugin_archires_install()
{
    $migration = new Migration(PLUGIN_ARCHIRES_VERSION);

    ImpactCompound::install($migration);
    ImpactContext::install($migration);
    ImpactItem::install($migration);
    ImpactRelation::install($migration);

    CronTask::Register(
        Archires::class,
        'CreateNetworkArchitecture',
        WEEK_TIMESTAMP,
        ['state' => CronTask::STATE_DISABLE]
    );

    Profile::initProfile();
    Profile::createFirstAccess($_SESSION['glpiactiveprofile']['id']);

    return true;
}

/**
 * @return bool
 */
function plugin_archires_uninstall()
{

    ImpactCompound::uninstall();
    ImpactContext::uninstall();
    ImpactItem::uninstall();
    ImpactRelation::uninstall();


    CronTask::unregister("archires");

    //Delete rights associated with the plugin
    $profileRight = new ProfileRight();
    foreach (Profile::getAllRights() as $right) {
        $profileRight->deleteByCriteria(['name' => $right['field']]);
    }

    Profile::removeRightsFromSession();

    return true;
}
