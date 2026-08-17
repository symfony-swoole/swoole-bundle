<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Scheduler;

use SwooleBundle\SwooleBundle\Tests\Fixtures\Symfony\TestBundle\Message\CreateFileMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('scheduler_fixture')]
final class CreateFileSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())->add(
            RecurringMessage::every(1, new CreateFileMessage('scheduler_ran.txt', 'ran')),
        );
    }
}
