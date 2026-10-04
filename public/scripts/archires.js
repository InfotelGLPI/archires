/**
 * -------------------------------------------------------------------------
 * Archires plugin for GLPI
 * -------------------------------------------------------------------------
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
