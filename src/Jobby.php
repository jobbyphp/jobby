<?php

class Jobby
{
    private $_config;
    private $_jobs;

    public function __construct($config = null)
    {
        $this->_config = array(
            'recipients' => null,
            'mailer' => 'sendmail',
            'smtpHost' => null,
            'smtpPort' => 25,
            'smtpUsername' => null,
            'smtpPassword' => null,
            'runAs' => null,
            'environment' => $this->_env(),
            'runOnHost' => $this->_host(),
            'output' => null,
            'dateFormat' => 'Y-m-d H:i:s',
            'enabled' => true,
            'debug' => false,
        );

        if ($config !== null)
        {
            $this->_config = array_merge($this->_config, $config);
        }

        $this->_jobs = array();
    }

    private function _host()
    {
        if (($host = gethostname()) === false)
        {
            $host = php_uname('n');
        }

        return $host;
    }

    private function _env()
    {
        $env = null;
        if (getenv('APPLICATION_ENV'))
        {
            $env = getenv('APPLICATION_ENV');
        }

        return $env;
    }

    const UNIX = 0;
    const WINDOWS = 1;

    private function _platform()
    {
        $platform = self::UNIX;
        if (strncasecmp(PHP_OS, "Win", 3) == 0)
        {
            $platform = self::WINDOWS;
        }

        return $platform;
    }

    private function _toCode($fn)
    {
        // From http://www.htmlist.com/development/extending-php-5-3-closures-with-serialization-and-reflection/
        $reflection = new \ReflectionFunction($fn);

        // Open file and seek to the first line of the closure
        $file = new \SplFileObject($reflection->getFileName());
        $file->seek($reflection->getStartLine() - 1);

        // Retrieve all of the lines that contain code for the closure
        $code = '';
        while ($file->key() < $reflection->getEndLine())
        {
            $code .= $file->current();
            $file->next();
        }

        // Only keep the code defining that closure
        $begin = strpos($code, 'function');
        $end = strrpos($code, '}');
        $code = substr($code, $begin, $end - $begin + 1);

        return $code;
    }

    public function setConfig($config)
    {
        $this->_config = array_merge($this->_config, $config);
    }

    public function add($job, $config)
    {
        // 'command' is required
        if (empty($config['command']))
        {
            throw new \Jobby\Exception("'command' is required for '$job' job");
        }

        // 'schedule' is required
        if (empty($config['schedule']))
        {
            throw new \Jobby\Exception("'schedule' is required for '$job' job");
        }

        $config = array_merge($this->_config, $config);

        $this->_jobs[$job] = $config;
    }

    public function run()
    {
        $script = __DIR__ . DIRECTORY_SEPARATOR . 'Jobby' . DIRECTORY_SEPARATOR . 'BackgroundJob.php';

        foreach ($this->_jobs as $job => $config)
        {
            $debug = $config['debug'];

            if ($config['command'] instanceof Closure)
            {
                // Convert closures to its source code as a string so that we
                // can send it on the command line.
                
                $config['command'] = $this->_toCode($config['command']);
            }

            $config = http_build_query($config);
            $command = "\"$script\" \"$job\" \"$config\"";

            // Run in background (non-blocking). From
            // http://us3.php.net/manual/en/function.exec.php#43834
            if ($this->_platform() === self::WINDOWS)
            {
                pclose(popen("start \"blah\" /B \"php.exe\" $command", "r"));
            }
            else
            {
                $output = '/dev/null';
                if ($debug)
                {
                    $output = 'debug.log';
                }

                exec("php $command 1> $output 2>&1 &");   
            }
        }
    }

    // For Composer install
    public static function install($event)
    {
        $src = dirname(dirname(__DIR__)) . '/resources/jobby.php';
        $dst = getcwd();

        if ((copy($src, $dst)) === false)
        {
            echo "\n";
            echo "Could not copy jobby.php.\n";
            echo "You can still manually copy $src to $dst.";
            echo "\n";
            return;
        }

        echo "\n";
        echo "Created jobby.php.";
        echo "\n";
    }
}

