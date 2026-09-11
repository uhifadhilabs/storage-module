/* ============================================================================
   FILES — the hub's behaviour.
   ----------------------------------------------------------------------------
   ONE thing lives here:

     THE REMOVAL FORM — revealed by the danger button on a file's own page,
                        because a page's resting state must not be a deletion
                        form.

   THE FILTER ROW IS NOT HERE ANY MORE, and that is the point. It used to hide
   DOM nodes client-side; it is now four dropdown chips, two pill runs, a search
   and a shape toggle that are all ORDINARY LINKS driving one GET, so the server
   answers with the files that survived and the grid, the list and the count can
   never disagree. The only scripting the row needs is opening a panel, which is
   a Stimulus controller because it arms itself wherever the row is included.
   → templates/files/_filters.html.twig · assets/controllers/files_filters_controller.js

   THE FILE OVERLAY IS NOT HERE EITHER. It is the bundle's one SHAREABLE
   component — an observation's photos card opens the same overlay this hub does
   — so it lives where any module can reach it, and the hub is a consumer of it
   like any other surface.
   → templates/overlay/_preview.html.twig · assets/controllers/preview_controller.js

   Plain DOM, no framework: the removal form is the HUB's own, it runs on the
   hub's own screens only, and nothing outside this bundle asks for it.
   ========================================================================== */

(function () {
    'use strict';

    /* REMOVE, NEVER DELETE — and the record keeps a line saying it happened, so
       the form asks for a reason before it will submit. It is revealed rather
       than always drawn: a file's page must not sit there looking like a
       deletion form. */

    document.addEventListener('click', function (event) {
        var form = document.querySelector('[data-f-removeform]');
        if (!form) {
            return;
        }
        if (event.target.closest('[data-f-removeopen]')) {
            form.hidden = false;
            var reason = form.querySelector('textarea');
            if (reason) {
                reason.focus();
            }
        }
        if (event.target.closest('[data-f-removecancel]')) {
            form.hidden = true;
        }
    });
}());
