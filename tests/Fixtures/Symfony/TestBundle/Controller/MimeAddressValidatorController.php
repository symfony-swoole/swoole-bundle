<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Controller;

use Egulias\EmailValidator\EmailValidator;
use ReflectionProperty;
use SwooleBundle\SwooleBundle\Bridge\Symfony\Container\Proxy\ContextualProxy;
use SwooleBundle\SwooleBundle\Coroutine\CoroutinePool;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Says what the worker answering this request validates its mail addresses through.
 *
 * Asked over HTTP rather than from a console command, because the console boots no server and the
 * bootable services are what install this. And asked of a worker rather than of the master, because the
 * whole arrangement rests on the workers inheriting the static across the fork - installing it in a
 * process nothing is served from would prove nothing.
 */
final class MimeAddressValidatorController
{
    #[Route(path: '/mime/address-validator', methods: ['GET'])]
    public function reportInstalledValidator(): Response
    {
        // Built first, so the report is of a static something has actually validated through rather
        // than of one nothing has touched yet.
        new Address('someone@example.com');

        $validator = (new ReflectionProperty(Address::class, 'validator'))->getValue();

        $facts = [
            'validator' => is_object($validator) ? $validator::class : 'none',
            'pooled' => $validator instanceof ContextualProxy ? 'yes' : 'no',
        ];

        if ($validator instanceof ContextualProxy) {
            $facts['distinct_instances'] = (string) self::instancesAcrossTwoCoroutines($validator);
        }

        $report = '';

        foreach ($facts as $name => $value) {
            $report .= sprintf('%s=%s%s', $name, $value, PHP_EOL);
        }

        return new Response($report, 200, ['Content-Type' => 'text/plain']);
    }

    /**
     * How many validators two coroutines are given between them - two if each has its own, one if they
     * are sharing, which is the fault.
     *
     * Asked through getContextualObject(), because every other way of looking sees the proxy: it is one
     * object whichever coroutine holds it, and what differs is the instance behind it.
     *
     * @param ContextualProxy<EmailValidator> $validator
     */
    private static function instancesAcrossTwoCoroutines(ContextualProxy $validator): int
    {
        $identify = static fn(): int => spl_object_id($validator->getContextualObject());

        return count(array_unique(CoroutinePool::fromCoroutines($identify, $identify)->run()));
    }
}
