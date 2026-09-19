import { Controller } from '@hotwired/stimulus';

/*
 * DEPRECATED, AND A NO-OP ON PURPOSE. This controller opened the Files hub's
 * filter panels by toggling an `.open` class, back when the shell shipped no
 * dropdown and this module carried the whole `.i-dd*` family in its own sheet.
 * The shell ships the grouped dropdown now, as a `<details>` the browser opens
 * by itself, so the filter row wires nothing and there is nothing left to do
 * here — see templates/files/_filters.html.twig.
 *
 * IT IS STILL SHIPPED, AND STILL LISTED IN assets/package.json, because an
 * installation's assets/controllers.json names it: deleting the file outright
 * leaves that name pointing at nothing and every page of the installation 500s
 * on the next asset compile. The stub goes in THIS release, the file and its
 * controllers.json entry in the NEXT one, with the recipe update beside it.
 */
export default class extends Controller {
}
