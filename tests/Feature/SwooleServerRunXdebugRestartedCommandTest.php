<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use SwooleBundle\SwooleBundle\Bridge\Symfony\Bundle\Command\AbstractServerStartCommand;
use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class SwooleServerRunXdebugRestartedCommandTest extends ServerTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->deleteVarDirectory();
    }

    public function testRunAndCall(): void
    {
        $kernel = static::createKernel();
        $application = new Application($kernel);

        /** @var AbstractServerStartCommand $command */
        $command = $application->find('swoole:server:start');
        $command->enableTestMode();

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--host' => 'localhost',
            '--port' => '9999',
        ]);

        self::assertSame(0, $commandTester->getStatusCode());
        if (\extension_loaded('xdebug')) {
            self::assertStringContainsString('Restarting command without Xdebug..', $commandTester->getDisplay());
        }
    }
}
