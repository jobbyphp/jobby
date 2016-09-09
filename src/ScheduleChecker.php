<?php

namespace Jobby;

use Cron\CronExpression;

class ScheduleChecker
{
    /**
     * @param string $schedule
     * @return bool
     */
    public function isDue($schedule)
    {
        $dateTime = \DateTime::createFromFormat('Y-m-d H:i:s', $schedule);
        if ($dateTime !== false) {
            return $dateTime->format('Y-m-d H:i') == (date('Y-m-d H:i'));
        }

        return CronExpression::factory((string)$schedule)->isDue();
    }
}