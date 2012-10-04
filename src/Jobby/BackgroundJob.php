<?php

namespace Jobby;

class BackgroundJob
{
    private function _tmpdir()
    {
        if (function_exists('sys_get_temp_dir'))
        {
            $tmp = sys_get_temp_dir();
        }
        else if (! empty($_SERVER['TMP']))
        {
            $tmp = $_SERVER['TMP'];
        }
        else if (! empty($_SERVER['TEMP']))
        {
            $tmp = $_SERVER['TEMP'];
        }
        else if (! empty($_SERVER['TMPDIR']))
        {
            $tmp = $_SERVER['TMPDIR'];
        }
        else
        {
            $tmp = getcwd();
        }

        return $tmp;
    }

    private function _host()
    {
        if (($host = gethostname()) === false)
        {
            $host = php_uname('n');
        }

        return $host;
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

    private function _mail($job, $retval, $config)
    {
        $host = $this->_host();
        $body = <<<EOF
'$job' exited with status of $retval.

You can find its output in {$config['output']} on $host.

Best,
jobby@$host
EOF;

        $mail = \Swift_Message::newInstance();
        $mail->setTo(explode(',', $config['recipients']));
        $mail->setSubject("[$host] '$job' exited with status of $retval");
        $mail->setFrom(array("jobby@$host" => 'jobby'));
        $mail->setSender("jobby@$host");
        $mail->setBody($body);

        if ($config['mailer'] == 'smtp')
        {
            $transport = \Swift_SmtpTransport::newInstance($config['smtpHost'], $config['smtpPort']);
            $transport->setUsername($config['smtpUsername']);
            $transport->setPassword($config['smtpPassword']);
        }
        else
        {
            $transport = \Swift_SendmailTransport::newInstance();
        }

        $mailer = \Swift_Mailer::newInstance($transport);
        $mailer->send($mail);
    }

    public function run($job, $config)
    {
        // Check for logfile
        $logfile = '/dev/null';
        if ($config['output'] !== null)
        {
            $logfile = $config['output'];
            $logs = dirname($logfile);
            if (! file_exists($logs))
            {
                mkdir($logs, 0777, true);
            }
        }

        $tmp = $this->_tmpdir();
        $lockfile = "$tmp/$job.lck";
        if (! empty($config['environment']))
        {
            $lockfile = "$tmp/{$config['environment']}-$job.lck";
        }

        $cp = new CronParser($config['schedule']);

        // If 1) job is enabled
        //    2) and, we are on specified host
        //    3) and, job is scheduled to run
        // then, start execution.
        if (   $config['enabled']
            && strcasecmp($config['runOnHost'], $this->_host()) == 0
            && $cp->shouldRun())
        {
            // Check for lock file
            if (file_exists($lockfile))
            {
                $now = date($config['dateFormat'], $_SERVER['REQUEST_TIME']);
                file_put_contents($logfile, "$now: Lock file found in $lockfile. Skipping.", FILE_APPEND);
                return;
            }

            // Create lock file
            touch($lockfile);

            $command = $config['command'];

            if (preg_match('/^function\(.*\).*}$/', $command))
            {
                // If job is an anonymous function string, eval it to get the
                // closure, and run the closure.
                eval('$command = ' . $command . ';');

                ob_start();
                $retval = (bool) $command();
                file_put_contents($logfile, ob_get_contents(), FILE_APPEND);
                ob_end_clean();
            }
            else
            {
                // Else job is a string to run on the command line.

                // If job should run as another user, we must be on *nix and
                // must have sudo privileges.
                $useSudo = '';
                if (   ! empty($config['runAs'])
                    && $this->_platform() !== self::WINDOWS
                    && posix_getuid() === 0)
                {
                    $useSudo = "sudo -u {$config['runAs']}";
                }

                // Start execution. Run in foreground (will block).
                exec("$useSudo $command 1>> $logfile 2>&1", $dummy, $retval);
            }

            // Remove lock file
            unlink($lockfile);

            // Mail log file if error
            if ((bool) $retval && ! empty($config['recipients']))
            {
                $this->_mail($job, $retval, $config);
            }
        }
    }
}

require(dirname(dirname(__DIR__)) . '/vendor/autoload.php');

spl_autoload_register(function ($class) {
    $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    require(dirname(__DIR__) . "/{$class}.php");
});

$job = new BackgroundJob();
parse_str($argv[2], $config);
$job->run($argv[1], $config);
