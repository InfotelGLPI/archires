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

use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Exception\Http\BadRequestHttpException;
use GlpiPlugin\Archires\Archires;
use GlpiPlugin\Archires\ImpactCompound;
use GlpiPlugin\Archires\ImpactItem;
use GlpiPlugin\Archires\ImpactRelation;

const DELTA_ACTION_ADD    = 1;
const DELTA_ACTION_UPDATE = 2;
const DELTA_ACTION_DELETE = 3;

global $CFG_GLPI;

// Send UTF8 Headers
header("Content-Type: application/json; charset=UTF-8");
Html::header_nocache();

switch ($_SERVER['REQUEST_METHOD']) {
    // GET request: build the impact graph for a given asset
    case 'GET':
        $action = $_GET["action"]  ?? "";

        $itemtype = $_GET["itemtype"] ?? "";
        // Check required params
        if (empty($itemtype)) {
            throw new BadRequestHttpException("Missing itemtype");
        }

        $item = getItemForItemtype($itemtype);
        if (!$item->canView()) {
            throw new AccessDeniedHttpException();
        }

        switch ($action) {
            case "search":
                $used     = $_GET["used"]     ?? "[]";
                $filter   = $_GET["filter"]   ?? "";
                $page     = $_GET["page"]     ?? 0;


                // Execute search
                $assets = Impact::searchAsset($itemtype, json_decode($used), $filter, $page);
                foreach ($assets['items'] as $index => $item) {
                    $item['image'] = Impact::getImpactIcon($itemtype, $item['id']);

                    $assets['items'][$index] = $item;
                }
                header('Content-Type: application/json');
                echo json_encode($assets);
                break;

            case 'load':
                $items_id = $_GET["items_id"]  ?? "";
                $view     = $_GET["view"]      ?? "graph";

                // Check required params
                if (empty($items_id)) {
                    throw new BadRequestHttpException("Missing itemtype or items_id");
                }

                if (!$item->can($items_id, READ)) {
                    throw new AccessDeniedHttpException();
                }

                // Check that the target asset exists
                if (!Impact::assetExist($itemtype, $items_id)) {
                    throw new BadRequestHttpException("Object[class=$itemtype, id=$items_id] doesn't exist");
                }

                // Prepare graph
                $item->getFromDB($items_id);
                $graph = Archires::buildGraph($item);
                $params = Archires::prepareParams($item);
                // The client flag means "the graph is read-only", i.e. true when
                // the user may NOT update the start node. Without the negation a
                // READ-only user received readonly=false and was offered editing.
                // Aligned with src/Archires.php and the POST branch below.
                $readonly = !$item->can($items_id, UPDATE);

                if ($view == "graph") {
                    // Output graph as json
                    header('Content-Type: application/json');
                    echo json_encode([
                        'graph'  => Impact::makeDataForCytoscape($graph),
                        'params' => $params,
                        'readonly' => $readonly,
                    ]);
                } elseif ($view == "list") {
                    // Output list as HTML
                    header('Content-Type: text/html');
                    Impact::displayListView($item, $graph);
                }
                break;

            default:
                throw new BadRequestHttpException("Missing or invalid 'action' parameter");
        }
        break;

    // Post request: update the store impact dependencies, compounds or items
    case 'POST':
        // Check required params
        if (!isset($_POST['impacts'])) {
            throw new BadRequestHttpException("Missing 'impacts' payload");
        }

        // Decode data (should be json)
        $data = Toolbox::jsonDecode($_POST['impacts'], true);
        if (!is_array($data)) {
            throw new BadRequestHttpException("Payload should be an array");
        }

        // Handle context for the starting node
        $context_em = new ImpactContext();
        if (
            !isset($data['context'])
            || !is_array($data['context'])
            || !isset($data['context']['node_id'])
            || !is_string($data['context']['node_id'])
        ) {
            throw new BadRequestHttpException("Missing or invalid context");
        }
        $context_data = $data['context'];

        // Get id and type from node_id (e.g. Computer::4 -> [Computer, 4]). A
        // forged node_id (missing delimiter, unknown itemtype, ...) must be
        // rejected before any lookup: previously a malformed value produced an
        // undefined index or a TypeError (getFromDB() on false), and an attacker
        // itemtype flowed straight into getItemForItemtype()/getFromDB().
        $start_node_details = explode(Archires::NODE_ID_DELIMITER, $context_data['node_id']);
        if (count($start_node_details) !== 2) {
            throw new BadRequestHttpException("Invalid node_id");
        }
        [$start_node_itemtype, $start_node_items_id] = $start_node_details;

        if (!is_a($start_node_itemtype, CommonDBTM::class, true)) {
            throw new BadRequestHttpException("Invalid itemtype");
        }
        $item = getItemForItemtype($start_node_itemtype);
        if (!($item instanceof CommonDBTM) || !$item->getFromDB($start_node_items_id)) {
            throw new BadRequestHttpException("Unknown item");
        }

        // Authorize BEFORE touching any data. ImpactItem::findForItem() defaults
        // to create_if_missing=true, so calling it first (as the code used to)
        // inserted an impact item row for any itemtype/items_id an unauthenticated
        // delta referenced. can($id, UPDATE) enforces the UPDATE right and the
        // entity boundary for the start node; the per-delta gate below re-checks
        // every other asset the payload touches.
        if (!$item->can($item->fields['id'], UPDATE)) {
            throw new AccessDeniedHttpException("Missing rights");
        }

        // Get impact_item for this node (safe to create now that the caller is
        // authorized to update it).
        $impact_item = ImpactItem::findForItem($item);
        $start_node_impact_item_id = $impact_item->fields['id'];

        // The start-node gate above only proves the caller may edit the graph's
        // entry point. Every delta below can reference OTHER assets (relation
        // endpoints, re-parented nodes) or global grouping rows, so each mutation
        // must be re-authorized against the asset it actually touches. can($id,
        // UPDATE) enforces both the UPDATE right and the entity boundary
        // (Session::haveAccessToEntity) for entity-aware assets, closing the
        // cross-entity gap where UPDATE on a single node granted write access to
        // the whole shared impact graph.
        $assert_can_update_asset = static function ($itemtype, $items_id): void {
            if (!is_string($itemtype) || !is_a($itemtype, CommonDBTM::class, true)) {
                throw new AccessDeniedHttpException("Missing rights");
            }
            $asset = getItemForItemtype($itemtype);
            if (!($asset instanceof CommonDBTM) || !$asset->can((int) $items_id, UPDATE)) {
                throw new AccessDeniedHttpException("Missing rights");
            }
        };

        // A compound is a pure visual grouping with no entity of its own; authorize
        // a mutation only when the caller can update every asset currently grouped
        // under it, so a forged id cannot alter or destroy another scope's grouping.
        $assert_can_update_compound = static function ($compound_id) use ($assert_can_update_asset): void {
            /** @var \DBmysql $DB */
            global $DB;

            $members = $DB->request([
                'SELECT' => ['itemtype', 'items_id'],
                'FROM'   => ImpactItem::getTable(),
                'WHERE'  => ['parent_id' => (int) $compound_id],
            ]);
            foreach ($members as $member) {
                $assert_can_update_asset($member['itemtype'], $member['items_id']);
            }
        };

        $context_id = 0;
        if (
            $impact_item->fields["impactcontexts_id"] == 0
            || $impact_item->fields["is_slave"] == 1
        ) {
            // There is no context OR we are slave to another context -> let's
            // create a new one
            $context_id = $context_em->add($context_data);

            // Set the context_id to be updated
            $data['items'][$start_node_impact_item_id]['impactcontexts_id'] = $context_id;
            $data['items'][$start_node_impact_item_id]['is_slave'] = 0;
        } else {
            // Update existing context
            $context_id = $impact_item->fields["impactcontexts_id"];
            $context_em->getFromDB($context_id);
            $context_data['id'] = $context_id;
            $context_em->update($context_data);
        }

        // Save impact relation delta
        $em = new ImpactRelation();
        foreach ($data['edges'] as $impact) {
            // Extract action
            $action = $impact['action'];
            unset($impact['action']);

            // A relation names a source and an impacted asset: require UPDATE on
            // BOTH endpoints, not merely on the graph's start node.
            $assert_can_update_asset($impact['itemtype_source'] ?? null, $impact['items_id_source'] ?? 0);
            $assert_can_update_asset($impact['itemtype_impacted'] ?? null, $impact['items_id_impacted'] ?? 0);

            switch ($action) {
                case DELTA_ACTION_ADD:
                    $em->add($impact);
                    break;

                case DELTA_ACTION_UPDATE:
                    $edge['id']   = ImpactRelation::getIDFromInput($impact);
                    $edge['name'] = $impact['name'];
                    $em->update($edge);
                    break;

                case DELTA_ACTION_DELETE:
                    $impact['id'] = ImpactRelation::getIDFromInput($impact);
                    $em->delete($impact);
                    break;

                default:
                    break;
            }
        }

        // Save impact compound delta
        $em = new ImpactCompound();
        foreach ($data['compounds'] as $id => $compound) {
            // Extract action
            $action = $compound['action'];
            unset($compound['action']);

            // ADD carries a client-side temporary id with no row yet, so there is
            // nothing to re-authorize (its future members are gated in the items
            // loop). UPDATE/DELETE target an existing compound: gate them on the
            // assets it currently groups.
            if ($action === DELTA_ACTION_UPDATE || $action === DELTA_ACTION_DELETE) {
                $assert_can_update_compound($id);
            }

            switch ($action) {
                case DELTA_ACTION_ADD:
                    $newCompoundID = $em->add($compound);

                    // Update id reference in impactitem
                    // This is needed because some nodes might have this compound
                    // temporary id as their parent id
                    foreach ($data['items'] as $nodeID => $node) {
                        if ($node['parent_id'] === $id) {
                            $data['items'][$nodeID]['parent_id'] = $newCompoundID;
                        }
                    }
                    break;

                case DELTA_ACTION_UPDATE:
                    $compound['id'] = $id;
                    $em->update($compound);
                    break;

                case DELTA_ACTION_DELETE:
                    $em->delete(['id' => $id]);
                    break;

                default:
                    break;
            }
        }

        // Save impact item delta
        $em = new ImpactItem();
        foreach ($data['items'] as $id => $impactItem) {
            // Extract action
            $action = $impactItem['action'];
            unset($impactItem['action']);

            switch ($action) {
                case DELTA_ACTION_UPDATE:
                    $impactItem['id'] = $id;

                    // Resolve this impact item to its underlying asset and require
                    // UPDATE on it (entity boundary included) so a caller cannot
                    // move or re-parent nodes bound to assets outside their scope.
                    $current = new ImpactItem();
                    if (!$current->getFromDB($id)) {
                        throw new BadRequestHttpException("Unknown impact item");
                    }
                    $assert_can_update_asset($current->fields['itemtype'], $current->fields['items_id']);

                    // If this is not the starting node, check for context update
                    if ($id !== $start_node_impact_item_id) {
                        $em->getFromDB($id);

                        // If this node has no context -> make it a slave
                        if ($em->fields['impactcontexts_id'] == 0) {
                            $impactItem['impactcontexts_id'] = $context_id;
                            $impactItem['is_slave'] = 1;
                        }
                    }

                    $em->update($impactItem);
                    break;
            }
        }

        header('Content-Type: application/javascript');
        break;
}
