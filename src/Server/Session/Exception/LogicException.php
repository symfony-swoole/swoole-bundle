<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Server\Session\Exception;

use LogicException as PHPLogicException;

final class LogicException extends PHPLogicException implements SessionExceptionInterface
{
}
