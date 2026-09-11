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

namespace Uhifadhi\Storage\Tests\Unit\Template;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Uhifadhi\Storage\Controller\UploadController;
use Uhifadhi\Storage\Upload\UploadDom;

/**
 * THE SEAM: the same literal string in the PHP, the template and the asset.
 *
 * A FULLY GREEN FUNCTIONAL SUITE CAN SIT ON TOP OF A COMPLETELY BROKEN PAGE.
 * That is not a worry, it is a bug this fleet has already shipped: a controller
 * validated a CSRF header the template rendered and the JS never sent, and every
 * server-side test passed because each one built its own request headers. Only a
 * browser ever hit the 403.
 *
 * Nothing that talks HTTP can catch that, so this test reads the three files AS
 * TEXT and asserts the names on both sides of the seam are the same string. If a
 * name has to change, it changes in three places at once — which is the point.
 *
 * IT ALSO PINS THE DESIGN'S OWN WORDS, for the same reason. Every state after
 * "idle" is drawn in the browser, so the design's leads, its classes and its
 * refusal shapes exist only inside the asset; a rendered-page test cannot reach
 * them and a code review is not a build step.
 */
#[CoversNothing]
final class UploadSeamTest extends TestCase
{
    private static function js(): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 3).'/assets/controllers/upload_controller.js');
    }

    private static function twig(): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 3).'/templates/upload/_component.html.twig');
    }

    /** Declaring a header is not sending one: assert the CALL SITE. */
    public function testTheAssetSendsTheHeaderTheControllerReads(): void
    {
        self::assertSame('X-CSRF-Token', UploadDom::CSRF_HEADER);
        self::assertStringContainsString(
            "xhr.setRequestHeader('X-CSRF-Token'",
            self::js(),
            'the upload must send the header UploadController reads',
        );
        self::assertStringContainsString(
            "'X-CSRF-Token': this.attr('token')",
            self::js(),
            'and so must the removal, which has no body to put a field in',
        );
        self::assertStringContainsString(
            'UploadDom::CSRF_HEADER',
            (string) file_get_contents(\dirname(__DIR__, 3).'/src/Controller/UploadController.php'),
            'and the controller must read it from the constant rather than a typed twin',
        );
    }

    /** The conventional field as well, so an endpoint reached without headers still carries a token. */
    public function testTheUploadAlsoCarriesTheConventionalTokenField(): void
    {
        self::assertStringContainsString("body.append('_token'", self::js());
    }

    /**
     * Every attribute the template writes and the asset reads. A hook spelled on
     * one side only is a control that draws perfectly and does nothing.
     *
     * @return iterable<string, array{string}>
     */
    public static function wiringAttributes(): iterable
    {
        foreach ([
            UploadDom::TARGET,
            UploadDom::TOKEN,
            UploadDom::UPLOAD_URL,
            UploadDom::REMOVE_URL,
            UploadDom::ACCEPT,
            UploadDom::MAX_FILES,
            UploadDom::PRESENTATION,
            UploadDom::INPUT_NAME,
        ] as $attribute) {
            yield $attribute => [$attribute];
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('wiringAttributes')]
    public function testTheTemplateWritesEveryAttributeTheAssetReads(string $attribute): void
    {
        self::assertStringContainsString($attribute, self::twig());
        // The asset reads them through attr('<name>'), which drops the shared
        // prefix — so the tail is what must appear on that side.
        self::assertStringContainsString(
            "attr('".substr($attribute, \strlen('data-upl-'))."')",
            self::js(),
            $attribute.' is written by the template and never read',
        );
    }

    /**
     * The parts the template renders and the asset addresses.
     *
     * @return iterable<string, array{string}>
     */
    public static function partHooks(): iterable
    {
        foreach ([UploadDom::ROOT, UploadDom::LEAD, UploadDom::LIST, UploadDom::BROWSE] as $hook) {
            yield $hook => [$hook];
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('partHooks')]
    public function testTheTemplateAndTheAssetNameTheSameParts(string $hook): void
    {
        self::assertStringContainsString($hook, self::twig());
        self::assertStringContainsString($hook, self::js());
    }

    /**
     * The controls the asset draws and then listens for. Neither side of these is
     * in the template — both ends are in the asset — so what this asserts is that
     * every control it DRAWS is one it also HANDLES.
     *
     * @return iterable<string, array{string}>
     */
    public static function controlHooks(): iterable
    {
        foreach ([
            UploadDom::CANCEL,
            UploadDom::RETRY,
            UploadDom::REMOVE,
            UploadDom::CONFIRM,
            UploadDom::KEEP,
        ] as $hook) {
            yield $hook => [$hook];
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('controlHooks')]
    public function testEveryControlTheAssetDrawsIsOneItHandles(string $hook): void
    {
        $js = self::js();

        self::assertStringContainsString($hook, $js);
        self::assertStringContainsString("closest('[".$hook."]')", $js, $hook.' is drawn and never listened for');
    }

    /**
     * The key is not a control but a fact carried on one — written onto the
     * finished row's remove and read back when the question is answered.
     */
    public function testTheKeyIsWrittenOntoARemovalAndReadBackOffIt(): void
    {
        $js = self::js();

        self::assertStringContainsString(UploadDom::KEY.'="${esc(receipt.key)}"', $js);
        self::assertStringContainsString("getAttribute('".UploadDom::KEY."')", $js);
    }

    public function testTheRemovalUrlIsPatchedAtThePlaceholderTheRouterLeft(): void
    {
        self::assertSame('__key__', UploadDom::KEY_PLACEHOLDER);
        self::assertStringContainsString(".replace('__key__', key)", self::js());
    }

    /**
     * The events a page listens for instead of writing JavaScript of its own.
     * Stimulus prefixes a dispatch with the controller identifier unless told
     * otherwise, so each one names its prefix explicitly — and the name in the
     * asset must be the name the constant publishes.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function events(): iterable
    {
        yield UploadDom::EVENT_INIT => [UploadDom::EVENT_INIT, 'init'];
        yield UploadDom::EVENT_CONNECT => [UploadDom::EVENT_CONNECT, 'connect'];
        yield UploadDom::EVENT_DONE => [UploadDom::EVENT_DONE, 'done'];
        yield UploadDom::EVENT_FAILED => [UploadDom::EVENT_FAILED, 'failed'];
        yield UploadDom::EVENT_REMOVED => [UploadDom::EVENT_REMOVED, 'removed'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('events')]
    public function testEveryPublishedEventIsActuallyDispatched(string $event, string $name): void
    {
        self::assertSame('storage:upload:'.$name, $event);
        self::assertStringContainsString(
            "this.dispatch('".$name."', { prefix: 'storage:upload'",
            self::js(),
            $event.' is published and never dispatched',
        );
    }

    /**
     * THE DESIGN'S VOCABULARY, AND ONLY IT. Every class the asset writes is one
     * the shell ships; a class invented here would render as browser defaults in
     * an installation while looking perfect in a standalone sheet.
     *
     * @return iterable<string, array{string}>
     */
    public static function drawnClasses(): iterable
    {
        foreach ([
            'upl-file', 'upl-file bad', 'upl-file done',
            'upl-tile busy', 'upl-tile bad', 'upl-tile done', 'upl-tile confirm',
            'upl-bar', 'upl-act', 'upl-act x', 'upl-act dg',
        ] as $class) {
            yield $class => [$class];
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('drawnClasses')]
    public function testTheAssetDrawsTheDesignsOwnStates(string $class): void
    {
        self::assertStringContainsString($class, self::js());
    }

    /** The dropzone's four leads, in the design's own words. */
    public function testTheZonesLeadSaysWhatTheDesignSaysAtEachMoment(): void
    {
        $js = self::js();

        self::assertStringContainsString('Release to upload —', $js);
        self::assertStringContainsString('Uploading ${busy} file', $js);
        self::assertStringContainsString('add more</a>', $js);
        self::assertStringContainsString('could not be stored', $js);
        self::assertStringContainsString('Stored — <a href="#" data-upl-browse>upload another</a>', $js);
    }

    /** The add tile's drag-over word, and the removal question asked in its own box. */
    public function testTheTileAsksItsQuestionInItsOwnBox(): void
    {
        $js = self::js();

        self::assertStringContainsString("this.label('Release to add')", $js);
        self::assertStringContainsString('Remove ${esc(name)}?', $js);
        self::assertStringContainsString('>Keep</button>', $js);
        self::assertStringNotContainsString('confirm(', $js.'', 'the question is asked in the tile, never by the browser');
        self::assertStringNotContainsString('alert(', $js);
    }

    /**
     * THE COMPONENT NEVER INVENTS AN ERROR MESSAGE. Every sentence it draws on a
     * row or in a tile is the endpoint's, except the queue cap — the one rule the
     * component owns, because the server sees one file per request and has no
     * opinion about a queue.
     */
    public function testTheOnlySentenceTheAssetWritesIsTheOneItOwns(): void
    {
        $js = self::js();

        self::assertStringContainsString('body_.error ||', $js, 'the drawn refusal is the endpoint’s own');
        self::assertStringContainsString('Only ${cap} files at a time. Nothing was written.', $js);

        // The refusals the SERVER writes must exist nowhere in the asset.
        foreach (['is not a kind this target takes', 'Larger than the', 'You may not attach'] as $serverSentence) {
            self::assertStringNotContainsString($serverSentence, $js, 'that sentence is the server’s to write');
        }
    }

    /** The endpoint the component posts to is the endpoint the controller mounts. */
    public function testTheRoutesAreNamedOnceAndUsedOnce(): void
    {
        $controller = (string) file_get_contents(\dirname(__DIR__, 3).'/src/Controller/UploadController.php');

        self::assertStringContainsString("'/files/upload'", $controller);
        self::assertStringContainsString("name: 'storage_upload'", $controller);
        self::assertStringContainsString("name: 'storage_upload_remove'", $controller);
        self::assertStringContainsString(
            "'storage_upload'",
            (string) file_get_contents(\dirname(__DIR__, 3).'/src/Twig/UploadRuntime.php'),
            'the component must be handed the route the controller mounts',
        );
    }

    /** The controller name the template carries is the one the package declares. */
    public function testTheControllerNameIsTheOneTheAssetPackageDeclares(): void
    {
        $package = (string) file_get_contents(\dirname(__DIR__, 3).'/assets/package.json');

        self::assertStringContainsString('"upload"', $package);
        self::assertStringContainsString('controllers/upload_controller.js', $package);
        self::assertStringContainsString('data-controller="uhifadhi--storage-module--upload"', self::twig());
    }

    public function testUploadControllerIsRegisteredWithTheEndpointItServes(): void
    {
        self::assertStringContainsString(
            'storage_upload',
            (string) file_get_contents(\dirname(__DIR__, 3).'/src/Controller/UploadController.php'),
        );
        self::assertNotEmpty(UploadController::class);
    }
}
