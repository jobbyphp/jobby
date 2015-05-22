<?php

namespace Jobby;

use SuperClosure\SerializableClosure;

/**
 *
 */
class Jobby
{
    /**
     * @var array
     */
    protected $config = array();

    /**
     * @var string
     */
    protected $script;

    /**
     * @var array
     */
    protected $jobs = array();

    /**
     * @var Helper
     */
    protected $helper;

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
    protected function getHelper()
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
            'smtpSender' => 'jobby@' . $this->getHelper()->getHost(),
            'smtpSenderName' => 'jobby',
            'smtpSecurity' => null,
            'runAs' => null,
            'environment' => $this->getHelper()->getApplicationEnv(),
            'runOnHost' => $this->getHelper()->getHost(),
            'output' => null,
            'dateFormat' => 'Y-m-d H:i:s',
            'enabled' => true,
            'haltDir' => null,
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
     * @param $job
     * @param array $config
     * @throws Exception
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
        $isUnix = ($this->helper->getPlatform() === Helper::UNIX);

        if ($isUnix && !extension_loaded('posix')) {
            throw new Exception("'posix' extension is required");
        }

        foreach ($this->jobs as $job => $config) {
            if ($isUnix) {
                $this->runUnix($job, $config);
            } else {
                $this->runWindows($job, $config);
            }
        }
    }

    /**
     * @param string $job
     * @param array $config
     */
    protected function runUnix($job, array $config)
    {
        if ($config['debug']) {
            $output = 'debug.log';
        } else {
            $output = '/dev/null';
        }

        $command = $this->getExecutableCommand($job, $config);
        exec("php $command 1> $output 2>&1 &");
    }

    // @codeCoverageIgnoreStart
    /**
     * @param string $job
     * @param array $config
     */
    protected function runWindows($job, array $config)
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
    protected function getExecutableCommand($job, array $config)
    {
        if ($config['command'] instanceof SerializableClosure) {
            $config['command'] = serialize($config['command']);

        } else if ($config['command'] instanceof \Closure) {
            // Convert closures to its source code as a string so that we
            // can send it on the command line.
            $config['command'] = $this->getHelper()
                ->closureToString($config['command']);
        }

        $configQuery = http_build_query($config);
        return "\"$this->script\" \"$job\" \"$configQuery\"";
    }
}
