<?php

class CronParserTest extends PHPUnit_Framework_TestCase
{
    private function _help($crontab, $expected)
    {
        $cp = new \Jobby\CronParser($crontab);
        $cp->setDateTime($expected);
        $this->assertTrue($cp->shouldRun());
    }

    public function testAll()
    {
        // these 5 are from the crontab man page
        $this->_help('5 0 * * *', '2008-09-17 00:05:00');
        $this->_help('15 14 1 * *', '2008-09-01 14:15:00');
        $this->_help('0 22 * * 1-5', '2008-09-16 22:00:00');
        $this->_help('23 0-23/2 * * *', '2008-09-17 16:23:00');
        $this->_help('5 4 * * sun', '2008-09-14 04:05:00');

        $this->_help('*/2 * * * *', '2008-09-17 17:08:00');
        $this->_help('*/10 * * * *', '2008-09-17 17:00:00');
        $this->_help('0 15 6-8 * *', '2008-09-08 15:00:00');
        $this->_help('0 15 * * *', '2008-09-17 15:00:00');
        $this->_help('0 15 10 * sun', '2008-09-14 15:00:00');
        $this->_help('0 15 10 * tue', '2008-09-16 15:00:00');
        $this->_help('0 15 1,15 * *', '2008-09-15 15:00:00');
        $this->_help('0 15 10,20 * *', '2008-09-10 15:00:00');
        $this->_help('0 15 15 * *', '2008-09-15 15:00:00');
        $this->_help('0 15 15 */2 *', '2008-09-15 15:00:00');
        $this->_help('0 15 15 2-12/2 *', '2008-08-15 15:00:00');
        $this->_help('* * 15 * *', '2008-09-15 23:59:00');
        $this->_help('* * 15 8 *', '2008-08-15 23:59:00');
        $this->_help('* * 12,13,14,15 8 *', '2008-08-15 23:59:00');

        // there was a bug when the 31st day of the month was specified
        $this->_help('0 11 * * 4', '2012-05-31 11:00:00');
    }
}

