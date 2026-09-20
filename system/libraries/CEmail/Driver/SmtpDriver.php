<?php

/**
 * @deprecated 1.9 jalur pengirim lama; pakai CEmail::mailer() dengan transport `smtp` (atau nyalakan `email.legacy_sender_via_mailer`)
 */
class CEmail_Driver_SmtpDriver extends CEmail_DriverAbstract {
    /**
     * The SMTP connection.
     */
    protected $smtpConnection = null;

    /**
     * Pipelining enabled?
     */
    protected $pipelining = false;

    protected $debug = false;

    public function send(array $to, $subject, $body, $options = []) {
        // send the email
        try {
            $this->debug = carr::get($options, 'debug');

            return $this->sendEmail($to, $subject, $body, $options);
        } catch (Exception $e) {
            // something failed
            // disconnect if needed
            if ($this->smtpConnection) {
                if ($e instanceof CEmail_Exception_SmtpTimeoutException) {
                    // simply close the connection
                    fclose($this->smtpConnection);
                    $this->smtpConnection = null;
                } else {
                    // proper close, with a QUIT
                    $this->smtpDisconnect();
                }
            }

            // rethrow the exception
            throw $e;
        }
    }

    /**
     * Class destructor.
     */
    public function __destruct() {
        // makes sure any open connections will be closed
        if ($this->smtpConnection) {
            $this->smtpDisconnect();
        }
    }

    /**
     * Sends the actual email.
     *
     * @param mixed $subject
     * @param mixed $body
     * @param mixed $options
     *
     * @return bool success boolean
     */
    protected function sendEmail(array $to, $subject, $body, $options = []) {
        $smtpMessage = new CEmail_Driver_Smtp_Message($to, $body, $subject, $this->config, $options);
        $message = $smtpMessage->buildMessage();
        $smtpOptions = [];
        if (!empty($this->config->getOption('stream'))) {
            $smtpOptions = $this->config->getOption('stream');
        }

        $smtpPort = carr::get($options, 'port', $this->config->getOption('port', 25));

        $smtpHost = carr::get($options, 'host', $this->config->getOption('host'));

        if (empty($smtpHost)) {
            throw new Exception('Must supply a SMTP host and port, none given.');
        }
        $smtpUsername = carr::get($options, 'username', $this->config->getUsername());
        $smtpPassword = carr::get($options, 'password', $this->config->getPassword());

        // Use authentication?
        $authenticate = (empty($this->smtpConnection) && !empty($smtpUsername) and !empty($smtpPassword));

        // Connect
        $this->smtpConnect($smtpHost, $smtpPort, $smtpOptions);
        // Authenticate when needed
        if ($authenticate) {
            $this->smtpAuthenticate($smtpUsername, $smtpPassword);
        }

        // Set return path
        $returnPath = carr::get($options, 'returnPath', $this->config->getFrom());
        $this->debug('MAIL FROM:<' . $returnPath . '>');
        $this->debug($this->smtpSend('MAIL FROM:<' . $returnPath . '>', 250));

        foreach ([$to, carr::wrap(carr::get($options, 'cc', [])), carr::wrap(carr::get($options, 'bcc', []))] as $addresses) {
            // satu RCPT TO per alamat (RFC 5321); daftar berkoma dalam satu perintah ditolak server
            foreach ($this->emailAddresses($addresses) as $rcpt) {
                $this->debug('RCPT TO:<' . $rcpt . '>');
                $this->debug($this->smtpSend('RCPT TO:<' . $rcpt . '>', [250, 251]));
            }
        }

        // Prepare for data sending
        $this->debug('DATA');
        $this->smtpSend('DATA', 354);
        $newLine = $this->newline();

        $lines = explode($this->newline(), $message['header'] . preg_replace('/^\./m', '..$1', $message['body']));

        $field = substr($lines[0], 0, strpos($lines[0], ':'));
        $in_headers = false;
        if (!empty($field) && strpos($field, ' ') === false) {
            $in_headers = true;
        }

        $MAX_LINE_LENGTH = 998;

        foreach ($lines as $line) {
            $lines_out = [];
            if ($in_headers && $line === '') {
                $in_headers = false;
            }
            //Break this line up into several smaller lines if it's too long
            //Micro-optimisation: isset($str[$len]) is faster than (strlen($str) > $len),
            while (isset($line[$MAX_LINE_LENGTH])) {
                //Working backwards, try to find a space within the last MAX_LINE_LENGTH chars of the line to break on
                //so as to avoid breaking in the middle of a word
                $pos = strrpos(substr($line, 0, $MAX_LINE_LENGTH), ' ');
                //Deliberately matches both false and 0
                if (!$pos) {
                    //No nice break found, add a hard break
                    $pos = $MAX_LINE_LENGTH - 1;
                    $lines_out[] = substr($line, 0, $pos);
                    $line = substr($line, $pos);
                } else {
                    //Break at the found point
                    $lines_out[] = substr($line, 0, $pos);
                    //Move along by the amount we dealt with
                    $line = substr($line, $pos + 1);
                }
                //If processing headers add a LWSP-char to the front of new line RFC822 section 3.1.1
                if ($in_headers) {
                    $line = "\t" . $line;
                }
            }
            $lines_out[] = $line;

            //Send the lines to the server
            foreach ($lines_out as $line_out) {
                //Dot-stuffing as per RFC5321 section 4.5.2
                //https://tools.ietf.org/html/rfc5321#section-4.5.2
                if (!empty($line_out) && $line_out[0] === '.') {
                    $line_out = '.' . $line_out;
                }
                $this->debug(($line_out . "\r\n"));
                fwrite($this->smtpConnection, $line_out . "\r\n");
            }
        }

        // foreach ($lines as $line) {
        //     if (substr($line, 0, 1) === '.') {
        //         $line = '.' . $line;
        //     }
        //     $this->debug(($line . $newLine));
        //     fwrite($this->smtpConnection, $line . $newLine);
        // }

        // Finish the message
        $this->debug('.');
        $this->debug(($this->smtpSend('.', 250)));

        // Close the connection if we're not using pipelining
        $this->pipelining or $this->smtpDisconnect();

        return true;
    }

    /**
     * Connects to the given smtp and says hello to the other server.
     *
     * @param mixed $smtpHost
     * @param mixed $smtpPort
     * @param mixed $smtpOptions
     */
    protected function smtpConnect($smtpHost, $smtpPort, $smtpOptions = []) {
        // re-use the existing connection
        if (!empty($this->smtpConnection)) {
            return;
        }
        $secure = $this->config->getEncryption();
        $protocol = $this->config->getProtocol();
        if ($secure === 'ssl' && $protocol === 'tcp') {
            // implicit TLS (umumnya port 465): sambung langsung lewat ssl://
            $protocol = 'ssl';
        }
        // add a transport if not given
        if (strpos($smtpHost, '://') === false) {
            $smtpHost = $protocol . '://' . $smtpHost;
        }

        $context = stream_context_create();
        if (is_array($smtpOptions) and !empty($smtpOptions)) {
            stream_context_set_option($context, $smtpOptions);
        }

        $this->smtpConnection = stream_socket_client(
            $smtpHost . ':' . $smtpPort,
            $errCode,
            $errMessage,
            $this->getTimeout(),
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (empty($this->smtpConnection)) {
            throw new CEmail_Exception_SmtpConnectionException('Could not connect to SMTP: (' . $errCode . ') ' . $errMessage);
        }

        // Clear the smtp response
        $this->debug($this->smtpGetResponse());

        // Just say hello!
        try {
            $this->debug('EHLO' . ' ' . c::request()->server('SERVER_NAME', 'localhost.local'));
            $this->debug($this->smtpSend('EHLO' . ' ' . $this->getServerName(), 250));
            //$this->smtpSend('EHLO' . ' ' . 'localhost.local', 250);
        } catch (CEmail_Exception_SmtpCommandFailureException $e) {
            // Didn't work? Try HELO
            $this->debug('HELO' . ' ' . c::request()->server('SERVER_NAME', 'localhost.local'));
            $this->debug($this->smtpSend('HELO' . ' ' . $this->getServerName(), 250));
            //$this->smtpSend('HELO' . ' ' . 'localhost.local', 250);
        }

        // Enable TLS encryption if needed, and we're connecting using TCP

        if ($secure == 'tls' && $protocol != 'tls') {
            try {
                $this->debug('STARTTLS');
                $this->debug($this->smtpSend('STARTTLS', 220));
                if (!stream_socket_enable_crypto($this->smtpConnection, true, $this->cryptoMethod())) {
                    throw new CEmail_Exception_SmtpConnectionException('STARTTLS failed, Crypto client can not be enabled.');
                }
            } catch (CEmail_Exception_SmtpCommandFailureException $e) {
                throw new CEmail_Exception_SmtpConnectionException('STARTTLS failed, invalid return code received from server.');
            }

            // Say hello again, the service list might be updated (see RFC 3207 section 4.2)
            try {
                $this->debug('EHLO' . ' ' . c::request()->server('SERVER_NAME', 'localhost.local'));
                $this->debug($this->smtpSend('EHLO' . ' ' . $this->getServerName(), 250));
            } catch (CEmail_Exception_SmtpCommandFailureException $e) {
                // Didn't work? Try HELO
                $this->debug('HELO' . ' ' . c::request()->server('SERVER_NAME', 'localhost.local'));
                $this->debug($this->smtpSend('HELO' . ' ' . $this->getServerName(), 250));
            }
        }

        try {
            $this->debug('HELP');
            $this->debug($this->smtpSend('HELP', 214));
        } catch (CEmail_Exception_SmtpCommandFailureException $e) {
            // Let this pass as some servers don't support this.
        }

        return $this->smtpConnection;
    }

    /**
     * Close SMTP connection.
     */
    /**
     * TLS 1.2+ (dan 1.3 bila tersedia) untuk STARTTLS; 1.0/1.1 sudah tidak diterima MTA modern.
     *
     * @return int
     */
    protected function cryptoMethod() {
        $method = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
            $method |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        }

        return $method;
    }

    protected function smtpDisconnect() {
        $this->debug('QUIT');

        try {
            $this->debug($this->smtpSend('QUIT', false));
        } catch (Exception $ex) {
            $this->debug('Error on Disconnect:' . $ex->getMessage());
        }

        fclose($this->smtpConnection);
        $this->smtpConnection = null;
    }

    /**
     * Performs authentication with the SMTP host.
     *
     * @param string $smtpUsername
     * @param string $smtpPassword
     */
    protected function smtpAuthenticate($smtpUsername, $smtpPassword) {
        // Encode login data
        $username = base64_encode($smtpUsername);
        $password = base64_encode($smtpPassword);

        try {
            // Prepare login
            $this->debug('AUTH LOGIN');
            $this->debug($this->smtpSend('AUTH LOGIN', 334));

            // Send username
            $this->debug($username);
            $this->debug($this->smtpSend($username, 334));

            // Send password
            $this->debug($password);
            $this->debug($this->smtpSend($password, 235));
        } catch (CEmail_Exception_SmtpCommandFailureException $e) {
            // server tanpa AUTH LOGIN: coba AUTH PLAIN sebelum menyerah
            try {
                $this->debug('AUTH PLAIN');
                $this->debug($this->smtpSend('AUTH PLAIN ' . base64_encode("\0" . $smtpUsername . "\0" . $smtpPassword), 235));
            } catch (CEmail_Exception_SmtpCommandFailureException $plainException) {
                throw new CEmail_Exception_SmtpAuthenticationFailedException('Failed authentication: ' . $plainException->getMessage(), 0, $plainException);
            }
        }
    }

    /**
     * Sends data to the SMTP host.
     *
     * @param string             $data         The SMTP command
     * @param string|bool|string $expecting    The expected response
     * @param bool               $returnNumber Set to true to return the status number
     *
     * @throws CEmail_Exception_SmtpCommandFailureException when the command failed an expecting is not set to false
     * @throws CEmail_Exception_SmtpTimeoutException        SMTP connection timed out
     *
     * @return mixed Result or result number, false when expecting is false
     */
    protected function smtpSend($data, $expecting, $returnNumber = false) {
        !is_array($expecting) and $expecting !== false and $expecting = [$expecting];
        stream_set_timeout($this->smtpConnection, $this->getTimeout());
        if (!fwrite($this->smtpConnection, $data . "\r\n")) {
            if ($expecting === false) {
                return false;
            }

            throw new CEmail_Exception_SmtpCommandFailureException('Failed executing command: ' . $data);
        }

        $info = stream_get_meta_data($this->smtpConnection);
        if ($info['timed_out']) {
            throw new CEmail_Exception_SmtpTimeoutException('SMTP connection timed out.');
        }

        // Get the reponse
        $response = $this->smtpGetResponse();

        // Get the reponse number
        $number = (int) substr(trim($response), 0, 3);
        // Check against expected result
        if ($expecting !== false and !in_array($number, $expecting)) {
            throw new CEmail_Exception_SmtpCommandFailureException('Got an unexpected response from host on command: [' . $data . '] expecting: ' . join(' or ', $expecting) . ' received: ' . $response);
        }

        if ($returnNumber) {
            return $number;
        }

        return $response;
    }

    protected function getServerName() {
        $serverName = $this->config->getOption('domain', c::request()->server('SERVER_NAME', 'localhost.local'));

        return $serverName;
    }

    protected function getTimeout() {
        return $this->config->getOption('timeout', 5);
    }

    /**
     * Get SMTP response.
     *
     * @throws CEmail_Exception_SmtpTimeoutException
     *
     * @return string SMTP response
     */
    protected function smtpGetResponse() {
        $data = '';

        // set the timeout.
        stream_set_timeout($this->smtpConnection, $this->getTimeout());

        while ($str = fgets($this->smtpConnection, 512)) {
            $info = stream_get_meta_data($this->smtpConnection);
            if ($info['timed_out']) {
                throw new CEmail_Exception_SmtpTimeoutException('SMTP connection timed out.');
            }

            $data .= $str;

            if (substr($str, 3, 1) === ' ') {
                break;
            }
        }

        return $data;
    }

    public function newline() {
        return $this->config->getOption('newline', "\r\n");
    }

    public function debug($message) {
        if ($this->debug) {
            CLogger::debug($message);
        }
    }
}
