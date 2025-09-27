<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2023 Teclib' and contributors.
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

use GlpiPlugin\Archires\Impactrelation;

include('../../inc/includes.php');

ini_set("memory_limit", "-1");
ini_set("max_execution_time", "0");

global $DB, $GLPI_CACHE;
$dbu = new DbUtils();

$networkport = new NetworkPort();
$impactrelaction = new ImpactRelation();

$itemtype = ['NetworkEquipment', 'Unmanaged', 'Computer', 'Phone', 'Peripheral'];
$ports = $networkport->find(['itemtype' => $itemtype]);

foreach ($ports as $port) {
    $networkport_networkport = new NetworkPort_NetworkPort();
    $connections_1 = $networkport_networkport->find(['networkports_id_1' => $port['id']]);

    foreach ($connections_1 as $connection_1) {
        $networkport_impacted = new NetworkPort();
        if ($networkport_impacted->getFromDB($connection_1['networkports_id_2'])) {
            $itemtype_impacted = $networkport_impacted->fields['itemtype'];
            $items_id_impacted = $networkport_impacted->fields['items_id'];

            $impactrelaction->add([
                'itemtype_source' => $port['itemtype'],
                'items_id_source' => $port['items_id'],
                'itemtype_impacted' => $itemtype_impacted,
                'items_id_impacted' => $items_id_impacted
            ]);
        }
    }

    $connections_2 = $networkport_networkport->find(['networkports_id_2' => $port['id']]);

    foreach ($connections_2 as $connection_2) {
        $networkport_source = new NetworkPort();
        if ($networkport_source->getFromDB($connection_2['networkports_id_1'])) {
            $itemtype_source = $networkport_source->fields['itemtype'];
            $items_id_source = $networkport_source->fields['items_id'];

            $impactrelaction->add([
                'itemtype_source' => $itemtype_source,
                'items_id_source' => $items_id_source,
                'itemtype_impacted' => $port['itemtype'],
                'items_id_impacted' => $port['items_id']
            ]);
        }
    }
}
