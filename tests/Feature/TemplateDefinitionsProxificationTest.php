<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Feature;

use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Test\ServerTestCase;

/**
 * Templates - definitions a bundle registers once for its per-firewall services to be copied from - next to
 * stateful services. One holds an abstract_arg(), the other is marked abstract, and both are reachable by the
 * stateful services compiler pass through their tags.
 *
 * Neither can be instantiated, and Symfony removes them for being unused - unless a pool is built around them.
 * For the one with the abstract_arg() that is the scheb/2fa-bundle failure: the container fails to dump with
 * "Argument 1 of service ... is abstract", which is why the command below has to succeed at all, before
 * anything it prints is looked at. The abstract one dumps, but ends up with a pool of its own, for a service
 * nothing can ever ask for - which is what the listing is checked for.
 */
final class TemplateDefinitionsProxificationTest extends ServerTestCase
{
    public function testTemplatesAreNotPooledAndTheServicesBuiltFromThemAre(): void
    {
        $command = $this->createConsoleProcess([
            'swoole:debug:service-pools',
            '--no-ansi',
            '--env=coroutines_template_definitions',
            '--filter=test.template',
        ]);
        $command->setTimeout(self::coverageEnabled() ? 30 : 15);
        $command->run();

        $this->assertProcessSucceeded($command);
        $output = $command->getOutput();

        self::assertStringContainsString(' * test.template.abstract_argument.main' . PHP_EOL, $output);
        self::assertStringContainsString(' * test.template.abstract_definition.main' . PHP_EOL, $output);
        self::assertStringNotContainsString(' * test.template.abstract_argument' . PHP_EOL, $output);
        self::assertStringNotContainsString(' * test.template.abstract_definition' . PHP_EOL, $output);
    }
}
