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

define('PLUGIN_ARCHIRES_VERSION', '1.0.0');

if (!defined("PLUGIN_ARCHIRES_WEBDIR")) {
    define("PLUGIN_ARCHIRES_WEBDIR", Plugin::getWebDir("archires"));
}
// Init the hooks of the plugins -Needed
function plugin_init_archires()
{
    global $PLUGIN_HOOKS, $CFG_GLPI;

    $PLUGIN_HOOKS['csrf_compliant']['archires'] = true;
    $PLUGIN_HOOKS['change_profile']['archires'] = array('PluginArchiresProfile', 'initProfile');

    if (Session::getLoginUserID()) {
        Plugin::registerClass('PluginArchiresArchires', ['addtabon' => ['Computer','NetworkEquipment']]);

        Plugin::registerClass(
            'PluginArchiresProfile',
            array('addtabon' => 'Profile')
        );

        CronTask::Register('PluginAutoexportsearchesFiles', 'DeleteFile',
            MONTH_TIMESTAMP, ['state' => CronTask::STATE_DISABLE]);

//       $PLUGIN_HOOKS['post_item_form']['archires']  = [PluginArchiresNetworkEquipmenttask::class, 'addField'];
//       $PLUGIN_HOOKS['pre_item_form']['archires'] = [PluginArchiresNetworkEquipmenttask::class, 'messageWarning'];
//       $PLUGIN_HOOKS['pre_item_add']['archires'] =
//           ['TicketTask'         => ['PluginArchiresNetworkEquipmenttask',        'beforeAdd']];
//       $PLUGIN_HOOKS['item_add']['archires'] = ['TicketTask'            => ['PluginArchiresNetworkEquipmenttask',
//           'taskAdd']];
//       $PLUGIN_HOOKS['pre_item_update']['archires'] =
//           ['TicketTask'         => ['PluginArchiresNetworkEquipmenttask',        'beforeUpdate']];
//       $PLUGIN_HOOKS['pre_item_update']['archires'] = ['TicketTask'            => ['PluginArchiresNetworkEquipmenttask',
//           'taskUpdate']];
//
//       $PLUGIN_HOOKS['pre_show_item']['archires'] = ['PluginArchiresNetworkEquipmenttask', 'showWarning'];
//
//       if (Session::haveRight("plugin_archires", UPDATE)) {
//           $PLUGIN_HOOKS['config_page']['archires'] = 'front/config.form.php';
////           $PLUGIN_HOOKS["menu_toadd"]['archires']['config'] = 'PluginArchiresConfig';
//       }
//       $PLUGIN_HOOKS['add_javascript']['archires'][] = "lib/redips/redips-drag-min.js";
//       $PLUGIN_HOOKS['add_javascript']['archires'][] = "scripts/plugin_servicecatalog_drag-field-row.js";
    }
}

// Get the name and the version of the plugin - Needed
/**
 * @return array
 */
function plugin_version_archires()
{
    return array(
        'name' => __('Network architecture', 'archires'),
        'version' => PLUGIN_ARCHIRES_VERSION,
        'license' => 'GPLv2+',
        'author' => "Xavier CAILLAUD",
        'license' => 'GPLv2+',
        'homepage' => '',
        'requirements' => [
            'glpi' => [
                'min' => '10.0',
                'max' => '11.0',
                'dev' => false
            ]
        ]
    );
}
