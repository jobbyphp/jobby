<?php

namespace Jobby;

use Cron\CronExpression;
use DateTimeImmutable;

class ScheduleChecker
{
    /**
     * @var DateTimeImmutable|null
     */
    private $now;

    public function __construct(DateTimeImmutable $now = null)
    {
        $this->now = $now instanceof DateTimeImmutable ? $now : new DateTimeImmutable("now");
    }

    /**
     * @param string|callable $schedule
     * @return bool
     */
    public function isDue($schedule)
    {
        if (is_callable($schedule)) {
            return call_user_func($schedule, $this->now);
        }

        $dateTime = \DateTime::createFromFormat('Y-m-d H:i:s', $schedule);
        if ($dateTime !== false) {
            return $dateTime->format('Y-m-d H:i') == $this->now->format('Y-m-d H:i');
        }

        return CronExpression::factory((string)$schedule)->isDue($this->now);
    }
}
