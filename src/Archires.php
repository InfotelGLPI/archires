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

namespace GlpiPlugin\Archires;

use CommonDBTM;
use CommonGLPI;
use Dropdown;
use Glpi\Application\View\TemplateRenderer;
use Html;
use Impact;
use Infocom;
use Migration;
use NetworkPort;
use NetworkPort_NetworkPort;
use Problem;
use Session;
use Ticket;
use User;

class Archires extends CommonGLPI
{
    // Constants used to express the direction or "flow" of a graph
    // Theses constants can also be used to express if an edge is reachable
    // when exploring the graph forward, backward or both (0b11)
    public const DIRECTION_FORWARD    = 0b01;
    public const DIRECTION_BACKWARD   = 0b10;

    // Default colors used for the edges of the graph according to their flow
    public const DEFAULT_COLOR            = 'black';   // The edge is not accessible from the starting point of the graph
    public const IMPACT_COLOR             = '#ff3418'; // Forward
    public const DEPENDS_COLOR            = '#1c76ff'; // Backward
    public const IMPACT_AND_DEPENDS_COLOR = '#ca29ff'; // Forward and backward

    public const NODE_ID_DELIMITER = "::";
    public const EDGE_ID_DELIMITER = "->";

    // Consts for depth values
    public const DEFAULT_DEPTH = 5;
    public const MAX_DEPTH = 10;
    public const NO_DEPTH_LIMIT = 10000;

    public static $rightname = 'plugin_archires';

    public static function getTypeName($nb = 0)
    {
        return __('Network architecture', 'archires');
    }

    public static function getIcon()
    {
        return "ti ti-topology-star";
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        /** @var \DBmysql $DB */
        global $DB;

        // Class of the current item
        $class = get_class($item);

        // Only enabled for CommonDBTM
        if (!is_a($item, "CommonDBTM", true)) {
            throw new \InvalidArgumentException(
                "Argument \$item ($class) must be a CommonDBTM."
            );
        }

        $is_enabled_asset = self::isEnabled($class);
        $is_itil_object = is_a($item, "CommonITILObject", true);

        // Check if itemtype is valid
        if (!$is_enabled_asset && !$is_itil_object) {
            throw new \InvalidArgumentException(
                "Argument \$item ($class) is not a valid target for network architecture."
            );
        }

        if (
            !$_SESSION['glpishow_count_on_tabs']
            || !isset($item->fields['id'])
            || $is_itil_object
        ) {
            // Count is disabled in config OR no item loaded OR ITIL object -> no count
            $total = 0;
        } elseif ($is_enabled_asset) {
            // If on an asset, get the number of its direct dependencies
            $total = count($DB->request([
                'FROM'  => Impactrelation::getTable(),
                'WHERE' => [
                    'OR' => [
                        [
                            // Source item is our item
                            'itemtype_source' => get_class($item),
                            'items_id_source' => $item->fields['id'],
                        ],
                        [
                            // Impacted item is our item AND source item is enabled
                            'itemtype_impacted' => get_class($item),
                            'items_id_impacted' => $item->fields['id'],
                            'itemtype_source'   => Impact::getEnabledItemtypes(),
                        ],
                    ],
                ],
            ]));
        }

        return self::createTabEntry(__('Network architecture', 'archires'), $total);

    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {

        // Impact analysis should not be available outside of central
        if (Session::getCurrentInterface() !== "central") {
            return false;
        }

        $class = get_class($item);

        // Only enabled for CommonDBTM
        if (!is_a($item, "CommonDBTM")) {
            throw new \InvalidArgumentException(
                "Argument \$item ($class) must be a CommonDBTM)."
            );
        }

        $ID = $item->fields['id'];

        // Don't show the impact analysis on new object
        if ($item->isNewID($ID)) {
            return false;
        }

        // Check READ rights
        $itemtype = $item->getType();
        if (!$itemtype::canView()) {
            return false;
        }

        // For an ITIL object, load the first linked element by default
        //        if (is_a($item, "CommonITILObject")) {
        //            $linked_items = $item->getLinkedItems();
        //
        //            // Search for a valid linked item of this ITILObject
        //            $items_data = [];
        //            foreach ($linked_items as $itemtype => $linked_item_ids) {
        //                $class = $itemtype;
        //                if (self::isEnabled($class)) {
        //                    $item = new $class();
        //                    foreach ($linked_item_ids as $linked_item_id) {
        //                        if (!$item->getFromDB($linked_item_id)) {
        //                            continue;
        //                        }
        //                        $items_data[] = [
        //                            'itemtype' => $itemtype,
        //                            'items_id' => $linked_item_id,
        //                            'name'     => $item->getNameID(),
        //                        ];
        //                    }
        //                }
        //            }
        //
        //            // No valid linked item were found, tab shouldn't be visible
        //            if (empty($items_data)) {
        //                return false;
        //            }
        //
        //            Impact::printAssetSelectionForm($items_data);
        //        }

        // Check is the impact analysis is enabled for $class
        if (!Impact::isEnabled($class)) {
            return false;
        }

        // Build graph and params
        $graph = self::buildGraph($item, true);
        $params = self::prepareParams($item);
        $readonly = !$item->can($item->fields['id'], UPDATE);

        // Print header
        self::printHeader(Impact::makeDataForCytoscape($graph), $params, $readonly);

        // Displays views
        self::displayGraphView($item);

        //        $graphForList = Impact::buildGraph($item);
        //        Impact::displayListView($item, $graphForList, true);

        // Select view
        echo Html::scriptBlock("
         // Select default view
         $(document).ready(function() {
            if (location.hash == '#list') {
               showListView();
            } else {
               showGraphView();
            }
         });
      ");


        return true;
    }

    /**
     * Display the impact analysis as an interactive graph
     *
     * @param CommonDBTM $item    starting point of the graph
     */
    public static function displayGraphView(
        CommonDBTM $item
    ) {
        Impact::loadLibs();

        echo '<div id="impact_graph_view">';
        self::prepareImpactNetwork($item);
        echo '</div>';
    }

    /**
     * Prepare the impact network
     *
     * @since 9.5
     *
     * @param CommonDBTM $item The specified item
     */
    public static function prepareImpactNetwork(CommonDBTM $item)
    {
        // Load requirements
        self::printImpactNetworkContainer();
        self::printShowOngoingDialog();
        self::printEditCompoundDialog();
        self::printEditEdgeDialog();
        echo Html::script("js/impact.js");

        // Load backend values
        $default   = self::DEFAULT_COLOR;
        $forward   = self::IMPACT_COLOR;
        $backward  = self::DEPENDS_COLOR;
        $both      = self::IMPACT_AND_DEPENDS_COLOR;
        $start_node = self::getNodeID($item);

        // Bind the backend values to the client and start the network
        echo  Html::scriptBlock("
         $(function() {
            GLPIImpact.prepareNetwork(
               $(\"#network_container\"),
               {
                  default : '$default',
                  forward : '$forward',
                  backward: '$backward',
                  both    : '$both',
               },
               '$start_node'
            )
         });
      ");
    }

    /**
     * Load the "show ongoing tickets" dialog
     *
     * @since 9.5
     */
    public static function printShowOngoingDialog()
    {
        // This dialog will be built dynamically by the front end
        TemplateRenderer::getInstance()->display('impact/ongoing_modal.html.twig');
    }

    /**
     * Load the "edit compound" dialog
     *
     * @since 9.5
     */
    public static function printEditCompoundDialog()
    {
        TemplateRenderer::getInstance()->display('impact/edit_compound_modal.html.twig');
    }

    /**
     * Load the "edit edge" dialog
     */
    private static function printEditEdgeDialog(): void
    {
        TemplateRenderer::getInstance()->display('impact/edit_edge_modal.html.twig');
    }

    /**
     * Load the impact network container
     *
     * @since 9.5
     */
    public static function printImpactNetworkContainer()
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        $action = PLUGIN_ARCHIRES_WEBDIR . '/ajax/archires.php';
        $formName = "form_impact_network";

        echo "<form name=\"$formName\" action=\"$action\" method=\"post\" class='no-track'>";
        echo "<table class='tab_cadre_fixe network-table'>";
        echo '<tr><td class="network-parent">';
        echo '<span id="help_text"></span>';

        echo '<div id="network_container"></div>';
        echo '<img class="impact-drop-preview">';

        echo '<div class="impact-side">';

        echo '<div class="impact-side-panel">';

        echo '<div class="impact-side-add-node">';
        echo '<h3>' . __('Add assets') . '</h3>';
        echo '<div class="impact-side-select-itemtype">';

        echo Html::input("impact-side-filter-itemtypes", [
            'id' => 'impact-side-filter-itemtypes',
            'placeholder' => __('Filter itemtypes...'),
        ]);

        echo '<div class="impact-side-filter-itemtypes-items">';
        $itemtypes = $CFG_GLPI["impact_asset_types"];
        // Sort by translated itemtypes
        uksort($itemtypes, function ($a, $b) {
            return strcasecmp($a::getTypeName(), $b::getTypeName());
        });
        foreach ($itemtypes as $itemtype => $icon) {
            // Do not display this itemtype if the user doesn't have READ rights
            if (!Session::haveRight($itemtype::$rightname, READ)) {
                continue;
            }

            //            $plugin_icon = Plugin::doHookFunction(\Glpi\Plugin\Hooks::SET_ITEM_IMPACT_ICON, [
            //                'itemtype' => $itemtype,
            //                'items_id' => 0
            //            ]);
            //            if ($plugin_icon && is_string($plugin_icon)) {
            //                $icon = ltrim($plugin_icon, '/');
            //            }

            // Skip if not enabled
            if (!self::isEnabled($itemtype)) {
                continue;
            }

            $icon = self::checkIcon($icon);

            echo '<div class="impact-side-filter-itemtypes-item">';
            echo '<h4><img class="impact-side-icon" src="' . $CFG_GLPI['root_doc'] . '/' . $icon . '" title="' . $itemtype::getTypeName() . '" data-itemtype="' . $itemtype . '">';
            echo "<span>" . $itemtype::getTypeName() . "</span></h4>";
            echo '</div>'; // impact-side-filter-itemtypes-item
        }
        echo '</div>'; // impact-side-filter-itemtypes-items
        echo '</div>'; // <div class="impact-side-select-itemtype">

        echo '<div class="impact-side-search">';
        echo '<h4><i class="fas fa-chevron-left"></i><img><span></span></h4>';
        echo Html::input("impact-side-filter-assets", [
            'id' => 'impact-side-filter-assets',
            'placeholder' => __('Filter assets...'),
        ]);

        echo '<div class="impact-side-search-panel">';
        echo '<div class="impact-side-search-results"></div>';

        echo '<div class="impact-side-search-more">';
        echo '<h4><i class="fas fa-chevron-down"></i>' . __("More...") . '</h4>';
        echo '</div>'; // <div class="impact-side-search-more">

        echo '<div class="impact-side-search-no-results">';
        echo '<p>' . __("No results") . '</p>';
        echo '</div>'; // <div class="impact-side-search-no-results">

        echo '<div class="impact-side-search-spinner">';
        echo '<i class="fas fa-spinner fa-2x fa-spin"></i>';
        echo '</div>'; // <div class="impact-side-search-spinner">

        echo '</div>'; // <div class="impact-side-search-panel">

        echo '</div>'; // <div class="impact-side-search">

        echo '</div>'; // div class="impact-side-add-node">

        echo '<div class="impact-side-settings">';
        echo '<h3>' . __('Settings') . '</h3>';

        echo '<h4>' . __('Visibility') . '</h4>';
        echo '<div class="impact-side-settings-item">';
        echo Html::getCheckbox([
            'id'      => "toggle_impact",
            'name'    => "toggle_impact",
            'checked' => "true",
        ]);
        echo '<span class="impact-checkbox-label">' . __("Show impact") . '</span>';
        echo '</div>';

        echo '<div class="impact-side-settings-item">';
        echo Html::getCheckbox([
            'id'      => "toggle_depends",
            'name'    => "toggle_depends",
            'checked' => "true",
        ]);
        echo '<span class="impact-checkbox-label">' . __("Show depends") . '</span>';
        echo '</div>';

        echo '<h4>' . __('Colors') . '</h4>';
        echo '<div class="impact-side-settings-item">';
        Html::showColorField("depends_color", []);
        echo '<span class="impact-checkbox-label">' . __("Depends") . '</span>';
        echo '</div>';

        echo '<div class="impact-side-settings-item">';
        Html::showColorField("impact_color", []);
        echo '<span class="impact-checkbox-label">' . __("Impact") . '</span>';
        echo '</div>';

        echo '<div class="impact-side-settings-item">';
        Html::showColorField("impact_and_depends_color", []);
        echo '<span class="impact-checkbox-label">' . __("Impact and depends") . '</span>';
        echo '</div>';

        echo '<h4>' . __('Max depth') . '</h4>';
        echo '<div class="impact-side-settings-item">';
        echo '<input id="max_depth" type="range" class="impact-range" min="1" max ="10" step="1" value="5"><span id="max_depth_view" class="impact-checkbox-label"></span>';
        echo '</div>';

        echo '</div>'; // div class="impact-side-settings">

        echo '<div class="impact-side-search-footer"></div>';
        echo '</div>'; // div class="impact-side-panel">

        echo '<ul>';
        echo '<li id="save_impact" title="' . __("Save") . '"><i class="fa-fw far fa-save"></i></li>';
        echo '<li id="impact_undo" class="impact-disabled" title="' . __("Undo") . '"><i class="fa-fw fas fa-undo"></i></li>';
        echo '<li id="impact_redo" class="impact-disabled" title="' . __("Redo") . '"><i class="fa-fw fas fa-redo"></i></li>';
        echo '<li class="impact-separator"></li>';
        echo '<li id="add_node" title="' . __("Add asset") . '"><i class="fa-fw ti ti-plus"></i></li>';
        echo '<li id="add_edge" title="' . __("Add relation") . '"><i class="fa-fw ti ti-line"></i></li>';
        echo '<li id="add_compound" title="' . __("Add group") . '"><i class="far fa-fw fa-object-group"></i></li>';
        echo '<li id="delete_element" title="' . __("Delete element") . '"><i class="fa-fw ti ti-trash"></i></li>';
        echo '<li class="impact-separator"></li>';
        echo '<li id="export_graph" title="' . __("Download") . '"><i class="fa-fw ti ti-download"></i></li>';
        echo '<li id="toggle_fullscreen" title="' . __("Fullscreen") . '"><i class="fa-fw ti ti-maximize"></i></li>';
        echo '<li id="impact_settings" title="' . __("Settings") . '"><i class="fa-fw ti ti-adjustments"></i></li>';
        echo '</ul>';
        echo '<span class="impact-side-toggle"><i class="fa-fw ti ti-chevron-left"></i></span>';
        echo '</div>'; // <div class="impact-side impact-side-expanded">
        echo "</td></tr>";
        echo "</table>";
        Html::closeForm();
    }

    /**
     * Print the title and view switch
     *
     * @param string  $graph      The network graph (json)
     * @param string  $params     Params of the graph (json)
     * @param bool    $readonly   Is the graph editable ?
     */
    public static function printHeader(
        string $graph,
        string $params,
        bool $readonly
    ) {
        echo '<div class="impact-header">';
        echo "<h2>" . __('Network architecture', 'archires') . "</h2>";
        echo "<div id='switchview'>";
        //        echo "<a id='sviewlist' href='#list'><i class='pointer ti ti-list' title='" . __('View as list') . "'></i></a>";
        echo "<a id='sviewgraph' href='#graph'><i class='pointer ti ti-hierarchy-2' title='" . __('View graphical representation') . "'></i></a>";
        echo "</div>";
        echo "</div>";

        // View selection
        echo Html::scriptBlock("
         function showGraphView() {
            $('#impact_list_view').hide();
            $('#impact_graph_view').show();
            $('#sviewlist i').removeClass('selected');
            $('#sviewgraph i').addClass('selected');

            if (window.GLPIImpact !== undefined && GLPIImpact.cy === null) {
               GLPIImpact.buildNetwork($graph, $params, $readonly);
            }
         }

         function showListView() {
            $('#impact_graph_view').hide();
            $('#impact_list_view').show();
            $('#sviewgraph i').removeClass('selected');
            $('#sviewlist i').addClass('selected');
            $('#save_impact').removeClass('clean');
         }

         $('#sviewgraph').click(function() {
            showGraphView();
         });

//         $('#sviewlist').click(function() {
//            showListView();
//         });
      ");
    }


    /**
     * Get saved graph params for the current item
     *
     * @param CommonDBTM $item
     *
     * @return string $item
     */
    public static function prepareParams(CommonDBTM $item)
    {
        $impact_item = Impactitem::findForItem($item);

        $params = array_intersect_key($impact_item->fields, [
            'parent_id'         => 1,
            'impactcontexts_id' => 1,
            'is_slave'          => 1,
        ]);

        // Load context if exist
        if ($params['impactcontexts_id']) {
            $impact_context = Impactcontext::findForImpactItem($impact_item);

            if ($impact_context) {
                $params = $params + array_intersect_key(
                    $impact_context->fields,
                    [
                        'positions'                => 1,
                        'zoom'                     => 1,
                        'pan_x'                    => 1,
                        'pan_y'                    => 1,
                        'impact_color'             => 1,
                        'depends_color'            => 1,
                        'impact_and_depends_color' => 1,
                        'show_depends'             => 1,
                        'show_impact'              => 1,
                        'max_depth'                => 1,
                    ]
                );
            }
        }

        return json_encode($params);
    }
    /**
     * Build the impact graph starting from a node
     *
     * @since 9.5
     *
     * @param CommonDBTM $item Current item
     * @param boolean $recursive Each relation found will be explored from in both directions
     *
     * @return array Array containing edges and nodes
     */
    public static function buildGraph(CommonDBTM $item, $recursive = false)
    {
        $nodes = [];
        $edges = [];

        // Explore the graph forward
        self::buildGraphFromNode($nodes, $edges, $item, self::DIRECTION_FORWARD, [self::getNodeID($item) => true], $recursive);

        // Explore the graph backward
        self::buildGraphFromNode($nodes, $edges, $item, self::DIRECTION_BACKWARD, [self::getNodeID($item) => true], $recursive);

        // Add current node to the graph if no impact relations were found
        if (count($nodes) == 0) {
            self::addNode($nodes, $item);
        }

        // Add special flag to start node
        $nodes[self::getNodeID($item)]['start'] = 1;

        return [
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }


    /**
     * Check if the given itemtype is enabled in impact config
     *
     * @param string $itemtype
     * @return bool
     */
    public static function isEnabled(string $itemtype): bool
    {
        return true;//in_array($itemtype, self::getEnabledItemtypes());
    }

    /**
     * Create an ID for a node (itemtype::items_id)
     *
     * @param CommonDBTM  $item Name of the node
     *
     * @return string
     */
    public static function getNodeID(CommonDBTM $item)
    {
        return get_class($item) . self::NODE_ID_DELIMITER . $item->fields['id'];
    }

    /**
     * Create an ID for an edge (NodeID->NodeID)
     *
     * @param CommonDBTM  $itemA     First node of the edge
     * @param CommonDBTM  $itemB     Second node of the edge
     * @param int         $direction Direction of the edge : A to B or B to A ?
     *
     * @return string|null
     *
     * @throws \InvalidArgumentException
     */
    public static function getEdgeID(
        CommonDBTM $itemA,
        CommonDBTM $itemB,
        int $direction
    ) {
        switch ($direction) {
            case self::DIRECTION_FORWARD:
                return self::getNodeID($itemA) . self::EDGE_ID_DELIMITER . self::getNodeID($itemB);

            case self::DIRECTION_BACKWARD:
                return self::getNodeID($itemB) . self::EDGE_ID_DELIMITER . self::getNodeID($itemA);

            default:
                throw new \InvalidArgumentException(
                    "Invalid value for argument \$direction ($direction)."
                );
        }
    }

    /**
     * Check if the icon path is valid, if not return a fallback path
     *
     * @param string $icon_path
     * @return string
     */
    private static function checkIcon(string $icon_path): string
    {
        // Special case for images returned dynamicly
        if (strpos($icon_path, ".php") !== false) {
            return $icon_path;
        }

        // Check if icon exist on the filesystem
        $file_path = GLPI_ROOT . "/$icon_path";
        if (file_exists($file_path) && is_file($file_path)) {
            return $icon_path;
        }

        // Fallback "default" icon
        return "pics/impact/default.png";
    }


    /**
     * add data for node tooltip
     *
     * @param CommonDBTM $item
     * @return array
     */
    private static function addTooltip(CommonDBTM $item): array
    {
        $type = "";
        if (class_exists($item::class . "Type")) {
            $tabletype = getTableForItemType($item::class . "Type");
            $typefield = getForeignKeyFieldForTable($tabletype);
            $types_id = $item->fields[$typefield];
            $type = Dropdown::getDropdownName($tabletype, $types_id);
        }
        $states_id = "";
        if (isset($item->fields['states_id'])) {
            $states_id = Dropdown::getDropdownName("glpi_states", $item->fields['states_id']);
        }
        $infocom = new Infocom();
        $businesscriticities_id = "";
        if ($infocom->getFromDBforDevice($item::class, $item->getID())) {
            $businesscriticities_id
                = Dropdown::getDropdownName(
                    'glpi_businesscriticities',
                    $infocom->fields['businesscriticities_id']
                );
        }
        $tooltip = [__("Name") => $item->getFriendlyName(),
            _n("Type", "Types", 1) => $type,
            __("Status") => $states_id,
            _n("Business criticity", "Business criticities", 1) => $businesscriticities_id,
            __("Comments") => $item->fields['comment'],
        ];

        return $tooltip;
    }

    /**
     * Add a node to the node list if missing
     *
     * @param array      $nodes  Nodes of the graph
     * @param CommonDBTM $item   Node to add
     *
     * @since 9.5
     *
     * @return bool true if the node was missing, else false
     */
    private static function addNode(array &$nodes, CommonDBTM $item)
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        // Check if the node already exist
        $key = self::getNodeID($item);
        if (isset($nodes[$key])) {
            return false;
        }

        // Get web path to the image matching the itemtype from config
        $image_name = $CFG_GLPI["impact_asset_types"][get_class($item)] ?? "";

        //        $plugin_icon = Plugin::doHookFunction(\Glpi\Plugin\Hooks::SET_ITEM_IMPACT_ICON, [
        //            'itemtype' => get_class($item),
        //            'items_id' => $item->getID()
        //        ]);
        //        if ($plugin_icon && is_string($plugin_icon)) {
        //            $image_name = ltrim($plugin_icon, '/');
        //        }

        $image_name = self::checkIcon($image_name);

        $tooltip = self::addTooltip($item);

        // Define basic data of the new node
        $new_node = [
            'id'             => $key,
            'label'          => $item->getFriendlyName(),
            'itemtype'       => $item->getTypeName(),
            'image'          => $CFG_GLPI['root_doc'] . "/$image_name",
            'ITILObjects'    => $item->getITILTickets(true),
            'itemtype'       => $item::getTypeName(),
            'tooltip'        => $tooltip,
        ];

        // Only set GOTO link if the user have READ rights
        if ($item::canView()) {
            $new_node['link'] = $item->getLinkURL();
        }

        // Set incident badge if needed
        $nb_incidents = count($new_node['ITILObjects']['incidents']);
        $nb_problems = count($new_node['ITILObjects']['problems']);
        if ($nb_incidents + $nb_problems > 0) {
            $priority = 0;
            foreach ($new_node['ITILObjects']['incidents'] as $incident) {
                if ($priority < $incident['priority']) {
                    $priority = $incident['priority'];
                }
            }
            foreach ($new_node['ITILObjects']['problems'] as $problem) {
                if ($priority < $problem['priority']) {
                    $priority = $problem['priority'];
                }
            }

            if ($nb_problems && !$nb_incidents) {
                // If at least one problems and zero incidents, link to problems search
                $target = Problem::getSearchURL() . "?is_deleted=0&as_map=0&search=Search&itemtype=Problem";
            } else {
                // Link to tickets search
                $target = Ticket::getSearchURL() . "?is_deleted=0&as_map=0&search=Search&itemtype=Ticket";
            }

            $user = new User();
            $user->getFromDB(Session::getLoginUserID());
            $user->computePreferences();
            $new_node['badge'] = [
                'color'  => $user->fields["priority_$priority"],
                'count'  => $nb_incidents + $nb_problems,
                'target' => $target,
            ];
        }

        // Alter the label if we found some linked ITILObjects
        $itil_tickets_count = $new_node['ITILObjects']['count'];
        if ($itil_tickets_count > 0) {
            $new_node['label'] .= " ($itil_tickets_count)";
            $new_node['hasITILObjects'] = 1;
        }

        // Load or create a new ImpactItem object
        $impact_item = Impactitem::findForItem($item);

        // Load node position and parent
        $new_node['impactitem_id'] = $impact_item->fields['id'];
        $new_node['parent']        = $impact_item->fields['parent_id'];

        // If the node has a parent, add it to the node list aswell
        if (!empty($new_node['parent'])) {
            $compound = new ImpactCompound();
            $compound->getFromDB($new_node['parent']);

            if (!isset($nodes[$new_node['parent']])) {
                $nodes[$new_node['parent']] = [
                    'id'    => $compound->fields['id'],
                    'label' => $compound->fields['name'],
                    'color' => $compound->fields['color'],
                ];
            }
        }

        // Insert the node
        $nodes[$key] = $new_node;
        return true;
    }

    /**
     * Add an edge to the edge list if missing, else update it's direction
     *
     * @param array      $edges      Edges of the graph
     * @param string     $key        ID of the new edge
     * @param CommonDBTM $itemA      One of the node connected to this edge
     * @param CommonDBTM $itemB      The other node connected to this edge
     * @param int        $direction  Direction of the edge : A to B or B to A ?
     *
     * @since 9.5
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    private static function addEdge(
        array &$edges,
        string $key,
        CommonDBTM $itemA,
        CommonDBTM $itemB,
        int $direction,
        string $label
    ): void {
        // Just update the flag if the edge already exist
        if (isset($edges[$key])) {
            $edges[$key]['flag'] = $edges[$key]['flag'] | $direction;
            return;
        }

        // Assign 'from' and 'to' according to the direction
        switch ($direction) {
            case self::DIRECTION_FORWARD:
                $from = self::getNodeID($itemA);
                $to = self::getNodeID($itemB);
                break;
            case self::DIRECTION_BACKWARD:
                $from = self::getNodeID($itemB);
                $to = self::getNodeID($itemA);
                break;
            default:
                throw new \InvalidArgumentException(
                    "Invalid value for argument \$direction ($direction)."
                );
        }

        // Add the new edge
        $edges[$key] = [
            'id'     => $key,
            'source' => $from,
            'target' => $to,
            'flag'   => $direction,
            'label' => $label,
        ];
    }

    /**
     * Explore dependencies of the current item, subfunction of buildGraph()
     *
     * @since 9.5
     *
     * @param array      $edges          Edges of the graph
     * @param array      $nodes          Nodes of the graph
     * @param CommonDBTM $node           Current node
     * @param int        $direction      The direction in which the graph
     *                                   is being explored : DIRECTION_FORWARD
     *                                   or DIRECTION_BACKWARD
     * @param array      $explored_nodes List of nodes that have already been
     *                                   explored
     * @param boolean $recursive Should found relations be explored in both directions
     *
     * @throws InvalidArgumentException
     */
    private static function buildGraphFromNode(
        array &$nodes,
        array &$edges,
        CommonDBTM $node,
        int $direction,
        array $explored_nodes = [],
        $recursive = false
    ) {
        /** @var \DBmysql $DB */
        global $DB;

        // Source and target are determined by the direction in which we are
        // exploring the graph
        switch ($direction) {
            case self::DIRECTION_BACKWARD:
                $source = "source";
                $target = "impacted";
                break;
            case self::DIRECTION_FORWARD:
                $source = "impacted";
                $target = "source";
                break;
            default:
                throw new \InvalidArgumentException(
                    "Invalid value for argument \$direction ($direction)."
                );
        }

        // Get relations of the current node
        $relations = $DB->request([
            'FROM'   => ImpactRelation::getTable(),
            'WHERE'  => [
                'itemtype_' . $target => get_class($node),
                'items_id_' . $target => $node->fields['id'],
            ],
        ]);

        // Add current code to the graph if we found at least one impact relation
        if (count($relations)) {
            self::addNode($nodes, $node);
        }
        // Iterate on each relations found
        foreach ($relations as $related_item) {
            // Do not explore disabled itemtypes
            if (!self::isEnabled($related_item['itemtype_' . $source])) {
                continue;
            }

            // Add the related node
            if (!($related_node = getItemForItemtype($related_item['itemtype_' . $source]))) {
                continue;
            }
            $related_node->getFromDB($related_item['items_id_' . $source]);
            $label = $related_item['name'] ?? "";
            self::addNode($nodes, $related_node);

            // Add or update the relation on the graph
            $edgeID = self::getEdgeID($node, $related_node, $direction);
            self::addEdge($edges, $edgeID, $node, $related_node, $direction, $label);

            // Keep exploring from this node unless we already went through it
            $related_node_id = self::getNodeID($related_node);
            if (!isset($explored_nodes[$related_node_id])) {
                $explored_nodes[$related_node_id] = true;
                if ($recursive) {
                    self::buildGraphFromNode(
                        $nodes,
                        $edges,
                        $related_node,
                        self::DIRECTION_FORWARD,
                        $explored_nodes,
                        $recursive
                    );
                    self::buildGraphFromNode(
                        $nodes,
                        $edges,
                        $related_node,
                        self::DIRECTION_BACKWARD,
                        $explored_nodes,
                        $recursive
                    );
                } else {
                    self::buildGraphFromNode(
                        $nodes,
                        $edges,
                        $related_node,
                        $direction,
                        $explored_nodes,
                        $recursive
                    );
                }
            }
        }
    }

    public static function cronCreateNetworkArchitecture($task)
    {

        ini_set("memory_limit", "-1");
        ini_set("max_execution_time", "0");

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
                        'items_id_impacted' => $items_id_impacted,
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
                        'items_id_impacted' => $port['items_id'],
                    ]);
                }
            }
        }
    }

    public static function install(Migration $mig)
    {
        global $DB;

        $table = 'glpi_plugin_archires_impactrelations';
        if (!$DB->tableExists($table)) { //not installed

            $query = "CREATE TABLE `glpi_plugin_archires_impactrelations` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
  `itemtype_source` varchar(255) NOT NULL DEFAULT '',
  `items_id_source` int unsigned NOT NULL DEFAULT '0',
  `itemtype_impacted` varchar(255) NOT NULL DEFAULT '',
  `items_id_impacted` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unicity` (`itemtype_source`,`items_id_source`,`itemtype_impacted`,`items_id_impacted`),
  KEY `impacted_asset` (`itemtype_impacted`,`items_id_impacted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;";
            $DB->doQuery($query);


            $query = "CREATE TABLE `glpi_plugin_archires_impactcompounds` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT '',
  `color` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;";
            $DB->doQuery($query);

            $query = "CREATE TABLE `glpi_plugin_archires_impactitems` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
  `itemtype` varchar(255) NOT NULL DEFAULT '',
  `items_id` int unsigned NOT NULL DEFAULT '0',
  `parent_id` int unsigned NOT NULL DEFAULT '0',
  `impactcontexts_id` int unsigned NOT NULL DEFAULT '0',
  `is_slave` tinyint NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unicity` (`itemtype`,`items_id`),
  KEY `source` (`itemtype`,`items_id`),
  KEY `parent_id` (`parent_id`),
  KEY `impactcontexts_id` (`impactcontexts_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;";
            $DB->doQuery($query);

            $query = "CREATE TABLE `glpi_plugin_archires_impactcontexts` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;";
            $DB->doQuery($query);

        }

        return true;
    }


    public static function uninstall()
    {
        global $DB;

        if ($DB->tableExists('glpi_plugin_archires_impactrelations')) { //not installed
            $DB->dropTable("glpi_plugin_archires_impactrelations");
        }
        if ($DB->tableExists('glpi_plugin_archires_impactcompounds')) { //not installed
            $DB->dropTable("glpi_plugin_archires_impactcompounds");
        }
        if ($DB->tableExists('glpi_plugin_archires_impactitems')) { //not installed
            $DB->dropTable("glpi_plugin_archires_impactitems");
        }
        if ($DB->tableExists('glpi_plugin_archires_impactcontexts')) { //not installed
            $DB->dropTable("glpi_plugin_archires_impactcontexts");
        }
        return true;
    }
}
