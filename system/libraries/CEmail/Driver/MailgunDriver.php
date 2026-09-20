<?php

/**
 * @deprecated 1.9 jalur pengirim lama; pakai CEmail::mailer() dengan transport `mailgun` (atau nyalakan `email.legacy_sender_via_mailer`)
 */
class CEmail_Driver_MailgunDriver extends CEmail_DriverAbstract {
    public function send(array $to, $subject, $body, $options = []) {
        $apiKey = $this->config->getPassword();

        $from = carr::get($options, 'from', $this->config->getFrom());
        $fromName = carr::get($options, 'from_name', $this->config->getFromName());

        $domain = carr::get($options, 'domain', carr::get($options, 'smtp_domain'));

        $url = 'https://api.mailgun.net/v3/messages';
        if ($domain) {
            $url = 'https://api.mailgun.net/v3/' . $domain . '/messages';
        }

        /*
          $template_id = '<your_template_id>';
          $js = array(
          'sub' => array(':name' => array('Elmer')),
          'filters' => array('templates' => array('settings' => array('enable' => 1, 'template_id' => $template_id)))
          );
         */

        $files = [];

        $params = [
            'to' => $to,
            'from' => $from,
            'subject' => $subject . '',
            'html' => $body,
        ];

        $cc = carr::get($options, 'cc');
        $bcc = carr::get($options, 'bcc');
        if (is_array($cc) && count($cc) > 0) {
            $params['cc'] = $cc;
        }
        if (is_array($bcc) && count($bcc) > 0) {
            $params['bcc'] = $bcc;
        }

        // Generate curl request
        $session = curl_init($url);
        curl_setopt($session, CURLOPT_SSL_VERIFYPEER, carr::get($options, 'verify_peer', true));
        curl_setopt($session, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($session, CURLOPT_USERPWD, 'api:' . $apiKey);
        curl_setopt($session, CURLOPT_ENCODING, 'UTF-8');
        // Tell curl to use HTTP POST
        curl_setopt($session, CURLOPT_POST, true);
        // Tell curl that this is the body of the POST
        curl_setopt($session, CURLOPT_POSTFIELDS, curl::asPostString($params));
        // Tell curl not to return headers, but do return the response
        curl_setopt($session, CURLOPT_HEADER, false);
        curl_setopt($session, CURLOPT_RETURNTRANSFER, true);

        if (count($files) > 0) {
            //curl_setopt($session, CURLOPT_SAFE_UPLOAD, false);
        }
        // obtain response
        $response = curl_exec($session);
        curl_close($session);

        $responseArray = json_decode($response, true);
        if (strlen(carr::get($responseArray, 'id')) == 0) {
            throw new Exception('Fail to send mail, API Response:' . $response);
        }

        return $responseArray;
    }
}
