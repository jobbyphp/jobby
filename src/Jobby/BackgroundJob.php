<?php
namespace Jobby;

use Jobby\Helper;

/**
 *
 */
class BackgroundJob
{
    /**
     * @var Helper
     */
    private $_helper;

    /**
     * @var string
     */
    private $_job;

    /**
     * @var string
     */
    private $_tmpDir;

    /**
     * @var array
     */
    private $_config;

    /**
     * @param string $job
     * @param array $config
     */
    public function __construct($job, array $config)
    {
        $this->_job = $job;
        $this->_config = $config;
        $this->_tmpDir = $this->_getHelper()->getTempDir();
    }

    /**
     *
     */
    public function run()
    {
        if (!$this->_shouldRun()) {
            return;
        }

        if (!$this->_aquireLock()) {
            return;
        }

        if ($this->_isFunction()) {
            $retval = $this->_runFunction();
        } else {
            $retval = $this->_runFile();
        }

        $this->_releaseLock();

        if ((bool) $retval) {
            $this->_mail($retval);
        }
    }

    /**
     * @return Helper
     */
    private function _getHelper()
    {
        if ($this->_helper === null) {
            $this->_helper = new Helper();
        }

        return $this->_helper;
    }

    /**
     * @param int $retval
     */
    private function _mail($retval)
    {
        if (empty($this->_config['recipients'])) {
            return;
        }

        $this->_getHelper()->sendMail(
            $this->_job,
            $this->_config,
            $retval
        );
    }

    /**
     * @return string
     */
    private function _getLogfile()
    {
        if ($this->_config['output'] === null) {
            return "/dev/null";
        }

        $logfile = $this->_config['output'];

        $logs = dirname($logfile);
        if (!file_exists($logs)) {
            mkdir($logs, 0777, true);
        }

        return $logfile;
    }

    /**
     * @return string
     */
    private function _getLockFile()
    {
        $tmp = $this->_tmpDir;
        $job = $this->_job;

        if (!empty($this->_config['environment'])) {
            $env = $this->_config['environment'];
            return "$tmp/$env-$job.lck";
        } else {
            return "$tmp/$job.lck";
        }
    }

    /**
     * @return bool
     */
    private function _aquireLock()
    {
        $lockfile = $this->_getLockFile();

        if (file_exists($lockfile)) {
            $this->_log("Lock file found in $lockfile. Skipping.");
            return false;
        }

        touch($lockfile);
        return true;
    }

    /**
     *
     */
    private function _releaseLock()
    {
        $lockfile = $this->_getLockFile();
        unlink($lockfile);
    }

    /**
     * @return bool
     */
    private function _shouldRun()
    {
        if (!$this->_config['enabled']) {
            return false;
        }

        $cron = \Cron\CronExpression::factory($this->_config['schedule']);
        if (!$cron->isDue()) {
            return false;
        }

        $host = $this->_getHelper()->getHost();
        if (strcasecmp($this->_config['runOnHost'], $host) != 0) {
            return false;
        }

        return true;
    }

    /**
     * @param string $message
     */
    private function _log($message)
    {
        $now = date($this->_config['dateFormat'], $_SERVER['REQUEST_TIME']);
        $logfile = $this->_getLogfile();

        file_put_contents($logfile, "$now: $message\n", FILE_APPEND);
    }

    /**
     * @return bool
     */
    private function _isFunction()
    {
        return preg_match('/^function\(.*\).*}$/', $this->_config['command']);
    }

    /**
     * @return int
     */
    private function _runFunction()
    {
        // If job is an anonymous function string, eval it to get the
        // closure, and run the closure.
        eval('$command = ' . $this->_config['command'] . ';');

        ob_start();
        $retval = (bool) $command();
        file_put_contents($this->_getLogfile(), ob_get_contents(), FILE_APPEND);
        ob_end_clean();

        return $retval;
    }

    /**
     * @return int
     */
    private function _runFile()
    {
        // If job should run as another user, we must be on *nix and
        // must have sudo privileges.
        $hasRunAs = !empty($this->_config["runAs"]);
        $isRoot = (posix_getuid() === 0);
        $isUnix = ($this->_getHelper()->getPlatform() === Helper::UNIX);

        if ($hasRunAs && $isUnix && $isRoot) {
            $useSudo = "sudo -u {$this->_config['runAs']}";
        } else {
            $useSudo = "";
        }

        // Start execution. Run in foreground (will block).
        $command = $this->_config['command'];
        $logfile = $this->_getLogfile();
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
