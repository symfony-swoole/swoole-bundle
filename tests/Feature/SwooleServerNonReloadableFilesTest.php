<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use SwooleBundle\SwooleBundle\Client\HttpClient;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

final class SwooleServerNonReloadableFilesTest extends ServerTestCase
{
    protected function setUp(): void
    {
        $this->markTestSkippedIfXdebugEnabled();
        $this->deleteVarDirectory();
    }

    public function testDumpNonReloadableFilesWithAutoRegistration(): void
    {
        $env = 'non_reloadable_files';
        $envs = ['APP_ENV' => $env];
        $serverStart = $this->createConsoleProcess([
            'swoole:server:start',
            '--host=localhost',
            '--port=9999',
        ], $envs);

        $serverStart->setTimeout(3);
        $serverStart->disableOutput();
        $serverStart->run();

        $this->assertProcessSucceeded($serverStart);

        $this->runAsCoroutineAndWait(function () use ($envs): void {
            $this->deferServerStop([], $envs);

            $client = HttpClient::fromDomain('localhost', 9999, false);
            $this->assertTrue($client->connect());
            $this->assertHelloWorldRequestSucceeded($client);
        });

        $nonReloadableFiles = $this->getVarDirectoryPath().'/cache/'.$env.'/swoole_bundle/nonReloadableFiles.txt';
        $nonReloadableAppFiles = $this->getVarDirectoryPath().'/cache/'.$env.'/swoole_bundle/nonReloadableAppFiles.txt';
        $this->assertFileExists($nonReloadableFiles);
        $this->assertFileExists($nonReloadableAppFiles);
        $this->assertNotEmpty(\file_get_contents($nonReloadableFiles));
        $this->assertNotEmpty(\file_get_contents($nonReloadableAppFiles));
    }
}
