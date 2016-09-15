<?php
namespace Jobby;

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
     * @var resource[]
     */
    private $lockHandles = [];

    /**
     * @var \Swift_Mailer
     */
    private $mailer;

    /**
     * @param \Swift_Mailer $mailer
     */
    public function __construct(\Swift_Mailer $mailer = null)
    {
        $this->mailer = $mailer;
    }

    /**
     * @param string $job
     * @param array  $config
     * @param string $message
     *
     * @return \Swift_Message
     */
    public function sendMail($job, array $config, $message)
    {
        $host = $this->getHost();
        $body = <<<EOF
$message

You can find its output in {$config['output']} on $host.

Best,
jobby@$host
EOF;
        $mail = \Swift_Message::newInstance();
        $mail->setTo(explode(',', $config['recipients']));
        $mail->setSubject("[$host] '{$job}' needs some attention!");
        $mail->setBody($body);
        $mail->setFrom([$config['smtpSender'] => $config['smtpSenderName']]);
        $mail->setSender($config['smtpSender']);

        $mailer = $this->getCurrentMailer($config);
        $mailer->send($mail);

        return $mail;
    }

    /**
     * @param array $config
     *
     * @return \Swift_Mailer
     */
    private function getCurrentMailer(array $config)
    {
        if ($this->mailer !== null) {
            return $this->mailer;
        }

        if ($config['mailer'] == 'smtp') {
            $transport = \Swift_SmtpTransport::newInstance(
                $config['smtpHost'],
                $config['smtpPort'],
                $config['smtpSecurity']
            );
            $transport->setUsername($config['smtpUsername']);
            $transport->setPassword($config['smtpPassword']);
        } elseif ($config['mailer'] == 'mail') {
            $transport = \Swift_MailTransport::newInstance();
        } else {
            $transport = \Swift_SendmailTransport::newInstance();
        }

        return \Swift_Mailer::newInstance($transport);
    }

    /**
     * @param string $lockFile
     *
     * @throws Exception
     * @throws InfoException
     */
    public function acquireLock($lockFile)
    {
        if (array_key_exists($lockFile, $this->lockHandles)) {
            throw new Exception("Lock already acquired (Lockfile: $lockFile).");
        }

        if (!file_exists($lockFile) && !touch($lockFile)) {
            throw new Exception("Unable to create file (File: $lockFile).");
        }

        $fh = fopen($lockFile, 'r+');
        if ($fh === false) {
            throw new Exception("Unable to open file (File: $lockFile).");
        }

        $attempts = 5;
        while ($attempts > 0) {
            if (flock($fh, LOCK_EX | LOCK_NB)) {
                $this->lockHandles[$lockFile] = $fh;
                ftruncate($fh, 0);
                fwrite($fh, getmypid());

                return;
            }
            usleep(250);
            --$attempts;
        }

        throw new InfoException("Job is still locked (Lockfile: $lockFile)!");
    }

    /**
     * @param string $lockFile
     *
     * @throws Exception
     */
    public function releaseLock($lockFile)
    {
        if (!array_key_exists($lockFile, $this->lockHandles)) {
            throw new Exception("Lock NOT held - bug? Lockfile: $lockFile");
        }

        if ($this->lockHandles[$lockFile]) {
            ftruncate($this->lockHandles[$lockFile], 0);
            flock($this->lockHandles[$lockFile], LOCK_UN);
        }

        unset($this->lockHandles[$lockFile]);
    }

    /**
     * @param string $lockFile
     *
     * @return int
     */
    public function getLockLifetime($lockFile)
    {
        if (!file_exists($lockFile)) {
            return 0;
        }

        $pid = file_get_contents($lockFile);
        if (empty($pid)) {
            return 0;
        }

        if (!posix_kill(intval($pid), 0)) {
            return 0;
        }

        $stat = stat($lockFile);

        return (time() - $stat["mtime"]);
    }

    /**
     * @return string
     */
    public function getTempDir()
    {
        // @codeCoverageIgnoreStart
        if (function_exists('sys_get_temp_dir')) {
            $tmp = sys_get_temp_dir();
        } elseif (!empty($_SERVER['TMP'])) {
            $tmp = $_SERVER['TMP'];
        } elseif (!empty($_SERVER['TEMP'])) {
            $tmp = $_SERVER['TEMP'];
        } elseif (!empty($_SERVER['TMPDIR'])) {
            $tmp = $_SERVER['TMPDIR'];
        } else {
            $tmp = getcwd();
        }
        // @codeCoverageIgnoreEnd

        return $tmp;
    }

    /**
     * @return string
     */
    public function getHost()
    {
        return php_uname('n');
    }

    /**
     * @return string|null
     */
    public function getApplicationEnv()
    {
        return isset($_SERVER['APPLICATION_ENV']) ? $_SERVER['APPLICATION_ENV'] : null;
    }

    /**
     * @return int
     */
    public function getPlatform()
    {
        if (strncasecmp(PHP_OS, 'Win', 3) == 0) {
            // @codeCoverageIgnoreStart
            return self::WINDOWS;
            // @codeCoverageIgnoreEnd
        } else {
            return self::UNIX;
        }
    }

    /**
     * @param string $input
     *
     * @return string
     */
    public function escape($input)
    {
        $input = strtolower($input);
        $input = preg_replace('/[^a-z0-9_. -]+/', '', $input);
        $input = trim($input);
        $input = str_replace(' ', '_', $input);
        $input = preg_replace('/_{2,}/', '_', $input);

        return $input;
    }

    public function getSystemNullDevice()
    {
        $platform = $this->getPlatform();
        if ($platform === self::UNIX) {
            return "/dev/null";
        }
        return "NUL";
    }
}
