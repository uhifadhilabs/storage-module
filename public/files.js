/* ============================================================================
   FILES — the hub's behaviour.
   ----------------------------------------------------------------------------
   PORTED from the settled design's assets/files.js, which is the static twin of
   this file. Four things live here and nothing else does:

     1. THE FILTER ROW      — one chip run per query parameter, driving both
                              shapes of the result set at once.
     2. THE SHAPE TOGGLE    — thumbnails or a list. ONE result set, two shapes.
     3. THE FILE OVERLAY    — the thumbnail large, everything known about it
                              beside it, and the owning record one click away.
     4. THE REMOVAL FORM    — revealed by the danger button on a file's own page,
                              because a page's resting state must not be a
                              deletion form.

   WHAT THE DESIGN DID HERE AND THIS FILE DOES NOT. The design's overlay drew the
   guard — what you may do to a file — from the tile's own attributes. The guard
   is the OWNING RECORD's answer about THIS PERSON, so it is rendered where it is
   authoritative and nowhere else: the file's own page, which the overlay's header
   links to. An overlay that repeated a permission it had cached would eventually
   repeat it wrongly.

   Plain DOM, no framework: a module bundle must not make a host install a
   Stimulus controller before somebody can look at a photograph. Filtering here
   hides DOM nodes; the same chips are also real query parameters, so a filtered
   hub is linkable.
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

    /* ---- 3. THE FILE OVERLAY -------------------------------------------- *
     * WHY AN OVERLAY AND NOT ONLY A PAGE. Browsing is comparing: you open one
     * photograph, then the next one on the same record, without losing the grid
     * you found them in. The arrows are the whole argument. The page at
     * /files/f/{key} exists too and shows the same facts, because a file must be
     * linkable — you cannot send somebody an overlay.
     *
     * The overlay is filled from the tile's own attributes, so opening one costs
     * NO request; the only thing fetched is the ~400px picture, and even that
     * comes through the permission check.
     * -------------------------------------------------------------------- */

    var NOIMG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" '
        + 'stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/>'
        + '<path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>';

    var open = null;   // the tile the overlay was opened from
    var pool = [];     // the files it can step through

    function esc(value) {
        return (value || '').replace(/[&<>"]/g, function (ch) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ch];
        });
    }

    function get(el, name) {
        return el.getAttribute('data-f-' + name) || '';
    }

    function stage(el) {
        var img = get(el, 'img');
        if (img) {
            return '<img src="' + esc(img) + '" alt="' + esc(get(el, 'name')) + '">';
        }
        if ('wait' === get(el, 'thumb')) {
            return '<div class="noimg">' + NOIMG + 'the small picture is still being made<br>the original is here and downloadable</div>';
        }
        if ('failed' === get(el, 'thumb')) {
            return '<div class="noimg">' + NOIMG + 'no small picture could be made<br>the original is untouched</div>';
        }

        return '<div class="noimg">' + NOIMG + esc(get(el, 'mime')) + '<br>nothing to show small</div>';
    }

    function owner(el) {
        var inner = '<i class="' + esc(get(el, 'dot')) + '"></i><span class="mod">' + esc(get(el, 'modlabel'))
            + '</span><span class="sepdot">&middot;</span>' + esc(get(el, 'rec'));
        if (!get(el, 'rechref')) {
            return '<span class="f-owner off">' + inner + '</span>';
        }

        return '<a class="f-owner" href="' + esc(get(el, 'rechref')) + '">' + inner + '</a>';
    }

    var THUMB_WORD = { made: 'made', wait: 'being made', failed: 'could not be made', none: 'nothing to shrink' };

    function side(el) {
        var rows = [
            ['Owner', owner(el)],
            ['Taken', '<span class="mono val">' + esc(get(el, 'taken')) + '</span>'],
            ['Arrived', '<span class="mono val">' + esc(get(el, 'uploaded')) + '</span>'],
            ['Size', '<span class="mono val">' + esc(get(el, 'size')) + '</span>'],
            ['Type', '<span class="mono val">' + esc(get(el, 'mime')) + '</span>'],
            ['Its key', '<span class="mono val">' + esc(get(el, 'key')) + '</span>'],
            ['Small picture', '<span class="f-th ' + esc(get(el, 'thumb')) + '">'
                + (THUMB_WORD[get(el, 'thumb')] || '—') + '</span>'],
            ['Who can see it', '<span class="mono val">anyone who can see ' + esc(get(el, 'rec')) + '</span>'],
        ];
        var caption = get(el, 'caption');

        /* WHAT YOU MAY DO is deliberately two links and no verdict. Whether this
           file may be removed is the owning record's answer about this person,
           and it is given on the file's own page — never cached into a tile. */
        var acts = '<div class="f-acts">'
            + '<a class="f-act" href="' + esc(get(el, 'original')) + '">Open the original</a>'
            + (get(el, 'rechref') ? '<a class="f-act" href="' + esc(get(el, 'rechref')) + '">Open ' + esc(get(el, 'rec')) + '</a>' : '')
            + '<a class="f-act" href="' + esc(get(el, 'detail')) + '">What you may do &rarr;</a>'
            + '</div>';

        return '<h3>What this file is</h3>'
            + (caption
                ? '<p class="use" style="margin-top:2px">' + esc(caption) + '</p>'
                : '<p class="use" style="margin-top:2px">No caption &mdash; a caption belongs to the record, not to the file.</p>')
            + rows.map(function (r) {
                return '<div class="rln"><span>' + r[0] + '</span><span>' + r[1] + '</span></div>';
            }).join('')
            + '<h3>What you may do</h3>' + acts;
    }

    function overlay() {
        var node = document.querySelector('[data-f-overlay]');
        if (node) {
            return node;
        }
        node = document.createElement('div');
        node.className = 'f-ov';
        node.setAttribute('data-f-overlay', '');
        node.hidden = true;
        node.innerHTML = '<div class="f-ovback" data-f-close></div>'
            + '<div class="f-ovbox" role="dialog" aria-modal="true" aria-label="File" tabindex="-1">'
            + '<div class="f-ovhead"><span class="t" data-f-ovname></span>'
            + '<span class="sp"><a class="tgl" data-f-ovlink href="#">Open its own page</a>'
            + '<button class="tb-icon" type="button" data-f-close aria-label="Close">'
            + '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>'
            + '</button></span></div>'
            + '<div class="f-ovbody"><div class="f-ovstage" data-f-ovstage>'
            + '<button class="f-ovnav prev" type="button" data-f-step="-1" aria-label="Previous file"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg></button>'
            + '<button class="f-ovnav next" type="button" data-f-step="1" aria-label="Next file"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></button>'
            + '</div><div class="f-ovside modal-scroll" data-f-ovside></div></div></div>';
        document.body.appendChild(node);

        return node;
    }

    function show(el) {
        var node = overlay();
        /* The step arrows walk THE RESULT SET YOU ARE LOOKING AT — the files the
           filters left visible, in the order they are drawn. */
        pool = Array.prototype.filter.call(
            el.closest('[data-f-shapewrap], .c, body').querySelectorAll('.f-tile[data-f-id], tr[data-f-id]'),
            function (c) { return !c.hidden; }
        );
        open = el;
        node.querySelector('[data-f-ovname]').innerHTML = esc(get(el, 'name'))
            + ' &middot; <span class="d">' + esc(get(el, 'modlabel')) + ' &middot; ' + esc(get(el, 'rec')) + '</span>';
        node.querySelector('[data-f-ovlink]').setAttribute('href', get(el, 'detail'));
        var st = node.querySelector('[data-f-ovstage]');
        st.querySelectorAll(':scope > :not(.f-ovnav)').forEach(function (n) { n.remove(); });
        st.insertAdjacentHTML('afterbegin', stage(el));
        node.querySelector('[data-f-ovside]').innerHTML = side(el);
        var many = pool.length > 1;
        node.querySelectorAll('.f-ovnav').forEach(function (b) { b.hidden = !many; });
        node.hidden = false;
        node.querySelector('.f-ovbox').focus();
    }

    function step(delta) {
        if (!open) {
            return;
        }
        var at = pool.indexOf(open);
        var next = pool[(at + delta + pool.length) % pool.length];
        if (next) {
            show(next);
        }
    }

    function close() {
        var node = document.querySelector('[data-f-overlay]');
        if (node) {
            node.hidden = true;
        }
        if (open && open.focus) {
            open.focus();
        }
        open = null;
    }

    document.addEventListener('click', function (event) {
        if (event.target.closest('[data-f-close]')) {
            close();
            return;
        }
        var stepper = event.target.closest('[data-f-step]');
        if (stepper) {
            step(parseInt(stepper.getAttribute('data-f-step'), 10));
            return;
        }
        /* A click inside the owner LINK is navigation, not an overlay. */
        if (event.target.closest('a')) {
            return;
        }
        var el = event.target.closest('.f-tile[data-f-id], tr[data-f-id]');
        if (el) {
            show(el);
        }
    });

    document.addEventListener('keydown', function (event) {
        var node = document.querySelector('[data-f-overlay]');
        var isOpen = node && !node.hidden;
        if (isOpen && 'Escape' === event.key) {
            close();
            return;
        }
        if (isOpen && 'ArrowLeft' === event.key) {
            step(-1);
            return;
        }
        if (isOpen && 'ArrowRight' === event.key) {
            step(1);
            return;
        }
        if (!isOpen && ('Enter' === event.key || ' ' === event.key)) {
            var el = event.target.closest('.f-tile[data-f-id], tr[data-f-id]');
            if (el) {
                event.preventDefault();
                show(el);
            }
        }
    });

    /* ---- 4. THE REMOVAL FORM -------------------------------------------- *
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
