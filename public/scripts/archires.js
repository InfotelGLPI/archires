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

/* global GLPIImpact */

// Network architecture of an item (templates/impact_graph_view.html.twig): the impact graph of
// the core (js/impact.js), started with the colours, the start node and the graph data read
// from the data-* attributes of its view. The tab is loaded over AJAX, after this script.
(function () {
    function start(view) {
        if (view.dataset.archiresStarted === '1' || typeof GLPIImpact === 'undefined') {
            return;
        }
        view.dataset.archiresStarted = '1';

        GLPIImpact.prepareNetwork(
            $('#network_container'),
            JSON.parse(view.dataset.colors),
            view.dataset.startNode,
        );
        if (GLPIImpact.cy === null) {
            GLPIImpact.buildNetwork(
                JSON.parse(view.dataset.graph),
                JSON.parse(view.dataset.params),
                view.dataset.readonly === '1',
            );
        }
        $('#sviewgraph i').addClass('selected');
    }

    function startAll() {
        document.querySelectorAll('[data-archires-graph]').forEach(start);
    }

    $(startAll);
    $(document).on('ajaxComplete', startAll);
})();
