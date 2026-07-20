<?php

declare(strict_types=1);

use SwooleBundle\SwooleBundle\Bridge\Symfony\Dumper\SafeDumper;
use Symfony\Component\VarDumper\Caster\ScalarStub;

// sdump as coroutine safe dump, best used with dump server like buggregator
function sdump(mixed ...$vars): mixed
{
    if (!$vars) {
        SafeDumper::dump(new ScalarStub('🐛'));

        return null;
    }

    if (array_key_exists(0, $vars) && count($vars) === 1) {
        SafeDumper::dump($vars[0]);
        $k = 0;
    } else {
        foreach ($vars as $k => $v) {
            SafeDumper::dump($v, (string) (is_int($k) ? 1 + $k : $k));
        }
    }

    if (count($vars) > 1) {
        return $vars;
    }

    return $vars[$k];
}
