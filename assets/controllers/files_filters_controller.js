import { Controller } from '@hotwired/stimulus';

/*
 * THE FILTER ROW'S DROPDOWNS. Module, area, day and where-the-bytes-are are each
 * a .i-dd holding a trigger (.i-ddt) and a floating panel (.i-ddmenu); the panel
 * is hidden by CSS until its .i-dd carries `.open`. This controller does only the
 * chrome — opening one panel at a time and closing on an outside click or Escape.
 *
 * Every option inside a panel is an ordinary LINK driving the one FileFilter
 * query, so once a panel is open, filtering is a normal server-side navigation:
 * the controller never touches the query, the grid or the count. Opening the
 * panel is all it does. The pills, the shape toggle and direct
 * ?module/?area/?kind/?day/?backend/?thumb/?q/?view URLs all work with scripting
 * off; the dropdown panels are the one part that needs it.
 *
 * It is this module's own rather than the incidents module's identical one
 * because a module may not depend on another module, and the shell ships no
 * dropdown controller to inherit. If a third surface writes a fourth copy, the
 * three of them are the case for hoisting one into the shell.
 */
export default class extends Controller {
    connect() {
        this.onDocumentClick = (event) => {
            if (!this.element.contains(event.target)) {
                this.closeAll();
            }
        };
        this.onKeydown = (event) => {
            if ('Escape' === event.key) {
                this.closeAll();
            }
        };
        document.addEventListener('click', this.onDocumentClick);
        document.addEventListener('keydown', this.onKeydown);
    }

    disconnect() {
        document.removeEventListener('click', this.onDocumentClick);
        document.removeEventListener('keydown', this.onKeydown);
    }

    /** Open the clicked dropdown, closing any other — one panel at a time. */
    toggle(event) {
        event.preventDefault();
        const dropdown = event.currentTarget.closest('[data-dd]');
        if (!dropdown) {
            return;
        }
        const wasOpen = dropdown.classList.contains('open');
        this.closeAll();
        if (!wasOpen) {
            dropdown.classList.add('open');
            event.currentTarget.setAttribute('aria-expanded', 'true');
        }
    }

    closeAll() {
        this.element.querySelectorAll('[data-dd].open').forEach((dropdown) => {
            dropdown.classList.remove('open');
            const trigger = dropdown.querySelector('[data-dd-trigger]');
            if (trigger) {
                trigger.setAttribute('aria-expanded', 'false');
            }
        });
    }
}
