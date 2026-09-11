<?php

declare(strict_types=1);

/*
 * This file is part of the UhifadhiLabs Storage Module.
 *
 * (c) Ezekiel Mjema <https://github.com/eemjema>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Uhifadhi\Storage\Upload;

/**
 * EVERY NAME THAT CROSSES THE PHP↔JS BOUNDARY FOR THE UPLOAD COMPONENT.
 *
 * A class of constants rather than a convention, for the reason the core's
 * `WidgetDom` is one: a green functional suite once sat on top of a completely
 * broken page, because the controller read a CSRF header the shipped script
 * never sent. Nothing that speaks HTTP can catch that — each test builds its own
 * headers. What catches it is spelling each name ONCE here and asserting, as
 * text, that the template and the asset both carry it
 * ({@see \Uhifadhi\Storage\Tests\Unit\Template\UploadSeamTest}).
 *
 * The JS cannot import a PHP constant, so the asset holds literals; the seam
 * test is what makes the two the same string. If a name here has to change, it
 * changes in three places at once — which is the point.
 */
final class UploadDom
{
    /**
     * The header the component sends its token in, and the one the controller
     * reads first. The same spelling the core's widget library uses, because a
     * deployment putting a proxy in front should have one header name to allow,
     * not two.
     */
    public const string CSRF_HEADER = 'X-CSRF-Token';

    /* ── the component's own element, and everything it was told ──────────── */

    public const string ROOT = 'data-upl-root';
    public const string TOKEN = 'data-upl-token';
    public const string TARGET = 'data-upl-target';
    public const string UPLOAD_URL = 'data-upl-upload-url';

    /**
     * The removal URL with {@see KEY_PLACEHOLDER} standing in for the key. Built
     * server-side and patched in the browser, so the component never assembles a
     * platform route out of string parts.
     */
    public const string REMOVE_URL = 'data-upl-remove-url';

    /**
     * A key is a path and a router will not generate a route for a placeholder
     * containing slashes, so the stand-in is one plain segment.
     */
    public const string KEY_PLACEHOLDER = '__key__';

    /**
     * What the file picker filters on. A FILTER, not a guard: the component
     * refuses nothing on the strength of it, because every refusal in this
     * feature is a sentence written by the server.
     */
    public const string ACCEPT = 'data-upl-accept';

    /**
     * How many files may be queued at once — the ONE rule the component owns,
     * because it is about the queue and the queue is the component's. Every
     * other rule is the target's and is answered by the endpoint.
     */
    public const string MAX_FILES = 'data-upl-max-files';

    /** `zone` or `tile` — which of the design's two presentations this instance is. */
    public const string PRESENTATION = 'data-upl-presentation';

    /**
     * The name of the hidden input a finished upload writes its key into, for a
     * page that posts a classic form rather than listening for the event. Absent
     * where the page has no form.
     */
    public const string INPUT_NAME = 'data-upl-input-name';

    /* ── the parts of the dropzone card ───────────────────────────────────── */

    /** The "browse" word in the zone's lead, and the "add more" it becomes. */
    public const string BROWSE = 'data-upl-browse';

    /** The one line of the zone that says what is happening. */
    public const string LEAD = 'data-upl-lead';

    /** The queue. Hidden while empty — an empty bordered box is not a state the design has. */
    public const string LIST = 'data-upl-list';

    /* ── the controls ─────────────────────────────────────────────────────── */

    public const string CANCEL = 'data-upl-cancel';
    public const string RETRY = 'data-upl-retry';
    public const string REMOVE = 'data-upl-remove';
    public const string CONFIRM = 'data-upl-confirm';
    public const string KEEP = 'data-upl-keep';
    public const string KEY = 'data-upl-key';

    /* ── what a page may listen for, so it needs no JavaScript of its own ─── */

    /** Dispatched on the element before anything is wired, so a page may adjust it. */
    public const string EVENT_INIT = 'storage:upload:init';

    /** Dispatched once the component is live, carrying the component itself. */
    public const string EVENT_CONNECT = 'storage:upload:connect';

    /** One file stored — carries the whole receipt: key, label, href, kind, bytes, thumbnail. */
    public const string EVENT_DONE = 'storage:upload:done';

    /** One file refused — carries the sentence that was drawn and the file's name. */
    public const string EVENT_FAILED = 'storage:upload:failed';

    /** One file taken back off — carries its key. */
    public const string EVENT_REMOVED = 'storage:upload:removed';

    private function __construct()
    {
    }
}
