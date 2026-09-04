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

namespace Uhifadhi\Storage\Shell;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Uhifadhi\Shell\Contract\NavigationSourceInterface;
use Uhifadhi\Shell\Model\NavItem;
use Uhifadhi\Shell\Model\NavSection;

/**
 * THE ONE ROW THIS MODULE PUTS IN THE SIDEBAR.
 *
 * Files is the platform-wide row the shell's nav seam is documented to expect
 * from a module: "the rare platform-wide row that belongs to nobody's area".
 * The hub is org-wide by construction — it is every module's files across every
 * area at once — so it is not an area tab and could not be one.
 *
 * IT REPLACES A HAND-EDIT. Before the fleet had a shell, putting this row in
 * the sidebar meant opening the application's own layout.html.twig and typing a
 * nav-item beside the others, then remembering a second edit in a Twig
 * extension so the row lit up on the right pages. Two edits, in somebody else's
 * repository, that no test could see. This is the same row, stated by the
 * module that owns the screen, and it leaves when the module does.
 *
 * SIGNED IN IS THE WHOLE GATE, and that is the hub's own rule rather than a
 * shortcut: every file is shown with its owner, every ORIGINAL is
 * permission-checked on its way out, and so the hub shows LESS to some people
 * rather than being closed to them. A row gated on an administrator permission
 * would hide a screen that is deliberately open. It is absent for a stranger,
 * because who owns what is the organisation's business.
 *
 * ROUTE-TOLERANT. The address is mounted by the APPLICATION (the recipe's
 * config/routes/storage.yaml, which an installation may edit or delete), and a
 * sidebar that took every page down because somebody unmounted a route would be
 * the worst possible way to learn it. No route, no row.
 *
 * BUILT PER CALL, NEVER CACHED, and nothing is done in the constructor: the
 * shell reads its sources live on every render precisely so a module switched
 * off this morning is gone from the sidebar this morning.
 */
final readonly class FilesNavigation implements NavigationSourceInterface
{
    /**
     * The heading the row files under.
     *
     * SYSTEM RATHER THAN OBSERVATORY, and the design left it open: the hub is
     * org-wide, it is not an area tab, and it administers at least as much as it
     * observes — you come here to check that thumbnails were made and to see
     * where the bytes went. Moving it is one constant if the ruling goes the
     * other way.
     */
    public const string SECTION = 'System';

    /**
     * Below an installation's own Observatory rows and below Organization.
     * A declared position rather than a hope about container compilation order,
     * which is what the field is for.
     */
    public const int POSITION = 30;

    /** The hub: this module's front door, and the row's destination. */
    public const string ROUTE = 'storage_files';

    public function __construct(
        private UrlGeneratorInterface $urls,
        private TokenStorageInterface $tokens,
        private RequestStack $requests,
    ) {
    }

    public function sections(): iterable
    {
        /*
         * NO TOKEN, NO ROW. A page can render outside any firewall — an error
         * page, a console-rendered template — and the hub is not open to a
         * stranger, so there is nothing to offer either way.
         */
        if (null === $this->tokens->getToken()?->getUser()) {
            return;
        }

        try {
            $url = $this->urls->generate(self::ROUTE);
        } catch (RouteNotFoundException) {
            return;
        }

        yield new NavSection(self::SECTION, [
            new NavItem(
                label: 'Files',
                url: $url,
                icon: 'lucide:image',
                current: $this->viewerIsHere($url),
            ),
        ], position: self::POSITION);
    }

    /**
     * WHETHER THE VIEWER IS ON THIS ROW'S SCREEN, or on one underneath it — the
     * widget library, a file's own page and "where files go" all light the Files
     * row, because they are one place in the product.
     *
     * Compared as PATHS rather than route names, because the addresses belong to
     * the application: it may mount this module under a prefix, and a list of
     * route names typed out here would go stale the first time a screen was
     * added. The generated url carries the base url when the installation lives
     * in a subdirectory, so the request's is put back on before comparing.
     */
    private function viewerIsHere(string $url): bool
    {
        $request = $this->requests->getCurrentRequest();
        if (null === $request) {
            return false;
        }

        $here = $request->getBaseUrl().$request->getPathInfo();

        return $here === $url || str_starts_with($here, rtrim($url, '/').'/');
    }
}
