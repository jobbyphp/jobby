<?php
namespace Jobby;

use SuperClosure\SerializableClosure;

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
     * @var array
     */
    private $lockHandles = array();

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
     * @param array $config
     * @param string $message
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
        $mail->setFrom(array($config['smtpSender'] => $config['smtpSenderName']));
        $mail->setSender($config['smtpSender']);

        $mailer = $this->getCurrentMailer($config);
        $mailer->send($mail);

        return $mail;
    }

    /**
     * @param array $config
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
        } elseif($config['mailer'] == 'mail'){
            $transport = \Swift_MailTransport::newInstance();
        } else {
            $transport = \Swift_SendmailTransport::newInstance();
        }

        return \Swift_Mailer::newInstance($transport);
    }

    /**
     *
     */
    public function acquireLock($lockfile)
    {
        if (array_key_exists($lockfile, $this->lockHandles)) {
            throw new Exception("Lock already acquired (Lockfile: $lockfile).");
        }

        if (!file_exists($lockfile) && !touch($lockfile)) {
            throw new Exception("Unable to create file (File: $lockfile).");
        }

        $fh = fopen($lockfile, "r+");
        if ($fh === false) {
            throw new Exception("Unable to open file (File: $lockfile).");
        }

        $attempts = 5;
        while ($attempts > 0) {
            if (flock($fh, LOCK_EX | LOCK_NB)) {
                $this->lockHandles[$lockfile] = $fh;
                ftruncate($fh, 0);
                fwrite($fh, getmypid());
                return;
            }
            usleep(250);
            --$attempts;
        }

        throw new InfoException("Job is still locked (Lockfile: $lockfile)!");
    }

    /**
     * @param string $lockfile
     */
    public function releaseLock($lockfile)
    {
        if (!array_key_exists($lockfile, $this->lockHandles)) {
            throw new Exception("Lock NOT held - bug? Lockfile: $lockfile");
        }

        if ($this->lockHandles[$lockfile]) {
            ftruncate($this->lockHandles[$lockfile], 0);
            flock($this->lockHandles[$lockfile], LOCK_UN);
        }

        unset($this->lockHandles[$lockfile]);
    }

    /**
     * @param string $lockfile
     * @return int
     */
    public function getLockLifetime($lockfile)
    {
        if (!file_exists($lockfile)) {
            return 0;
        }

        $pid = file_get_contents($lockfile);
        if (empty($pid)) {
            return 0;
        }

        if (!posix_kill(intval($pid), 0)) {
            return 0;
        }

        $stat = stat($lockfile);
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
        } else if (!empty($_SERVER['TMP'])) {
            $tmp = $_SERVER['TMP'];
        } else if (!empty($_SERVER['TEMP'])) {
            $tmp = $_SERVER['TEMP'];
        } else if (!empty($_SERVER['TMPDIR'])) {
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
        if (isset($_SERVER['APPLICATION_ENV'])) {
            return $_SERVER['APPLICATION_ENV'];
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
            // @codeCoverageIgnoreStart
            return self::WINDOWS;
            // @codeCoverageIgnoreEnd
        } else {
            return self::UNIX;
        }
    }

    /**
     * @param \Closure $fn
     * @return string
     */
    public function closureToString(\Closure $fn)
    {
        $code = new SerializableClosure($fn);

        return serialize($code);
    }

    /**
     * @param string $input
     * @return string
     */
    public function escape($input)
    {
        $input = strtolower($input);
        $input = preg_replace("/[^a-z0-9_. -]+/", "", $input);
        $input = trim($input);
        $input = str_replace(" ", "_", $input);
        $input = preg_replace("/_{2,}/", "_", $input);
        return $input;
    }
}
