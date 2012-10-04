<?php
 
namespace Jobby;

class CronParser
{
    private $_crontab;
    private $_now;

    private static $_weekdays = array(
                'sun' => 0,
                'mon' => 1,
                'tue' => 2,
                'wed' => 3,
                'thu' => 4,
                'fri' => 5,
                'sat' => 6,
                'sun' => 7
            );

    private static $_months = array(
                'jan' => 1,
                'feb' => 2,
                'mar' => 3,
                'apr' => 4,
                'may' => 5,
                'jun' => 6,
                'jul' => 7,
                'aug' => 8,
                'sep' => 9,
                'oct' => 10,
                'nov' => 11,
                'dec' => 12
            );

    public function __construct($crontab)
    {
        $this->_crontab = $crontab;
        // floor to nearest minute
        $tmp = date('Y-m-d H:i:00', $_SERVER['REQUEST_TIME']);
        $this->_now = strtotime($tmp);
    }

    // for testing
    public function setDateTime($date)
    {
        // floor to nearest minute
        $tmp = date('Y-m-d H:i:00', strtotime($date));
        $this->_now = strtotime($tmp);
    }

    public function shouldRun()
    {
        if ($this->_validate($this->_now))
        {
            return true;
        }

        return false;
    }

    private function _validate($ts)
    {
        // remove any leading zeros so that result can be used as array index
        $year = (int) date('Y', $ts);
        $month = (int) date('m', $ts);
        $day = (int) date('d', $ts);
        $hour = (int) date('H', $ts);
        $minute = (int) date('i', $ts);

        list($minutes, $hours, $days, $months, $weekdays)
            = preg_split('/\s+/', $this->_crontab);

        $months = $this->_expand(1, 12, $months);
        $days = $this->_expandDays($year, $month, $days, $weekdays);
        $hours = $this->_expand(0, 23, $hours);
        $minutes = $this->_expand(0, 59, $minutes);

        $months = array_flip($months);
        $days = array_flip($days);
        $hours = array_flip($hours);
        $minutes = array_flip($minutes);

        if (isset($months[$month])
                && isset($days[$day])
                && isset($hours[$hour])
                && isset($minutes[$minute]))
        {
            return true;
        }

        return false;
    }

    private function _expandDays($year, $month, $days, $weekdays)
    {
        // if $days and $weekdays are both not restricted, or if $days is
        // restricted but not $weekdays, get days of entire month
        if (($days == '*' && $weekdays == '*')
                || ($days != '*' && $weekdays == '*'))
        {
            $days = $this->_expand(1, 31, $days);
        }

        // if $weekdays is restricted but not $days, get weekdays (as days
        // of month)
        else if ($days == '*' && $weekdays != '*')
        {
            $weekdays = $this->_expand(0, 7, $weekdays);
            $days = $this->_weekDaysToDays($year, $month, $weekdays);
        }

        // if both $days and $weekdays are restricted, get union of patterns
        else if ($days != '*' && $weekdays != '*')
        {
            $days = $this->_expand(1, 31, $days);
            $weekdays = $this->_expand(0, 7, $weekdays);
            $days = array_merge($days,
                        $this->_weekDaysToDays($year, $month, $weekdays));
        }

        return $days;
    }

    private function _weekDaysToDays($year, $month, $weekdays)
    {
        $len = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $arr = array();

        foreach ($weekdays as $dayToFind)
        {
            for ($i = 1; $i <= $len; $i++)
            {
                $day = date('N', strtotime("$year-$month-$i"));

                // if this day is a day of the week we've got, add it to return
                // array. note that crontab says Sunday can be either 0 or 7
                if (($day == $dayToFind) || ($day == 7 && $dayToFind == 0))
                {
                    $arr[] = $i;
                }
            }
        }

        return $arr;
    }

    private function _expand($min, $max, $expr)
    {
        // see crontab man page (Fedora) for crontab rules

        $arr = array();
        $expr = strtolower($expr);

        if ($expr == '*')
        {
            for ($i = $min; $i <= $max; $i++)
            {
                $arr[] = $i;
            }
        }
        else if (strpos($expr, '/') !== false)
        {
            list($range, $step) = explode('/', $expr);
            $steps = $this->_expand($min, $max, $range);
            foreach ($steps as $i => $value)
            {
                if ($i % $step == 0)
                {
                    $arr[] = $value;
                }
            }
        }
        else if (strpos($expr, '-') !== false)
        {
            list($aMin, $aMax) = explode('-', $expr);
            if ($aMin >= $min || $aMax <= $max)
            {
                for ($i = $aMin; $i <= $aMax; $i++)
                {
                    $arr[] = $i;
                }
            }
        }
        else if (strpos($expr, ',') !== false)
        {
            $exprs = explode(',', $expr);
            foreach ($exprs as $expr)
            {
                $arr = array_merge($arr, $this->_expand($min, $max, $expr));
            }
        }
        else if (isset(self::$_weekdays[$expr]))
        {
            $arr[] = self::$_weekdays[$expr];
        }
        else if (isset(self::$_months[$expr]))
        {
            $arr[] = self::$_months[$expr];
        }
        else if (ctype_digit($expr) && ($expr >= $min || $expr <= $max))
        {
            $arr[] = $expr;
        }
        else
        {
            throw new \Exception("error parsing '$expr'.");
        }

        return $arr;
    }
}

