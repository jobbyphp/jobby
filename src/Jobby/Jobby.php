<?php

namespace Jobby;

use Jobby\Helper;
use Jobby\Exception;

/**
 *
 */
class Jobby
{
    /**
     * @var array
     */
    private $config = array();

    /**
     * @var string
     */
    private $script;

    /**
     * @var array
     */
    private $jobs = array();

    /**
     * @var Helper
     */
    private $helper;

    /**
     * @param array $config
     */
    public function __construct(array $config = array())
    {
        $this->setConfig($this->getDefaultConfig());
        $this->setConfig($config);

        $this->script = __DIR__ . DIRECTORY_SEPARATOR . 'BackgroundJob.php';
    }

    /**
     * @return Helper
     */
    private function getHelper()
    {
        if ($this->helper === null) {
            $this->helper = new Helper();
        }

        return $this->helper;
    }

    /**
     * @return array
     */
    public function getDefaultConfig()
    {
        return array(
            'recipients' => null,
            'mailer' => 'sendmail',
            'maxRuntime' => null,
            'smtpHost' => null,
            'smtpPort' => 25,
            'smtpUsername' => null,
            'smtpPassword' => null,
            'smtpSender' => 'jobby@'.$this->getHelper()->getHost(),
            'smtpSenderName' => 'jobby',
            'smtpSecurity' => null,
            'runAs' => null,
            'environment' => $this->getHelper()->getApplicationEnv(),
            'runOnHost' => $this->getHelper()->getHost(),
            'output' => null,
            'dateFormat' => 'Y-m-d H:i:s',
            'enabled' => true,
            'debug' => false,
        );
    }

    /**
     * @param array
     */
    public function setConfig(array $config)
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param string $job
     * @param array $config
     */
    public function add($job, array $config)
    {
        foreach (array("command", "schedule") as $field) {
            if (empty($config[$field])) {
                throw new Exception("'$field' is required for '$job' job");
            }
        }

        $config = array_merge($this->config, $config);
        $this->jobs[$job] = $config;
    }

    /**
     *
     */
    public function run()
    {
        if (! extension_loaded('posix')) {
            throw new Exception("'posix' extension is required");
        }

        foreach ($this->jobs as $job => $config) {
            if ($this->getHelper()->getPlatform() === Helper::WINDOWS) {
                $this->runWindows($job, $config);
            } else {
                $this->runUnix($job, $config);
            }
        }
    }

    /**
     * @param string $job
     * @param array $config
     */
    private function runUnix($job, array $config)
    {
        if ($config['debug']) {
            $output = 'debug.log';
        } else {
            $output = '/dev/null';
        }

        if ($config['command'] instanceof \Closure) {
            $j = new BackgroundJob($job, $config);
            $j->run();
        } else {
            $command = $this->getExecutableCommand($job, $config);
            exec("php $command 1> $output 2>&1 &");
        }
    }

    // @codeCoverageIgnoreStart
    /**
     * @param string $job
     * @param array $config
     */
    private function runWindows($job, array $config)
    {
        // Run in background (non-blocking). From
        // http://us3.php.net/manual/en/function.exec.php#43834

        $command = $this->getExecutableCommand($job, $config);
        pclose(popen("start \"blah\" /B \"php.exe\" $command", "r"));
    }
    // @codeCoverageIgnoreEnd

    /**
     * @param string $job
     * @param array $config
     * @return string
     */
    private function getExecutableCommand($job, array $config)
    {
        // Convert closures to its source code as a string so that we
        // can send it on the command line.
        if ($config['command'] instanceof \Closure) {
            $config['command'] = $this->getHelper()
                ->closureToString($config['command']);
        }

        $configQuery = http_build_query($config);
        return "\"$this->script\" \"$job\" \"$configQuery\"";
    }
}
