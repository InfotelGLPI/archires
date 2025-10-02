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

use GlpiPlugin\Archires\Archires;
use GlpiPlugin\Archires\Profile;

define('PLUGIN_ARCHIRES_VERSION', '1.1.1');

global $CFG_GLPI;

if (!defined("PLUGIN_ARCHIRES_WEBDIR")) {
    define("PLUGIN_ARCHIRES_DIR", Plugin::getPhpDir("archires"));
    $root = $CFG_GLPI['root_doc'] . '/plugins/archires';
    define("PLUGIN_ARCHIRES_WEBDIR", $root);
}
// Init the hooks of the plugins -Needed
function plugin_init_archires()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['archires'] = true;
    $PLUGIN_HOOKS['change_profile']['archires'] = array(Profile::class, 'initProfile');

    if (Session::getLoginUserID()) {
        Plugin::registerClass(Archires::class, ['addtabon' => ['Computer','NetworkEquipment']]);

        Plugin::registerClass(
            Profile::class,
            array('addtabon' => 'Profile')
        );
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
        'homepage' => '',
        'requirements' => [
            'glpi' => [
                'min' => '11.0',
                'max' => '12.0',
                'dev' => false
            ]
        ]
    );
}
