import { Controller } from '@hotwired/stimulus';

/*
 * THE UPLOAD COMPONENT — the behaviour half of the one component every file in
 * the product arrives through. Its markup half is
 * templates/upload/_component.html.twig; its vocabulary is the SHELL's `.upl-*`,
 * and nothing here writes a class the frame does not already ship.
 *
 * TWO PRESENTATIONS, ONE BEHAVIOUR. A dropzone card, where receiving the file IS
 * the step, and an add tile, which is one cell of a grid of things already
 * attached. Both draw the same four moments — waiting, receiving, refused, kept
 * — because a file must behave the same way whichever door it came through.
 *
 * IT NEVER INVENTS AN ERROR MESSAGE. Every refusal drawn on a row or in a tile
 * is the sentence the endpoint sent, written by whoever refused: the storage for
 * a kind or a size, the owning module for a permission. The one sentence of this
 * component's own is the queue cap, because the queue is the only thing here the
 * server has no opinion about — every request carries one file.
 *
 * IT ASKS THE REMOVAL QUESTION IN THE TILE'S OWN BOX. No window.confirm, no
 * modal over the grid: the design puts the question where the file is, two words
 * at the house control height, and Keep puts the tile back exactly as it was.
 *
 * XHR FOR THE UPLOAD, fetch for the removal. Not a preference — XMLHttpRequest
 * is the only one of the two that reports upload progress, and a percentage per
 * file is half of what the design's uploading state IS.
 *
 * THE SCOPE IS THE GRID, NOT THIS ELEMENT. In the tile presentation this element
 * is one cell of the module's own evidence grid, and the tiles drawn while a
 * file is in flight are its siblings — so clicks are listened for on the parent,
 * which is also how a tile the module server-rendered gains a working remove
 * without the module writing a line of JavaScript.
 */

/* The glyphs the drawn states use. Inline because these are built in the
   browser, where the host's icon component cannot be reached — the same reason
   preview_controller.js carries its own. Copied from the component sheet. */
const GLYPH = {
    file:
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" ' +
        'stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7z"/>' +
        '<path d="M14 2v5h5"/></svg>',
    alert:
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" ' +
        'stroke-linejoin="round"><path d="M12 9v4"/><path d="M12 17h.01"/>' +
        '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>',
    photo:
        '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" ' +
        'stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/>' +
        '<circle cx="12" cy="13" r="3"/></svg>',
};

/* Decimal units, matching Uhifadhi\Storage\Model\Bytes: the number on a queue row
   and the number on the same file's page must agree, and the invoice for object
   storage is in decimal terabytes. */
const UNITS = ['B', 'KB', 'MB', 'GB', 'TB'];

const size = (bytes) => {
    let value = Math.max(0, bytes);
    let unit = 0;
    while (value >= 1000 && unit < UNITS.length - 1) {
        value /= 1000;
        unit += 1;
    }

    return `${value.toFixed(unit >= 2 ? 1 : 0)} ${UNITS[unit]}`;
};

const esc = (value) =>
    String(value ?? '').replace(/[&<>"]/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[ch]);

export default class extends Controller {
    initialize() {
        /* Before anything is wired, so a page may still change what it is about
           to become — the lifecycle the UX bundles publish. */
        this.dispatch('init', { prefix: 'storage:upload', detail: { element: this.element } });
    }

    connect() {
        this.tile = 'tile' === this.attr('presentation');
        /* The tiles this component draws are SIBLINGS in the module's grid, so
           the grid is what is listened to. In the zone presentation everything
           it draws is inside it. */
        this.scope = this.tile ? this.element.parentElement || this.element : this.element;
        /* The idle state, remembered rather than re-typed: the wording is the
           template's and returning to it must not invent a second copy. */
        this.idle = this.element.innerHTML;
        this.jobs = new Set();
        this.queued = 0;

        this.picker = document.createElement('input');
        this.picker.type = 'file';
        this.picker.multiple = true;
        this.picker.hidden = true;
        this.picker.accept = this.attr('accept');
        this.element.insertAdjacentElement('afterend', this.picker);

        this.onPick = () => {
            this.take(this.picker.files);
            this.picker.value = '';
        };
        this.onClick = this.handleClick.bind(this);
        this.onOver = this.handleOver.bind(this);
        this.onLeave = this.handleLeave.bind(this);
        this.onDrop = this.handleDrop.bind(this);

        this.picker.addEventListener('change', this.onPick);
        this.scope.addEventListener('click', this.onClick);
        this.element.addEventListener('dragover', this.onOver);
        this.element.addEventListener('dragleave', this.onLeave);
        this.element.addEventListener('drop', this.onDrop);

        this.dispatch('connect', { prefix: 'storage:upload', detail: { component: this } });
    }

    disconnect() {
        this.jobs.forEach((xhr) => xhr.abort());
        this.jobs.clear();
        this.picker.removeEventListener('change', this.onPick);
        this.scope.removeEventListener('click', this.onClick);
        this.element.removeEventListener('dragover', this.onOver);
        this.element.removeEventListener('dragleave', this.onLeave);
        this.element.removeEventListener('drop', this.onDrop);
        this.picker.remove();
    }

    /* ── what a click can mean ──────────────────────────────────────────── */

    handleClick(event) {
        const browse = event.target.closest('[data-upl-browse]');
        if (browse) {
            event.preventDefault();
            this.picker.click();

            return;
        }

        const retry = event.target.closest('[data-upl-retry]');
        if (retry) {
            this.retry(retry);

            return;
        }

        const cancel = event.target.closest('[data-upl-cancel]');
        if (cancel) {
            this.cancel(cancel);

            return;
        }

        const keep = event.target.closest('[data-upl-keep]');
        if (keep) {
            this.keep(keep);

            return;
        }

        const confirm = event.target.closest('[data-upl-confirm]');
        if (confirm) {
            this.remove(confirm);

            return;
        }

        const remove = event.target.closest('[data-upl-remove]');
        if (remove) {
            event.preventDefault();
            this.ask(remove);

            return;
        }

        /* The add tile itself opens the picker. The zone's only picker door is
           the "browse" word, because the whole card is a drop surface and a
           click anywhere on it would fire while somebody was reading it. */
        if (this.tile && event.target.closest('[data-upl-root]') === this.element) {
            this.picker.click();
        }
    }

    /* ── dragging ───────────────────────────────────────────────────────── */

    handleOver(event) {
        event.preventDefault();
        const count = event.dataTransfer ? event.dataTransfer.items.length : 0;
        if (this.tile) {
            this.element.classList.remove('idle');
            this.element.classList.add('over');
            this.label('Release to add');

            return;
        }
        this.element.classList.add('over');
        this.lead(`Release to upload — ${count} file${1 === count ? '' : 's'}`);
    }

    handleLeave(event) {
        /* A dragleave fires for every child the pointer crosses; only the one
           that genuinely left the box counts. */
        if (this.element.contains(event.relatedTarget)) {
            return;
        }
        this.rest();
    }

    handleDrop(event) {
        event.preventDefault();
        this.rest();
        this.take(event.dataTransfer ? event.dataTransfer.files : null);
    }

    /* Back out of drag-over, into whatever state the component was already in. */
    rest() {
        this.element.classList.remove('over');
        if (this.tile) {
            this.element.classList.add('idle');
            this.element.innerHTML = this.idle;

            return;
        }
        this.relead();
    }

    /* ── taking files ───────────────────────────────────────────────────── */

    take(files) {
        const chosen = Array.prototype.slice.call(files || []);
        if (0 === chosen.length) {
            return;
        }

        const room = Math.max(0, parseInt(this.attr('max-files'), 10) - this.queued);
        chosen.slice(0, room).forEach((file) => this.send(file));

        /* THE COMPONENT'S ONE SENTENCE OF ITS OWN. Every other refusal is the
           server's; this one is about the queue, and the queue is the only thing
           here the server has no opinion about — each request carries one file. */
        chosen.slice(room).forEach((file) => {
            const cap = this.attr('max-files');

            this.refuse(this.draw(file), file, `Only ${cap} files at a time. Nothing was written.`);
        });
    }

    send(file, into) {
        const shape = into || this.draw(file);
        this.queued += 1;
        this.relead();

        const body = new FormData();
        body.append('target', this.attr('target'));
        body.append('file', file);
        /* The conventional field as well as the header, so an endpoint reached
           without headers still carries a token. */
        body.append('_token', this.attr('token'));

        const xhr = new XMLHttpRequest();
        this.jobs.add(xhr);
        shape.xhr = xhr;
        shape.file = file;

        xhr.upload.addEventListener('progress', (event) => {
            if (event.lengthComputable) {
                this.progress(shape, Math.round((event.loaded / event.total) * 100));
            }
        });
        xhr.addEventListener('load', () => {
            this.jobs.delete(xhr);
            this.queued -= 1;
            let body_ = {};
            try {
                body_ = JSON.parse(xhr.responseText);
            } catch {
                body_ = {};
            }
            if (xhr.status >= 200 && xhr.status < 300) {
                this.keepIt(shape, body_);
            } else {
                this.refuse(shape, file, body_.error || 'That file could not be stored. Nothing was written.');
            }
        });
        xhr.addEventListener('error', () => {
            this.jobs.delete(xhr);
            this.queued -= 1;
            this.refuse(shape, file, 'That file could not be sent. Nothing was written.');
        });
        xhr.addEventListener('abort', () => {
            this.jobs.delete(xhr);
            this.queued -= 1;
            shape.el.remove();
            this.relead();
        });

        xhr.open('POST', this.attr('upload-url'));
        xhr.setRequestHeader('X-CSRF-Token', this.attr('token'));
        xhr.send(body);
    }

    retry(button) {
        const shape = this.shapeOf(button);
        if (!shape || !shape.file) {
            return;
        }
        const file = shape.file;
        const fresh = this.draw(file, shape.el);
        this.send(file, fresh);
    }

    cancel(button) {
        const shape = this.shapeOf(button);
        if (!shape) {
            return;
        }
        if (shape.xhr) {
            shape.xhr.abort();

            return;
        }
        shape.el.remove();
        this.relead();
    }

    /* ── the four moments, drawn ────────────────────────────────────────── */

    /* Receiving. In the zone that is a row in the queue; on a tile it is a new
       cell beside the add tile, which fills from the bottom and states its own
       percentage. Either way the box is the one the design states once. */
    draw(file, replacing) {
        const el = document.createElement(this.tile ? 'span' : 'div');
        if (this.tile) {
            el.className = 'upl-tile busy';
            el.innerHTML =
                '<span class="fill"></span>' +
                '<span class="pct">0%</span>' +
                `<span class="fn">${esc(file.name)}</span>` +
                '<span class="upl-bar"><i></i></span>' +
                '<button type="button" class="rm" data-upl-cancel aria-label="Cancel this upload">&times;</button>';
        } else {
            el.className = 'upl-file';
            el.innerHTML =
                GLYPH.file +
                `<span class="nm">${esc(file.name)}</span>` +
                `<span class="sz">${esc(size(file.size))}</span>` +
                '<span class="upl-bar"><i></i></span>' +
                '<span class="pct">0%</span>' +
                '<button type="button" class="upl-act x" data-upl-cancel aria-label="Cancel this upload">&times;</button>';
        }

        const shape = { el, file: file, xhr: null };
        el.uplShape = shape;

        if (replacing) {
            replacing.replaceWith(el);
        } else if (this.tile) {
            this.element.insertAdjacentElement('beforebegin', el);
        } else {
            this.list().hidden = false;
            this.list().appendChild(el);
        }

        return shape;
    }

    progress(shape, pct) {
        const bar = shape.el.querySelector('.upl-bar > i');
        if (bar) {
            bar.style.width = `${pct}%`;
        }
        const text = shape.el.querySelector('.pct');
        if (text) {
            text.textContent = `${pct}%`;
        }
        const fill = shape.el.querySelector('.fill');
        if (fill) {
            fill.style.height = `${pct}%`;
        }
    }

    /* Refused. The sentence is the server's, drawn on the row or in the tile
       that caused it, and nothing else on the page moves. */
    refuse(shape, file, sentence) {
        if (this.tile) {
            shape.el.className = 'upl-tile bad';
            shape.el.innerHTML =
                `<span class="lbl">${esc(sentence)}</span>` +
                '<button type="button" class="upl-act" data-upl-retry>Retry</button>';
        } else {
            shape.el.className = 'upl-file bad';
            shape.el.innerHTML =
                GLYPH.alert +
                `<span class="nm">${esc(file.name)}</span>` +
                `<span class="sz">${esc(size(file.size))}</span>` +
                `<span class="why">${esc(sentence)}</span>` +
                '<button type="button" class="upl-act" data-upl-retry>Retry</button>' +
                '<button type="button" class="upl-act x" data-upl-cancel aria-label="Take this file off the queue">&times;</button>';
        }
        shape.xhr = null;
        shape.file = file;
        shape.el.uplShape = shape;
        this.relead();
        this.dispatch('failed', { prefix: 'storage:upload', detail: { name: file.name, error: sentence } });
    }

    /* Kept. On a tile that means becoming an ordinary evidence tile — the same
       box as the ones already in the grid, with its removal always at least
       visible. In the zone it is a finished row carrying the chip the receiving
       module chose: "parsed" where something was read out of the file, "stored"
       where the bytes are the point. */
    keepIt(shape, receipt) {
        const label = receipt.label || (shape.file ? shape.file.name : '');
        if (this.tile) {
            shape.el.className = 'upl-tile done';
            shape.el.innerHTML =
                GLYPH.photo +
                `<span class="fn">${esc(label)}</span>` +
                `<button type="button" class="rm" data-upl-remove data-upl-key="${esc(receipt.key)}" ` +
                'aria-label="Remove this evidence">&times;</button>';
        } else {
            shape.el.className = 'upl-file done';
            shape.el.innerHTML =
                GLYPH.file +
                `<span class="nm">${esc(label)}</span>` +
                `<span class="sz">${esc(size(receipt.bytes || 0))}</span>` +
                `<span class="chip ok">${esc(receipt.kind)}</span>` +
                /* An empty .why is the vocabulary's one flexible cell, and what
                   pushes the removal to the row's right edge. */
                '<span class="why"></span>' +
                `<button type="button" class="upl-act x" data-upl-remove data-upl-key="${esc(receipt.key)}" ` +
                'aria-label="Remove this file">&times;</button>';
        }
        shape.xhr = null;
        shape.el.uplShape = shape;
        this.hidden(receipt.key);
        this.relead();
        this.dispatch('done', { prefix: 'storage:upload', detail: receipt });
    }

    /* ── removal, asked inline ──────────────────────────────────────────── */

    /* The question goes in the tile's own box: no browser dialog, nothing
       covering the grid, and the answer is two words at the house control
       height. In the zone the row simply asks in place. */
    ask(button) {
        const target = button.closest('.upl-tile, .upl-file, [data-upl-key]') || button;
        const key = button.getAttribute('data-upl-key') || target.getAttribute('data-upl-key') || '';
        const name = (target.querySelector('.fn, .nm, i') || {}).textContent || 'this file';

        const asking = document.createElement(this.tile ? 'span' : 'div');
        asking.className = this.tile ? 'upl-tile confirm' : 'upl-file bad';
        asking.innerHTML = this.tile
            ? `<span class="lbl">Remove ${esc(name)}?</span><span class="row">` +
              '<button type="button" class="upl-act" data-upl-keep>Keep</button>' +
              `<button type="button" class="upl-act dg" data-upl-confirm data-upl-key="${esc(key)}">Remove</button></span>`
            : `<span class="why">Remove ${esc(name)}?</span>` +
              '<button type="button" class="upl-act" data-upl-keep>Keep</button>' +
              `<button type="button" class="upl-act dg" data-upl-confirm data-upl-key="${esc(key)}">Remove</button>`;

        asking.uplRestore = target;
        target.replaceWith(asking);
    }

    keep(button) {
        const asking = button.closest('.upl-tile, .upl-file');
        if (asking && asking.uplRestore) {
            asking.replaceWith(asking.uplRestore);
        }
    }

    remove(button) {
        const asking = button.closest('.upl-tile, .upl-file');
        const key = button.getAttribute('data-upl-key') || '';

        fetch(this.attr('remove-url').replace('__key__', key), {
            method: 'DELETE',
            headers: { 'X-CSRF-Token': this.attr('token') },
        })
            .then((response) => response.json().then((body) => ({ ok: response.ok, body })))
            .then(({ ok, body }) => {
                if (!ok) {
                    /* The record refused after all. Put the tile back and say
                       why, in the record's own words. */
                    const back = asking && asking.uplRestore ? asking.uplRestore : null;
                    if (back) {
                        asking.replaceWith(back);
                    }
                    this.dispatch('failed', { prefix: 'storage:upload', detail: { key, error: body.error } });

                    return;
                }
                if (asking) {
                    asking.remove();
                }
                this.unhide(key);
                this.dispatch('removed', { prefix: 'storage:upload', detail: { key } });
            })
            .catch(() => {
                const back = asking && asking.uplRestore ? asking.uplRestore : null;
                if (back) {
                    asking.replaceWith(back);
                }
            });
    }

    /* ── the classic-form door ──────────────────────────────────────────── */

    /* A page that posts an ordinary form rather than listening for the event
       gets the key in a hidden input, named by the module in one attribute. */
    hidden(key) {
        const name = this.attr('input-name');
        if (!name || !key) {
            return;
        }
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = key;
        input.setAttribute('data-upl-key', key);
        this.element.insertAdjacentElement('afterend', input);
    }

    unhide(key) {
        const scope = this.element.parentElement || document;
        scope.querySelectorAll(`input[type="hidden"][data-upl-key="${key}"]`).forEach((input) => input.remove());
    }

    /* ── the zone's one line ────────────────────────────────────────────── */

    relead() {
        if (this.tile) {
            return;
        }
        const rows = Array.prototype.slice.call(this.list().children);
        const busy = rows.filter((row) => !row.classList.contains('bad') && !row.classList.contains('done')).length;
        const bad = rows.filter((row) => row.classList.contains('bad')).length;

        this.list().hidden = 0 === rows.length;

        if (busy > 0) {
            this.lead(`Uploading ${busy} file${1 === busy ? '' : 's'} — <a href="#" data-upl-browse>add more</a>`);

            return;
        }
        if (bad > 0) {
            this.lead(`${bad} file${1 === bad ? '' : 's'} could not be stored`);

            return;
        }
        if (rows.length > 0) {
            this.lead('Stored — <a href="#" data-upl-browse>upload another</a>');

            return;
        }
        this.element.innerHTML = this.idle;
    }

    lead(html) {
        const lead = this.element.querySelector('[data-upl-lead]');
        if (lead) {
            lead.innerHTML = html;
        }
    }

    label(text) {
        const label = this.element.querySelector('.lbl');
        if (label) {
            label.textContent = text;
        }
    }

    list() {
        return this.element.querySelector('[data-upl-list]');
    }

    shapeOf(node) {
        const el = node.closest('.upl-tile, .upl-file');

        return el ? el.uplShape : null;
    }

    attr(name) {
        return this.element.getAttribute(`data-upl-${name}`) || '';
    }
}
