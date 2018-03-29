<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Personalization
 *
 * @author Hery Kurniawan
 * @since Jan 7, 2018, 12:33:18 PM
 * @license Ittron Global Teknologi <ittron.co.id>
 */
class CSendGrid_Personalization implements \JsonSerializable {

    private $tos;
    private $ccs;
    private $bccs;
    private $subject;
    private $headers;
    private $substitutions;
    private $custom_args;
    private $send_at;

    public function addTo($email) {
        if (!is_array($email)) {
            $email = array($email);
        }
        foreach ($email as $em) {
            $this->tos[] = $em;
        }
    }

    public function getTos() {
        return $this->tos;
    }

    public function addCc($email) {
        if (!is_array($email)) {
            $email = array($email);
        }
        foreach ($email as $em) {
            $this->ccs[] = $em;
        }
    }

    public function getCcs() {
        return $this->ccs;
    }

    public function addBcc($email) {
        if (!is_array($email)) {
            $email = array($email);
        }
        foreach ($email as $em) {
            $this->bccs[] = $em;
        }
    }

    public function getBccs() {
        return $this->bccs;
    }

    public function setSubject($subject) {
        $this->subject = mb_convert_encoding($subject, 'UTF-8', 'UTF-8');
    }

    public function getSubject() {
        return $this->subject;
    }

    public function addHeader($key, $value) {
        $this->headers[$key] = $value;
    }

    public function getHeaders() {
        return $this->headers;
    }

    public function addSubstitution($key, $value) {
        $this->substitutions[$key] = $value;
    }

    public function getSubstitutions() {
        return $this->substitutions;
    }

    public function addCustomArg($key, $value) {
        $this->custom_args[$key] = (string) $value;
    }

    public function getCustomArgs() {
        return $this->custom_args;
    }

    public function setSendAt($send_at) {
        $this->send_at = $send_at;
    }

    public function getSendAt() {
        return $this->send_at;
    }

    public function jsonSerialize() {
        return array_filter(
                        [
                    'to' => $this->getTos(),
                    'cc' => $this->getCcs(),
                    'bcc' => $this->getBccs(),
                    'subject' => $this->subject,
                    'headers' => $this->getHeaders(),
                    'substitutions' => $this->getSubstitutions(),
                    'custom_args' => $this->getCustomArgs(),
                    'send_at' => $this->getSendAt()
                        ], function ($value) {
                    return $value !== null;
                }
                ) ?: null;
    }

}
