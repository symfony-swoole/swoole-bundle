<?php

if (extension_loaded('blackfire')) {
    return;
}

$blackfireStarted = false;
$blackfireStopped = false;

\ZEngine\Core::init();

$reflClass = new \ZEngine\Reflection\ReflectionClass('BlackfireProbe');
$reflClass->addMethod('setAttribute', static function ($key, $value) {});
/* @phpstan-ignore-next-line */
$reflClass->getMethod('startTransaction')->redefine(static function ($transactionName = null) {
    global $blackfireStarted;
    $blackfireStarted = true;
});
/* @phpstan-ignore-next-line */
$reflClass->getMethod('stopTransaction')->redefine(static function () {
    global $blackfireStopped;

    $blackfireStopped = true;
});
$reflClass->addMethod('wasStarted', static function () {
    global $blackfireStarted;

    return $blackfireStarted;
});
$reflClass->addMethod('wasStopped', static function () {
    global $blackfireStopped;

    return $blackfireStopped;
});
