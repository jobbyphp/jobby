<?php
namespace Jobby;

/**
 *
 */
class Helper
{
    /**
     * @var int
     */
    const UNIX = 0;

    /**
     * @var int
     */
    const WINDOWS = 1;

    /**
     * @param string $job
     * @param array $config
     * @param int $retval
     */
    public function sendMail($job, array $config, $retval)
    {
        $host = $this->getHost();
        $body = <<<EOF
'$job' exited with status of $retval.

You can find its output in {$config['output']} on $host.

Best,
jobby@$host
EOF;

        $mail = \Swift_Message::newInstance();
        $mail->setTo(explode(',', $config['recipients']));
        $mail->setSubject("[$host] '{$job}' exited with status of $retval");
        $mail->setFrom(array("jobby@$host" => 'jobby'));
        $mail->setSender("jobby@$host");
        $mail->setBody($body);

        if ($config['mailer'] == 'smtp') {
            $transport = \Swift_SmtpTransport::newInstance($config['smtpHost'], $config['smtpPort']);
            $transport->setUsername($config['smtpUsername']);
            $transport->setPassword($config['smtpPassword']);
        } else {
            $transport = \Swift_SendmailTransport::newInstance();
        }

        $mailer = \Swift_Mailer::newInstance($transport);
        $mailer->send($mail);
    }

    /**
     * @return string
     */
    public function getTempDir()
    {
        if (function_exists('sys_get_temp_dir')) {
            $tmp = sys_get_temp_dir();
        } else if (!empty($_SERVER['TMP'])) {
            $tmp = $_SERVER['TMP'];
        } else if (!empty($_SERVER['TEMP'])) {
            $tmp = $_SERVER['TEMP'];
        } else if (!empty($_SERVER['TMPDIR'])) {
            $tmp = $_SERVER['TMPDIR'];
        } else {
            $tmp = getcwd();
        }

        return $tmp;
    }

    /**
     * @return string
     */
    public function getHost()
    {
        $host = gethostname();
        if ($host === false) {
            $host = php_uname('n');
        }

        return $host;
    }

    /**
     * @return string|null
     */
    public function getApplicationEnv()
    {
        if (getenv('APPLICATION_ENV')) {
            return getenv('APPLICATION_ENV');
        } else {
            return null;
        }
    }

    /**
     * @return int
     */
    public function getPlatform()
    {
        if (strncasecmp(PHP_OS, "Win", 3) == 0) {
            return self::WINDOWS;
        } else {
            return self::UNIX;
        }
    }

    /**
     * @param closure $fn
     * @return string
     */
    public function closureToString($fn)
    {
        // From http://www.htmlist.com/development/extending-php-5-3-closures-with-serialization-and-reflection/
        $reflection = new \ReflectionFunction($fn);

        // Open file and seek to the first line of the closure
        $file = new \SplFileObject($reflection->getFileName());
        $file->seek($reflection->getStartLine() - 1);

        // Retrieve all of the lines that contain code for the closure
        $code = '';
        while ($file->key() < $reflection->getEndLine()) {
            $code .= $file->current();
            $file->next();
        }

        // Only keep the code defining that closure
        $begin = strpos($code, 'function');
        $end = strrpos($code, '}');
        $code = substr($code, $begin, $end - $begin + 1);

        return str_replace(array("\r\n", "\n"), '', $code);
    }

    /**
     * @param string $input
     * @return string
     */
    public function escape($input)
    {
        $input = strtolower($input);
        $input = preg_replace("/[^a-z0-9_.\- ]+/", "", $input);
        $input = trim($input);
        $input = str_replace(" ", "_", $input);
        $input = preg_replace("/_{2,}/", "_", $input);
        return $input;
    }
}
