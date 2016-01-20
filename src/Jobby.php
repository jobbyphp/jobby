<?php

namespace Jobby;

use Closure;
use SuperClosure\SerializableClosure;
use Symfony\Component\Process\PhpExecutableFinder;

class Jobby
{
    use SerializerTrait;

    /**
     * @var array
     */
    protected $config = [];

    /**
     * @var string
     */
    protected $script;

    /**
     * @var array
     */
    protected $jobs = [];

    /**
     * @var Helper
     */
    protected $helper;

    /**
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->setConfig($this->getDefaultConfig());
        $this->setConfig($config);

        $this->script = realpath(__DIR__ . '/../bin/run-job');
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
        return [
            'jobClass'       => 'Jobby\BackgroundJob',
            'recipients'     => null,
            'mailer'         => 'sendmail',
            'maxRuntime'     => null,
            'smtpHost'       => null,
            'smtpPort'       => 25,
            'smtpUsername'   => null,
            'smtpPassword'   => null,
            'smtpSender'     => 'jobby@' . $this->getHelper()->getHost(),
            'smtpSenderName' => 'jobby',
            'smtpSecurity'   => null,
            'runAs'          => null,
            'environment'    => $this->getHelper()->getApplicationEnv(),
            'runOnHost'      => $this->getHelper()->getHost(),
            'output'         => null,
            'dateFormat'     => 'Y-m-d H:i:s',
            'enabled'        => true,
            'haltDir'        => null,
            'debug'          => false,
        ];
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
     * Add a job.
     *
     * @param string $job
     * @param array  $config
     *
     * @throws Exception
     */
    public function add($job, array $config)
    {
        if (empty($config['schedule'])) {
            throw new Exception("'schedule' is required for '$job' job");
        }

        if (!(isset($config['command']) xor isset($config['closure']))) {
            throw new Exception("Either 'command' or 'closure' is required for '$job' job");
        }

        if (isset($config['command']) &&
            (
                $config['command'] instanceof Closure ||
                $config['command'] instanceof SerializableClosure
            )
        ) {
            $config['closure'] = $config['command'];
            unset($config['command']);

            if ($config['closure'] instanceof SerializableClosure) {
                $config['closure'] = $config['closure']->getClosure();
            }
        }

        $config = array_merge($this->config, $config);
        $this->jobs[$job] = $config;
    }

    /**
     * Run all jobs.
     */
    public function run()
    {
        $isUnix = ($this->helper->getPlatform() === Helper::UNIX);

        if ($isUnix && !extension_loaded('posix')) {
            throw new Exception('posix extension is required');
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
     * @param array  $config
     */
    protected function runUnix($job, array $config)
    {
        $command = $this->getExecutableCommand($job, $config);
        $binary = $this->getPhpBinary();

        $output = $config['debug'] ? 'debug.log' : '/dev/null';
        exec("$binary $command 1> $output 2>&1 &");
    }

    // @codeCoverageIgnoreStart
    /**
     * @param string $job
     * @param array  $config
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
     * @param array  $config
     *
     * @return string
     */
    protected function getExecutableCommand($job, array $config)
    {
        if (isset($config['closure'])) {
            $config['closure'] = $this->getSerializer()->serialize($config['closure']);
        }

        if (strpos(__DIR__, 'phar://') === 0) {
            $script = __DIR__ . DIRECTORY_SEPARATOR . 'BackgroundJob.php';
            return sprintf(' -r \'define("JOBBY_RUN_JOB",1);include("%s");\' "%s" "%s"', $script, $job, http_build_query($config));
        }

        return sprintf('"%s" "%s" "%s"', $this->script, $job, http_build_query($config));
    }

    /**
     * @return false|string
     */
    protected function getPhpBinary()
    {
        $executableFinder = new PhpExecutableFinder();

        return $executableFinder->find();
    }
}
