<?php

use Jobby\Helper;

/**
 *
 */
class Jobby
{
    /**
     * @var array
     */
    private $_config = array();

    /**
     * @var string
     */
    private $_script;

    /**
     * @var array
     */
    private $_jobs = array();

    /**
     * @var Jobby\Helper
     */
    private $_helper;

    /**
     * @param array $config
     */
    public function __construct(array $config = array())
    {
        $this->setConfig($this->_getDefaultConfig());
        $this->setConfig($config);

        $this->_script = __DIR__ . DIRECTORY_SEPARATOR
            . 'Jobby' . DIRECTORY_SEPARATOR
            . 'BackgroundJob.php';
    }

    /**
     * @return Jobby\Helper
     */
    private function getHelper()
    {
        if ($this->_helper === null) {
            $this->_helper = new Jobby\Helper();
        }

        return $this->_helper;
    }

    /**
     * @return array
     */
    private function _getDefaultConfig()
    {
        return array(
            'recipients' => null,
            'mailer' => 'sendmail',
            'smtpHost' => null,
            'smtpPort' => 25,
            'smtpUsername' => null,
            'smtpPassword' => null,
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
        $this->_config = array_merge($this->_config, $config);
    }

    /**
     * @param string $job
     * @param array $config
     */
    public function add($job, array $config)
    {
        foreach (array("command", "schedule") as $field) {
            if (empty($config[$field])) {
                throw new \Jobby\Exception("'$field' is required for '$job' job");
            }
        }

        $config = array_merge($this->_config, $config);
        $this->_jobs[$job] = $config;
    }

    /**
     *
     */
    public function run()
    {
        foreach ($this->_jobs as $job => $config) {
            if ($this->getHelper()->getPlatform() === Jobby\Helper::WINDOWS) {
                $this->_runWindows($job, $config);
            } else {
                $this->_runUnix($job, $config);
            }
        }
    }

    /**
     * @param string $job
     * @param array $config
     */
    private function _runUnix($job, array $config)
    {
        if ($config['debug']) {
            $output = 'debug.log';
        } else {
            $output = '/dev/null';
        }

        $command = $this->_getExecutableCommand($job, $config);
        exec("php $command 1> $output 2>&1 &");
    }

    /**
     * @param string $job
     * @param array $config
     */
    private function _runWindows($job, array $config)
    {
        // Run in background (non-blocking). From
        // http://us3.php.net/manual/en/function.exec.php#43834

        $command = $this->_getExecutableCommand($job, $config);
        pclose(popen("start \"blah\" /B \"php.exe\" $command", "r"));
    }

    /**
     * @param string $job
     * @param array $config
     * @return string
     */
    private function _getExecutableCommand($job, array $config)
    {
        // Convert closures to its source code as a string so that we
        // can send it on the command line.
        if ($config['command'] instanceof Closure) {
            $config['command'] = $this->getHelper()
                ->closureToString($config['command']);
        }

        $configQuery = http_build_query($config);
        return "\"$this->_script\" \"$job\" \"$configQuery\"";
    }
}
