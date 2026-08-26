/* ============================================================================
   FILES — the hub's behaviour.
   ----------------------------------------------------------------------------
   PORTED from the settled design's assets/files.js, which is the static twin of
   this file. Three things live here and nothing else does:

     1. THE FILTER ROW      — one chip run per query parameter, driving both
                              shapes of the result set at once.
     2. THE SHAPE TOGGLE    — thumbnails or a list. ONE result set, two shapes.
     3. THE REMOVAL FORM    — revealed by the danger button on a file's own page,
                              because a page's resting state must not be a
                              deletion form.

   THE FILE OVERLAY IS NOT HERE. It is the bundle's one SHAREABLE component — an
   observation's photos card opens the same overlay this hub does — so it lives
   where any module can reach it, and the hub is a consumer of it like any other
   surface. Nothing in this file knows the overlay exists; the tiles and rows
   carry the component's own data contract and it takes them from there.
   → templates/overlay/_preview.html.twig · assets/controllers/preview_controller.js

   Plain DOM, no framework: the filter row and the shape toggle are the HUB's
   own, they run on the hub's own screens only, and nothing outside this bundle
   asks for them. Filtering here hides DOM nodes; the same chips are also real
   query parameters, so a filtered hub is linkable.
   ========================================================================== */

(function () {
    'use strict';

    /* ---- 1. THE FILTER ROW ---------------------------------------------- *
     * One run of chips per parameter; a run has exactly one chip on. Every
     * chip is a query parameter and nothing more, which is what lets the same
     * row drive the grid, the list and the count.
     * → GET /files?module=&area=&kind=&day=&thumb=&q=
     * -------------------------------------------------------------------- */

    function state(bar) {
        var picked = {};
        bar.querySelectorAll('[data-f-filter].on').forEach(function (chip) {
            picked[chip.getAttribute('data-f-filter')] = chip.getAttribute('data-f-value');
        });
        var box = bar.querySelector('[data-f-search]');
        picked.q = (box ? box.value : '').trim().toLowerCase();

        return picked;
    }

    function keeps(el, picked) {
        if (picked.mod && el.getAttribute('data-f-mod') !== picked.mod) {
            return false;
        }
        if (picked.area && el.getAttribute('data-f-area') !== picked.area) {
            return false;
        }
        if (picked.kind && el.getAttribute('data-f-kind') !== picked.kind) {
            return false;
        }
        if (picked.day && el.getAttribute('data-f-day') !== picked.day) {
            return false;
        }
        if (picked.thumb) {
            var made = 'made' === el.getAttribute('data-f-thumb');
            if ('made' === picked.thumb && !made) {
                return false;
            }
            if ('!made' === picked.thumb && made) {
                return false;
            }
        }
        if (picked.q) {
            /* The file NAME and the OWNING RECORD's id only — the two things a
               person actually remembers. Never the caption: a caption is the
               record's, not the file's. */
            var hay = (el.getAttribute('data-f-name') + ' ' + el.getAttribute('data-f-rec')).toLowerCase();
            if (hay.indexOf(picked.q) < 0) {
                return false;
            }
        }

        return true;
    }

    function apply(bar) {
        var scope = bar.parentElement;
        var picked = state(bar);
        var kept = 0;

        scope.querySelectorAll('.f-tile[data-f-id]').forEach(function (tile) {
            var ok = keeps(tile, picked);
            tile.hidden = !ok;
            if (ok) {
                kept += 1;
            }
        });
        scope.querySelectorAll('tr[data-f-id]').forEach(function (row) {
            row.hidden = !keeps(row, picked);
        });

        var count = bar.querySelector('[data-f-count]');
        if (count) {
            var total = count.textContent.split(' of ').pop();
            count.innerHTML = '<b>' + kept + '</b> of ' + total;
        }
        var empty = scope.querySelector('[data-f-empty]');
        if (empty) {
            empty.hidden = 0 !== kept;
        }
        var wrap = scope.querySelector('[data-f-shapewrap]');
        if (wrap) {
            wrap.hidden = 0 === kept;
        }
    }

    document.addEventListener('click', function (event) {
        var chip = event.target.closest('[data-f-filter]');
        if (!chip) {
            return;
        }
        var bar = chip.closest('[data-f-filters]');
        var run = chip.getAttribute('data-f-filter');
        bar.querySelectorAll('[data-f-filter="' + run + '"]').forEach(function (other) {
            other.classList.toggle('on', other === chip);
        });
        apply(bar);
    });

    document.addEventListener('input', function (event) {
        var box = event.target.closest('[data-f-search]');
        if (box) {
            apply(box.closest('[data-f-filters]'));
        }
    });

    /* ---- 2. THE SHAPE TOGGLE -------------------------------------------- *
     * Thumbnails or a list. It is a per-person preference, not a filter: it
     * survives the page, and it changes nothing about which files answer.
     * -------------------------------------------------------------------- */

    function shapeTo(bar, shape) {
        var scope = bar.parentElement;
        bar.querySelectorAll('[data-f-shape]').forEach(function (b) {
            b.classList.toggle('on', b.getAttribute('data-f-shape') === shape);
        });
        var grid = scope.querySelector('[data-f-grid]');
        var list = scope.querySelector('[data-f-listwrap]');
        if (grid) {
            grid.hidden = 'grid' !== shape;
        }
        if (list) {
            list.hidden = 'list' !== shape;
        }
        try {
            localStorage.setItem('uhifadhi-files-shape', shape);
        } catch (ignored) {
            /* A browser refusing storage is not a reason to refuse the toggle. */
        }
    }

    document.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-f-shape]');
        if (btn) {
            shapeTo(btn.closest('[data-f-filters]'), btn.getAttribute('data-f-shape'));
        }
    });

    /* ---- 3. THE REMOVAL FORM -------------------------------------------- *
     * REMOVE, NEVER DELETE — and the record keeps a line saying it happened, so
     * the form asks for a reason before it will submit. It is revealed rather
     * than always drawn: a file's page must not sit there looking like a
     * deletion form.
     * -------------------------------------------------------------------- */

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

    /* ---- boot ----------------------------------------------------------- */

    function boot() {
        document.querySelectorAll('[data-f-filters]').forEach(function (bar) {
            var want = 'grid';
            try {
                want = localStorage.getItem('uhifadhi-files-shape') || 'grid';
            } catch (ignored) {
                /* No storage, no remembered preference. The default still works. */
            }
            shapeTo(bar, want);
        });
    }

    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    /* Widgets that arrive after this file booted (the library composes previews)
       ask to be counted; the overlay, the filters and the shape toggle are all
       delegated, so nothing else needs re-arming. */
    window.UHIF = { rescan: boot };
}());
