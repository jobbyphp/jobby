<?php

namespace Jobby;

use Jobby\Helper;
use Cron\CronExpression;

/**
 *
 */
class BackgroundJob
{
    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var string
     */
    private $job;

    /**
     * @var string
     */
    private $tmpDir;

    /**
     * @var array
     */
    private $config;

    /**
     * @param string $job
     * @param array $config
     */
    public function __construct($job, array $config)
    {
        $this->job = $job;
        $this->config = $config;

        $this->helper = new Helper();
        $this->tmpDir = $this->helper->getTempDir();
    }

    /**
     *
     */
    public function run()
    {
        if (!$this->shouldRun()) {
            return;
        }

        if (!$this->aquireLock()) {
            return;
        }

        if ($this->isFunction()) {
            $retval = $this->runFunction();
        } else {
            $retval = $this->runFile();
        }

        $this->releaseLock();

        if ((bool) $retval) {
            $this->mail($retval);
        }
    }

    /**
     * @param int $retval
     */
    private function mail($retval)
    {
        if (empty($this->config['recipients'])) {
            return;
        }

        $this->helper->sendMail(
            $this->job,
            $this->config,
            $retval
        );
    }

    /**
     * @return string
     */
    private function getLogfile()
    {
        if ($this->config['output'] === null) {
            return "/dev/null";
        }

        $logfile = $this->config['output'];

        $logs = dirname($logfile);
        if (!file_exists($logs)) {
            mkdir($logs, 0777, true);
        }

        return $logfile;
    }

    /**
     * @return string
     */
    private function getLockFile()
    {
        $tmp = $this->helper->escape($this->tmpDir);
        $job = $this->helper->escape($this->job);

        if (!empty($this->config['environment'])) {
            $env = $this->config['environment'];
            return "$tmp/$env-$job.lck";
        } else {
            return "$tmp/$job.lck";
        }
    }

    /**
     * @return bool
     */
    private function aquireLock()
    {
        $lockfile = $this->getLockFile();

        if (file_exists($lockfile)) {
            $this->log("Lock file found in $lockfile. Skipping.");
            return false;
        }

        touch($lockfile);
        return true;
    }

    /**
     *
     */
    private function releaseLock()
    {
        $lockfile = $this->getLockFile();
        unlink($lockfile);
    }

    /**
     * @return bool
     */
    private function shouldRun()
    {
        if (!$this->config['enabled']) {
            return false;
        }

        $cron = CronExpression::factory($this->config['schedule']);
        if (!$cron->isDue()) {
            return false;
        }

        $host = $this->helper->getHost();
        if (strcasecmp($this->config['runOnHost'], $host) != 0) {
            return false;
        }

        return true;
    }

    /**
     * @param string $message
     */
    private function log($message)
    {
        $now = date($this->config['dateFormat'], $_SERVER['REQUEST_TIME']);
        $logfile = $this->getLogfile();

        file_put_contents($logfile, "$now: $message\n", FILE_APPEND);
    }

    /**
     * @return bool
     */
    private function isFunction()
    {
        return preg_match('/^function\(.*\).*}$/', $this->config['command']);
    }

    /**
     * @return int
     */
    private function runFunction()
    {
        // If job is an anonymous function string, eval it to get the
        // closure, and run the closure.
        eval('$command = ' . $this->config['command'] . ';');

        ob_start();
        $retval = (bool) $command();
        file_put_contents($this->getLogfile(), ob_get_contents(), FILE_APPEND);
        ob_end_clean();

        return $retval;
    }

    /**
     * @return int
     */
    private function runFile()
    {
        // If job should run as another user, we must be on *nix and
        // must have sudo privileges.
        $hasRunAs = !empty($this->config["runAs"]);
        $isRoot = (posix_getuid() === 0);
        $isUnix = ($this->helper->getPlatform() === Helper::UNIX);

        if ($hasRunAs && $isUnix && $isRoot) {
            $useSudo = "sudo -u {$this->config['runAs']}";
        } else {
            $useSudo = "";
        }

        // Start execution. Run in foreground (will block).
        $command = $this->config['command'];
        $logfile = $this->getLogfile();
        exec("$useSudo $command 1>> $logfile 2>&1", $dummy, $retval);

        return $retval;
    }
}

// run this file, if executed directly
// @see: http://stackoverflow.com/questions/2413991/php-equivalent-of-pythons-name-main
if (!debug_backtrace()) {
    if (file_exists('vendor/autoload.php')) {
        require('vendor/autoload.php');
    } else {
        require(dirname(dirname(__DIR__)) . '/vendor/autoload.php');
    }

    spl_autoload_register(function ($class) {
        $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
        require(dirname(__DIR__) . "/{$class}.php");
    });

    parse_str($argv[2], $config);
    $job = new BackgroundJob($argv[1], $config);
    $job->run();
}
