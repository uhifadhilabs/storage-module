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

namespace UhifadhiLabs\Storage\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * The shared half of every Files screen test: a client, and somebody to be.
 *
 * Two people, because the hub has exactly two audiences. A RANGER is anyone
 * signed in — the hub is open to them and shows them what their permissions
 * allow. A WARDEN carries the deployment's administrator permission, which is
 * the only thing "Where files go" asks for.
 */
abstract class FilesTestCase extends WebTestCase
{
    protected function ranger(KernelBrowser $client): KernelBrowser
    {
        $client->loginUser(new InMemoryUser('ranger@example.test', 'x', ['ROLE_USER']));

        return $client;
    }

    protected function warden(KernelBrowser $client): KernelBrowser
    {
        $client->loginUser(new InMemoryUser('warden@example.test', 'x', ['ROLE_ADMIN', 'ROLE_USER']));

        return $client;
    }
}
