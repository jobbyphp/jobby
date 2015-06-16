<?php

namespace Jobby;

use SuperClosure\SerializableClosure;
use Cron\CronExpression;

/**
 *
 */
class BackgroundJob
{
    /**
     * @var Helper
     */
    protected $helper;

    /**
     * @var string
     */
    protected $job;

    /**
     * @var string
     */
    protected $tmpDir;

    /**
     * @var array
     */
    protected $config;

    /**
     * @param string $job
     * @param array $config
     * @param Helper $helper
     */
    public function __construct($job, array $config, Helper $helper = null)
    {
        $this->job = $job;
        $this->config = $config;
        $this->helper = $helper;

        if ($this->helper === null) {
            $this->helper = new Helper();
        }
        $this->tmpDir = $this->helper->getTempDir();
    }

    /**
     *
     */
    public function run()
    {
        $lockfile = $this->getLockFile();

        try {
            $this->checkMaxRuntime($lockfile);
        } catch (Exception $e) {
            $this->log("ERROR: " . $e->getMessage());
            $this->mail($e->getMessage());
            return;
        }

        if (!$this->shouldRun()) {
            return;
        }

        $lockAcquired = false;
        try {
            $this->helper->acquireLock($lockfile);
            $lockAcquired = true;

            if ($this->isFunction()) {
                $this->runFunction();
            } else {
                $this->runFile();
            }
        } catch (InfoException $e) {
            $this->log("INFO: " . $e->getMessage());
        } catch (Exception $e) {
            $this->log("ERROR: " . $e->getMessage());
            $this->mail($e->getMessage());
        }

        if ($lockAcquired) {
            $this->helper->releaseLock($lockfile);

            // remove log file if empty
            $logfile = $this->getLogfile();
            if(is_file($logfile) && filesize($logfile)<=0) {
                unlink($logfile);
            }
        }
    }

    /**
     * @param string $lockfile
     * @throws Exception
     */
    protected function checkMaxRuntime($lockfile)
    {
        $maxRuntime = $this->config["maxRuntime"];
        if ($maxRuntime === null) {
            return;
        }

        if ($this->helper->getPlatform() === Helper::WINDOWS) {
            throw new Exception("'maxRuntime' is not supported on Windows");
        }

        $runtime = $this->helper->getLockLifetime($lockfile);
        if ($runtime < $maxRuntime) {
            return;
        }

        throw new Exception(
            "MaxRuntime of $maxRuntime secs exceeded! "
            . "Current runtime: $runtime secs"
        );
    }

    /**
     * @param string $message
     */
    protected function mail($message)
    {
        if (empty($this->config['recipients'])) {
            return;
        }

        $this->helper->sendMail(
            $this->job,
            $this->config,
            $message
        );
    }

    /**
     * @return string
     */
    protected function getLogfile()
    {
        if ($this->config['output'] === null) {
            return false;
        }

        $logfile = $this->config['output'];

        $logs = dirname($logfile);
        if (!file_exists($logs)) {
            mkdir($logs, 0755, true);
        }

        return $logfile;
    }

    /**
     * @return string
     */
    protected function getLockFile()
    {
        $tmp = $this->tmpDir;
        $job = $this->helper->escape($this->job);

        if (!empty($this->config['environment'])) {
            $env = $this->helper->escape($this->config['environment']);
            return "$tmp/$env-$job.lck";
        } else {
            return "$tmp/$job.lck";
        }
    }

    /**
     * @return bool
     */
    protected function shouldRun()
    {
        if (!$this->config['enabled']) {
            return false;
        }

        if ($this->config['haltDir'] !== null) {
            $flag_file =
                $this->config['haltDir'] . DIRECTORY_SEPARATOR . $this->job;
            if (file_exists($flag_file)) {
                return false;
            }
        }

        $schedule = \DateTime::createFromFormat('Y-m-d H:i:s', $this->config['schedule']);
        if($schedule!==false){
            return $schedule->format('Y-m-d H:i') == (date('Y-m-d H:i'));
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
    protected function log($message)
    {
        $now = date($this->config['dateFormat'], $_SERVER['REQUEST_TIME']);

        if($logfile = $this->getLogfile())
            file_put_contents($logfile, "[$now] $message\n", FILE_APPEND);
    }

    /**
     * @return bool
     */
    protected function isFunction()
    {
        $cmd = @unserialize($this->config['command']);

        if ($cmd === false) {
            return false;
        }

        return is_object($cmd) && $cmd instanceof SerializableClosure;
    }

    /**
     *
     */
    protected function runFunction()
    {
        /** @var SerializableClosure $command */
        $command = unserialize($this->config['command']);

        ob_start();
        $retval = $command();
        $content = ob_get_contents();
        if($logfile = $this->getLogfile())
            file_put_contents($this->getLogfile(), $content, FILE_APPEND);
        ob_end_clean();

        if ($retval !== true) {
            throw new Exception(
                "Closure did not return true! Returned:\n" . print_r($retval, true)
            );
        }
    }

    /**
     *
     */
    protected function runFile()
    {
        // If job should run as another user, we must be on *nix and
        // must have sudo privileges.
        $isUnix = ($this->helper->getPlatform() === Helper::UNIX);
        $useSudo = "";

        if ($isUnix) {
            $hasRunAs = !empty($this->config["runAs"]);
            $isRoot = (posix_getuid() === 0);
            if ($hasRunAs && $isRoot) {
                $useSudo = "sudo -u {$this->config['runAs']}";
            }
        }

        // Start execution. Run in foreground (will block).
        $command = $this->config['command'];
        $logfile = $this->getLogfile() ?: '/dev/null';
        exec("$useSudo $command 1>> $logfile 2>&1", $dummy, $retval);

        if ($retval !== 0) {
            throw new Exception("Job exited with status '$retval'.");
        }
    }
}

// run this file, if executed directly
// @see: http://stackoverflow.com/questions/2413991/php-equivalent-of-pythons-name-main
// @codeCoverageIgnoreStart
if (!debug_backtrace()) {
    if (file_exists('vendor/autoload.php')) {
        require('vendor/autoload.php');
    } else {
        require(dirname(dirname(dirname(dirname(dirname(__DIR__))))) . '/vendor/autoload.php');
    }

    spl_autoload_register(function ($class) {
        $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
        require(dirname(__DIR__) . "/{$class}.php");
    });

    parse_str($argv[2], $config);

    $restoreNullValues = function ($config) {
        return array_merge(
            array(
                'recipients' => null,
                'mailer' => null,
                'maxRuntime' => null,
                'smtpHost' => null,
                'smtpPort' => null,
                'smtpUsername' => null,
                'smtpPassword' => null,
                'smtpSecurity' => null,
                'runAs' => null,
                'environment' => null,
                'runOnHost' => null,
                'output' => null,
                'dateFormat' => null,
                'enabled' => null,
                'haltDir' => null,
                'debug' => null,
            ),
            $config
        );
    };
    $config = $restoreNullValues($config);

    $job = new BackgroundJob($argv[1], $config);
    $job->run();
}
// @codeCoverageIgnoreEnd
